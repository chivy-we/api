<?php


namespace BeneficiaryBundle\Utils;


use BeneficiaryBundle\Entity\Beneficiary;
use BeneficiaryBundle\Entity\CountrySpecific;
use BeneficiaryBundle\Entity\Household;
use BeneficiaryBundle\Entity\VulnerabilityCriterion;
use BeneficiaryBundle\Model\ImportStatistic;
use BeneficiaryBundle\Model\IncompleteLine;
use BeneficiaryBundle\Utils\DataTreatment\AbstractTreatment;
use BeneficiaryBundle\Utils\DataTreatment\DuplicateTreatment;
use BeneficiaryBundle\Utils\DataTreatment\LessTreatment;
use BeneficiaryBundle\Utils\DataTreatment\MoreTreatment;
use BeneficiaryBundle\Utils\DataTreatment\TypoTreatment;
use BeneficiaryBundle\Utils\DataVerifier\AbstractVerifier;
use BeneficiaryBundle\Utils\DataVerifier\DuplicateVerifier;
use BeneficiaryBundle\Utils\DataVerifier\LessVerifier;
use BeneficiaryBundle\Utils\DataVerifier\MoreVerifier;
use BeneficiaryBundle\Utils\DataVerifier\TypoVerifier;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use ProjectBundle\Entity\Project;
use RA\RequestValidatorBundle\RequestValidator\ValidationException;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Kernel;

class HouseholdCSVService
{

    /** @var EntityManagerInterface $em */
    private $em;

    /** @var HouseholdService $householdService */
    private $householdService;

    /** @var Mapper $mapper */
    private $mapper;

    /** @var Container $container */
    private $container;

    /** @var BeneficiaryService $beneficiaryService */
    private $beneficiaryService;

    /** @var string $token */
    private $token;


    public function __construct(
        EntityManagerInterface $entityManager,
        HouseholdService $householdService,
        BeneficiaryService $beneficiaryService,
        Mapper $mapper,
        Container $container
    )
    {
        $this->em = $entityManager;
        $this->householdService = $householdService;
        $this->beneficiaryService = $beneficiaryService;
        $this->mapper = $mapper;
        $this->container = $container;
    }


    /**
     * Defined the reader and transform CSV to array
     *
     * @param $countryIso3
     * @param Project $project
     * @param UploadedFile $uploadedFile
     * @param int $step
     * @param $token
     * @return array
     * @throws \Exception
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     */
    public function saveCSV($countryIso3, Project $project, UploadedFile $uploadedFile, int $step, $token)
    {
        // If it's the first step, we transform CSV to array mapped for corresponding to the entity Household
        // LOADING CSV
        $reader = new Csv();
        $reader->setDelimiter(",");
        $worksheet = $reader->load($uploadedFile->getRealPath())->getActiveSheet();
        $sheetArray = $worksheet->toArray(null, true, true, true);

        return $this->transformAndAnalyze($countryIso3, $project, $sheetArray, $step, $token);
    }

    /**
     * @param $countryIso3
     * @param Project $project
     * @param array $sheetArray
     * @param int $step
     * @param $token
     * @return array|bool
     * @throws \Exception
     */
    public function transformAndAnalyze($countryIso3, Project $project, array $sheetArray, int $step, $token)
    {
        // Get the list of households from csv with their beneficiaries
        if (1 === $step)
        {
            $listHouseholdsArray = $this->mapper->getListHouseholdArray($sheetArray, $countryIso3);
            return $this->foundErrors($countryIso3, $project, $listHouseholdsArray, $step, $token);
        }
        else
        {
            return $this->foundErrors($countryIso3, $project, $sheetArray, $step, $token);
        }
    }

