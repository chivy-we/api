<?php


namespace BeneficiaryBundle\Utils;


use BeneficiaryBundle\Entity\CountrySpecific;
use BeneficiaryBundle\Entity\Household;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use Symfony\Component\DependencyInjection\ContainerInterface;


class ExportCSVService
{

    /** @var EntityManagerInterface $em */
    private $em;

    /** @var ContainerInterface $container */
    private $container;

    private $MAPPING_HXL = [
        "Address street" => '#contact+address_street',
        "Address number" => '#contact+address_number',
        "Address postcode" => '#contact+address_postcode',
        "Livelihood" => '',
        "Notes" => '#description+notes',
        "Latitude" => '#geo+lat',
        "Longitude" => '#geo+lon',
        // Location
        "Adm1" => '#adm1+name',
        "Adm2" => '#adm2+name',
        "Adm3" => '#adm3+name',
        "Adm4" => '#adm4+name'
    ];

    private $MAPPING_CSV_EXPORT = [
        // Household
        "Address street" => 'Thompson Drive',
        "Address number" => '4943',
        "Address postcode" => '94801',
        "Livelihood" => '2',
        "Notes" => 'Greatest city',
        "Latitude" => '38.018234',
        "Longitude" => '-122.379730',
        // Location
        "Adm1" => 'USA',
        "Adm2" => 'California',
        "Adm3" => 'CA',
        "Adm4" => 'Richmond'
    ];

    private $MAPPING_DEPENDENTS = [
        // Household
        "Address street" => '',
        "Address number" => '',
        "Address postcode" => '',
        "Livelihood" => '',
        "Notes" => '',
        "Latitude" => '',
        "Longitude" => '',
        // Location
        "Adm1" => '',
        "Adm2" => '',
        "Adm3" => '',
        "Adm4" => ''
    ];

    private $MAPPING_DETAILS = [
        "Address street" => "String*",
        "Address number" => "Number*",
        "Address postcode" => "Number*",
        "Livelihood" => "Number [0-24]",
        "Notes" => "String",
        "Latitude" => "Float",
        "Longitude" => "Float",
        // Location
        "Adm1" => "String/empty",
        "Adm2" => "String/empty",
        "Adm3" => "String/empty",
        "Adm4" => "String/empty",
    ];

    public function __construct(EntityManagerInterface $entityManager, ContainerInterface $container)
    {
        $this->em = $entityManager;
        $this->container = $container;
    }