    /**
     * @param $countryIso3
     * @param Project $project
     * @param array $listHouseholdsArray
     * @param int $step
     * @param $token
     * @return array|bool
     * @throws \Exception
     */
    public function foundErrors($countryIso3, Project $project, array $listHouseholdsArray, int $step, $token)
    {
        $this->clearData();
        $this->token = $token;
        if (!$this->checkTokenAndStep($step))
            throw new \Exception("Your session for this import has expired");
        // If there is a treatment class for this step, call it
        $treatment = $this->guessTreatment($step);
        if ($treatment !== null)
            $listHouseholdsArray = $treatment->treat($project, $listHouseholdsArray);

        /** @var AbstractVerifier $verifier */
        $verifier = $this->guessVerifier($step);
        $return = [];
        if (null === $verifier)
        {
            $this->clearCacheToken($this->token);
            return true;
        }
        $cache_id = 1;
        $householdsToSave = [];
        foreach ($listHouseholdsArray as $index => $householdArray)
        {
            $returnTmp = $verifier->verify($countryIso3, $householdArray, $cache_id);
            // IF there is errors
            if (null !== $returnTmp && [] !== $returnTmp)
            {
                if ($returnTmp !== false)
                    $return[] = $returnTmp;
            }
            else
            {
                $householdsToSave[$cache_id] = $householdArray;
            }

            $cache_id++;
            unset($listHouseholdsArray[$index]);
        }

        $this->saveInCache($step, json_encode($householdsToSave));
        $this->setTimeExpiry();
        return ["data" => $return, "token" => $this->token];
    }

    /**
     * Depends on the step, guess which verifier used
     * @param int $step
     * @return AbstractVerifier
     * @throws \Exception
     */
    private function guessVerifier(int $step)
    {
        switch ($step)
        {
            // CASE FOUND TYPO ISSUES
            case 1:
                return new TypoVerifier($this->em, $this->container, $this->initOrGetToken());
                break;
            // CASE FOUND DUPLICATED ISSUES
            case 2:
                return new DuplicateVerifier($this->em, $this->container, $this->initOrGetToken());
                break;
            // CASE FOUND MORE ISSUES
            case 3:
                return new MoreVerifier($this->em);
                break;
            // CASE FOUND LESS ISSUES
            case 4:
                return new LessVerifier($this->em);
                break;
            // CASE FOUND LESS ISSUES
            case 5:
                return null;
                break;
            // NOT FOUND CASE
            default:
                throw new \Exception("Step '$step' unknown.");
        }
    }

    /**
     * Depends on the step, guess which verifier used
     * @param int $step
     * @return AbstractTreatment
     * @throws \Exception
     */
    private function guessTreatment(int $step)
    {
        switch ($step)
        {
            case 1:
                return null;
                break;
            // CASE FOUND TYPO ISSUES
            case 2:
                return new TypoTreatment($this->em, $this->householdService, $this->beneficiaryService, $this->container, $this->initOrGetToken());
                break;
            // CASE FOUND DUPLICATED ISSUES
            case 3:
                return new DuplicateTreatment($this->em, $this->householdService, $this->beneficiaryService, $this->container, $this->initOrGetToken());
                break;
            // CASE FOUND MORE ISSUES
            case 4:
                return new MoreTreatment($this->em, $this->householdService, $this->beneficiaryService, $this->container, $this->initOrGetToken());
                break;
            // CASE FOUND LESS ISSUES
            case 5:
                return new LessTreatment($this->em, $this->householdService, $this->beneficiaryService, $this->container, $this->initOrGetToken());
                break;
            // NOT FOUND CASE
            default:
                throw new \Exception("Step '$step' unknown.");
        }
    }

    /**
     * @param $step
     * @return bool
     * @throws \Exception
     */
    private function checkTokenAndStep($step)
    {
        if (intval($step) === 1)
            return true;

        $dir_root = $this->container->get('kernel')->getRootDir();
        $dir_token = $dir_root . '/../var/data/' . $this->token;
        if (!is_dir($dir_token))
            return false;

        $dir_file_step = $dir_token . '/step_' . strval(intval($step) - 1);
        if (!is_file($dir_file_step))
            return false;

        return true;
    }

    /**
     * If the token is null, create a new one
     * return the token
     * @return string
     */
    public function initOrGetToken()
    {
        $sizeToken = 50;
        if (null === $this->token)
            $this->token = bin2hex(random_bytes($sizeToken));

        return $this->token;
    }