    /**
     * @param string $countryISO3
     * @param string $type
     * @return array
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    public function generate(string $countryISO3, string $type)
    {
        $spreadsheet = $this->buildFile($countryISO3);
        $filename = $this->container->get('export_csv_service')->generateFile($spreadsheet, 'pattern_household_' . $countryISO3, $type);
        
        return $filename;
    }

    /**
     * @param $countryISO3
     * @return Spreadsheet
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    public function buildFile($countryISO3)
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->createSheet();
        $worksheet = $spreadsheet->getActiveSheet();

        $countrySpecifics = $this->getCountrySpecifics($countryISO3);
        $columnsCountrySpecificsAdded = false;

        $i = 0;
        $worksheet->setCellValue('A' . 1, "Household");
        foreach ($this->MAPPING_CSV_EXPORT as $CSVIndex => $name)
        {
            if (!$columnsCountrySpecificsAdded && $CSVIndex >= Household::firstColumnNonStatic)
            {
                if (!empty($countrySpecifics))
                {
                    $worksheet->setCellValue(($this->SUMOfLetter($CSVIndex, $i)) . 1, "Country Specifics");
                    /** @var CountrySpecific $countrySpecific */
                    foreach ($countrySpecifics as $countrySpecific)
                    {
                        $worksheet->setCellValue(($this->SUMOfLetter($CSVIndex, $i)) . 2, $countrySpecific->getFieldString());
                        $i++;
                    }
                }
                $worksheet->setCellValue(($this->SUMOfLetter($CSVIndex, $i)) . 1, "Beneficiary");
                $worksheet->setCellValue(($this->SUMOfLetter($CSVIndex, $i)) . 2, $name);
                $columnsCountrySpecificsAdded = true;
            }
            else
            {
                if ($CSVIndex >= Household::firstColumnNonStatic)
                {
                    $worksheet->setCellValue(($this->SUMOfLetter($CSVIndex, $i)) . 2, $name);
                }
                else
                {
                    $worksheet->setCellValue($CSVIndex . 2, $name);
                }
            }
        }

        return $spreadsheet;
    }

    /**
     * @param $countryISO3
     * @return mixed
     */
    private function getCountrySpecifics($countryISO3)
    {
        $countrySpecifics = $this->em->getRepository(CountrySpecific::class)->findByCountryIso3($countryISO3);
        return $countrySpecifics;
    }

    /**
     * Make an addition of a letter and a number
     * Example : A + 2 = C  Or  Z + 1 = AA  OR  AY + 2 = BA
     * @param $letter1
     * @param $number
     * @return string
     */
    private function SUMOfLetter($letter1, $number)
    {
        $ascii = ord($letter1) + $number;
        $prefix = '';
        if ($ascii > 90)
        {
            $prefix = 'A';
            $ascii -= 26;
            while ($ascii > 90)
            {
                $prefix++;
                $ascii -= 90;
            }
        }
        return $prefix . chr($ascii);
    }

    /**
     * Export all projects of the country in the CSV file
     * @param string $type
     * @param string $countryISO3
     * @return mixed
     */
    public function exportToCsv(string $type, string $countryISO3) {
        $tempHxl = [
            "Given name" => '#beneficiary+givenName',
            "Family name" => '#beneficiary+familyName',
            "Gender" => '',
            "Status" => '',
            "Date of birth" => '#beneficiary+birth',
            "Vulnerability criteria" => '',
            "Phones" => '#contact+phone',
            "National IDs" => '',
            "  " => '',
            "" => "     -->",
            " " => 'Do not remove this line.'
        ];

        $tempBenef = [
            "Given name" => 'Price',
            "Family name" => 'Smith',
            "Gender" => 'Female',
            "Status" => '1',
            "Date of birth" => '1997-10-31',
            "Vulnerability criteria" => 'disabled',
            "Phones" => 'Mobile - 0145678348 - N',
            "National IDs" => 'IDCard - 030617701',
            "  " => '[Head]',
            "" => "     -->",
            " " => 'This Example line and the Type Helper line below must not be removed.'
        ];

        $dependent = [
            "Given name" => 'James',
            "Family name" => 'Smith',
            "Gender" => 'Male',
            "Status" => '0',
            "Date of birth" => '07/25/2001',
            "Vulnerability criteria" => '',
            "Phones" => 'Mobile - 0214844628 - Y',
            "National IDs" => '',
            "  " => '[Member]',
            "" => "     -->",
            " " => "'*' means that the property is needed -- Use ';' to separate multiple values -- An adm must be filled among Adm1/Adm2/Adm3/Adm4."
        ];

        $details = [
            "Given name" => 'String*',
            "Family name" => 'String*',
            "Gender" => 'Male / Female*',
            "Status" => 'Number [0-1]*',
            "Date of birth" => 'YYYY-MM-DD',
            "Vulnerability criteria" => 'String',
            "Phones" => '"TypeAsString" - Number - Y / N (Proxy)',
            "National IDs" => '"TypeAsString" - Number',
            "  " => '',
            "" => "     -->",
            " " => "'*' means that the property is needed -- Use ';' to separate multiple values -- An adm must be filled among Adm1/Adm2/Adm3/Adm4."
        ];

        $MAPPING_CSV_EXPORT = array();
        $countrySpecifics = $this->getCountrySpecifics($countryISO3);
        foreach ($countrySpecifics as $countrySpecific){
            $randomNum = rand(0, 100);
            $this->MAPPING_HXL[$countrySpecific->getFieldString()] = '';
            $this->MAPPING_CSV_EXPORT[$countrySpecific->getFieldString()] = $randomNum;
            $this->MAPPING_DEPENDENTS[$countrySpecific->getFieldString()] = '';
            $this->MAPPING_DETAILS[$countrySpecific->getFieldString()] = $countrySpecific->getType();
        }

        foreach ($tempHxl as $key => $value)
            $this->MAPPING_HXL[$key] = $value;
        foreach ($tempBenef as $key => $value)
            $this->MAPPING_CSV_EXPORT[$key] = $value;
        foreach ($dependent as $key => $value)
            $this->MAPPING_DEPENDENTS[$key] = $value;
        foreach($details as $key => $detail)
            $this->MAPPING_DETAILS[$key] = $detail;

        array_push($MAPPING_CSV_EXPORT, $this->MAPPING_HXL);
        array_push($MAPPING_CSV_EXPORT, $this->MAPPING_CSV_EXPORT);
        array_push($MAPPING_CSV_EXPORT, $this->MAPPING_DEPENDENTS);
        array_push($MAPPING_CSV_EXPORT, $this->MAPPING_DETAILS);

        return $this->container->get('export_csv_service')->export($MAPPING_CSV_EXPORT, 'pattern_household_'  . $countryISO3, $type);
    }
}