    /**
     * @param int $step
     * @param $dataToSave
     * @throws \Exception
     */
    private function saveInCache(int $step, $dataToSave)
    {
        $this->initOrGetToken();
        $dir_root = $this->container->get('kernel')->getRootDir();
        $dir_var = $dir_root . '/../var/data';
        if (!is_dir($dir_var))
            mkdir($dir_var);
        $dir_var_token = $dir_var . '/' . $this->token;
        if (!is_dir($dir_var_token))
            mkdir($dir_var_token);
        file_put_contents($dir_var_token . '/step_' . $step, $dataToSave);
    }

    /**
     * @throws \Exception
     */
    private function setTimeExpiry()
    {
        $dir_root = $this->container->get('kernel')->getRootDir();
        $dir_var = $dir_root . '/../var/data';
        if (!is_dir($dir_var))
            mkdir($dir_var);
        $dir_file = $dir_var . '/timestamp_token';
        if (is_file($dir_file))
        {
            $timestampByToken = json_decode(file_get_contents($dir_file), true);
        }
        else
        {
            $timestampByToken = [];
        }

        $index = null;
        $timestamp = null;
        $dateExpiry = new \DateTime();
        $dateExpiry->add(new \DateInterval('PT10M'));
        $timestampByToken[$this->token] = [
            'timestamp' => $dateExpiry->getTimestamp()
        ];

        file_put_contents($dir_var . '/timestamp_token', json_encode($timestampByToken));
    }

    /**
     * @throws \Exception
     */
    private function clearData()
    {
        $dir_root = $this->container->get('kernel')->getRootDir();
        $dir_var = $dir_root . '/../var/data';
        if (!is_dir($dir_var))
            mkdir($dir_var);
        $dir_file = $dir_var . '/timestamp_token';
        if (is_file($dir_file))
        {
            $timestampByToken = json_decode(file_get_contents($dir_file), true);
        }
        else
        {
            $this->rrmdir($dir_var);
            return;
        }

        foreach ($timestampByToken as $token => $item)
        {
            if ((new \DateTime())->getTimestamp() > $item['timestamp'])
            {
                $this->rrmdir($dir_var . '/' . $token);
                unset($timestampByToken[$token]);
            }
        }

        file_put_contents($dir_var . '/timestamp_token', json_encode($timestampByToken));
    }

    /**
     * @param $token
     * @throws \Exception
     */
    private function clearCacheToken($token)
    {
        $dir_root = $this->container->get('kernel')->getRootDir();
        $dir_var = $dir_root . '/../var/data';
        if (!is_dir($dir_var))
            mkdir($dir_var);
        $dir_file = $dir_var . '/timestamp_token';
        if (is_file($dir_file))
        {
            $timestampByToken = json_decode(file_get_contents($dir_file), true);
        }
        else
        {
            $this->rrmdir($dir_var);
            return;
        }

        if (is_dir($dir_var . '/' . $token))
            $this->rrmdir($dir_var . '/' . $token);
        if (array_key_exists($token, $timestampByToken))
            unset($timestampByToken[$token]);

        file_put_contents($dir_var . '/timestamp_token', json_encode($timestampByToken));
    }

    /**
     * @param $src
     */
    private function rrmdir($src)
    {
        $dir = opendir($src);
        while (false !== ($file = readdir($dir)))
        {
            if (($file != '.') && ($file != '..'))
            {
                $full = $src . '/' . $file;
                if (is_dir($full))
                {
                    $this->rrmdir($full);
                }
                else
                {
                    unlink($full);
                }
            }
        }
        closedir($dir);
        rmdir($src);
    }

    /**
     * Check if a value is missing inside the array
     *
     * @param array $array
     * @return bool
     */
    private function isIncomplete(array $array)
    {
        $isIncomplete = true;
        foreach ($array as $key => $value)
        {
            if (is_array($value))
                $isIncomplete = $this->isIncomplete($value);
            if (!$isIncomplete || null === $value)
            {
                return false;
            }
        }

        return $isIncomplete;
    }
}