<?php

namespace Tests\BeneficiaryBundle\Controller;

use BeneficiaryBundle\Entity\Beneficiary;
use BeneficiaryBundle\Entity\CountrySpecific;
use BeneficiaryBundle\Entity\CountrySpecificAnswer;
use BeneficiaryBundle\Entity\Household;
use BeneficiaryBundle\Entity\NationalId;
use BeneficiaryBundle\Entity\Phone;
use BeneficiaryBundle\Model\ImportStatistic;
use BeneficiaryBundle\Utils\ExportCSVService;
use BeneficiaryBundle\Utils\HouseholdCSVService;
use ProjectBundle\Entity\Project;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Tests\BMSServiceTestCase;


class HouseholdCSVTest extends BMSServiceTestCase
{
    /** @var HouseholdCSVService $hhCSVService */
    private $hhCSVService;
    /** @var ExportCSVService $exportCSVService */
    private $exportCSVService;

    private $iso3 = "KHM";
    private $addressStreet = "ADDR TEST_IMPORT";

    private $SHEET_ARRAY = [
        1 => [
            "A" => "Household",
            "B" => null,
            "C" => null,
            "D" => null,
            "E" => null,
            "F" => null,
            "G" => null,
            "H" => null,
            "I" => null,
            "J" => null,
            "K" => null,
            "L" => "Country Specifics",
            "M" => null,
            "N" => "Beneficiary",
            "O" => null,
            "P" => null,
            "Q" => null,
            "R" => null,
            "S" => null,
            "T" => null,
            "U" => null,
        ],
        2 => [
            "A" => "Address street",
            "B" => "Address number",
            "C" => "Address Postcode",
            "D" => "Livelihood",
            "E" => "Notes",
            "F" => "Latitude",
            "G" => "Longitude",
            "H" => "adm1",
            "I" => "adm2",
            "J" => "adm3",
            "K" => "adm4",
            "L" => "ID Poor",
            "M" => "WASH",
            "N" => "Given name",
            "O" => "Family name",
            "P" => "Gender",
            "Q" => "Status",
            "R" => "Date of birth",
            "S" => "Vulnerability criterions",
            "T" => "Phones",
            "U" => "National Ids",
        ],
        3 => [
            "A" => "ADDR TEST_IMPORT",
            "B" => 1.0,
            "C" => 11.0,
            "D" => 10.0,
            "E" => "this is just some notes",
            "F" => 1.1544,
            "G" => 120.12,
            "H" => "TEST_IMPORT",
            "I" => "TEST_IMPORT",
            "J" => "TEST_IMPORT",
            "K" => "TEST_IMPORT",
            "L" => 4.0,
            "M" => "my wash",
            "N" => "FIRSTNAME TEST_IMPORT",
            "O" => "NAME TEST_IMPORT",
            "P" => "F",
            "Q" => 1,
            "R" => "1995-04-25",
            "S" => "lactating ; single family",
            "T" => "Type1 - 1",
            "U" => "card-152a",
        ],
        4 => [
            "A" => null,
            "B" => null,
            "C" => null,
            "D" => null,
            "E" => null,
            "F" => null,
            "G" => null,
            "H" => null,
            "I" => null,
            "J" => null,
            "K" => null,
            "L" => null,
            "M" => null,
            "N" => "FIRSTNAME2 TEST_IMPORT",
            "O" => "NAME2 TEST_IMPORT",
            "P" => "M",
            "Q" => 0,
            "R" => "1995-04-25",
            "S" => "lactating",
            "T" => "Type1 - 2",
            "U" => "id-45f",
        ]
    ];

    /**
     * @throws \Exception
     */
    public function setUp()
    {
        parent::setUpFunctionnal();
        $this->hhCSVService = $this->container->get('beneficiary.household_csv_service');
        $this->exportCSVService = $this->container->get('beneficiary.household_export_csv_service');
    }

    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function testExportCSV()
    {
        $countrySpecifics = $this->em->getRepository(CountrySpecific::class)->findByCountryIso3($this->iso3);
        $csvGenerated = $this->exportCSVService->generateCSV($this->iso3);
        $csvArray = str_replace('"', '', explode(",", explode("\n", current($csvGenerated))[1]));
        $this->assertContains("Address street", $csvArray);
        $this->assertContains("Address number", $csvArray);
        $this->assertContains("Address postcode", $csvArray);
        $this->assertContains("Livelihood", $csvArray);
        $this->assertContains("Notes", $csvArray);
        $this->assertContains("Latitude", $csvArray);
        $this->assertContains("Longitude", $csvArray);
        $this->assertContains("Adm1", $csvArray);
        $this->assertContains("Adm2", $csvArray);
        $this->assertContains("Adm3", $csvArray);
        $this->assertContains("Adm4", $csvArray);
        $this->assertContains("Given name", $csvArray);
        $this->assertContains("Family name", $csvArray);
        $this->assertContains("Gender", $csvArray);
        $this->assertContains("Status", $csvArray);
        $this->assertContains("Date of birth", $csvArray);
        $this->assertContains("Vulnerability criteria", $csvArray);
        $this->assertContains("Phones", $csvArray);
        $this->assertContains("National IDs", $csvArray);

        foreach ($countrySpecifics as $countrySpecific)
        {
            $this->assertContains($countrySpecific->getField(), $csvArray);
        }
    }

    /**
     * @deprecated since v3 import CSV
     * @throws \Doctrine\Common\Persistence\Mapping\MappingException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function testImportCSV()
    {
        $projects = $this->em->getRepository(Project::class)->findAll();
        if (empty($projects))
        {
            print_r("\nThere is no project in your database.\n\n");
            return;
        }
        /** @var ImportStatistic $statistic */
        $return = $this->hhCSVService->loadCSV($this->iso3, current($projects), $this->SHEET_ARRAY);

        try
        {
            // First adding should work
            $this->assertSame([], $return["typo"]);
            $this->assertSame([], $return["duplicate"]);
            $this->assertSame([], $return["more"]);
            $this->assertSame([], $return["less"]);
            $this->assertSame(1, $return["statistic"]->getNbAdded());
            $this->assertSame([], $return["statistic"]->getIncompleteLine());

            // Adding same household, without any difference, so the function should only try to add the household to the project
            $return = $this->hhCSVService->loadCSV($this->iso3, current($projects), $this->SHEET_ARRAY);
            $this->assertSame([], $return["typo"]);
            $this->assertSame([], $return["duplicate"]);
            $this->assertSame([], $return["more"]);
            $this->assertSame([], $return["less"]);
            $this->assertSame(1, $return["statistic"]->getNbAdded());
            $this->assertSame([], $return["statistic"]->getIncompleteLine());

            // Should return an issue => more beneficiaries in the CSV than in the db
            $this->SHEET_ARRAY[5] = [
                "A" => null,
                "B" => null,
                "C" => null,
                "D" => null,
                "E" => null,
                "F" => null,
                "G" => null,
                "H" => null,
                "I" => null,
                "J" => null,
                "K" => null,
                "L" => null,
                "M" => null,
                "N" => "FIRSTNAME3 TEST_IMPORT",
                "O" => "NAME3 TEST_IMPORT",
                "P" => "M",
                "Q" => 0,
                "R" => "1995-04-25",
                "S" => "lactating",
                "T" => "Type1 - 2",
                "U" => "id-45f",
            ];
            $return = $this->hhCSVService->loadCSV($this->iso3, current($projects), $this->SHEET_ARRAY);
            $this->assertArrayHasKey("new", current($return["more"]));
            $this->assertArrayHasKey("old", current($return["more"]));
            $this->assertSame([], $return["typo"]);
            $this->assertSame([], $return["duplicate"]);
            $this->assertSame([], $return["less"]);
            $this->assertSame(0, $return["statistic"]->getNbAdded());
            $this->assertSame([], $return["statistic"]->getIncompleteLine());

            // Should return an issue => same number of beneficiaries but one with a difference in the typo
            unset($this->SHEET_ARRAY[5]);
            $this->SHEET_ARRAY[4]['N'] = 'A';
            $return = $this->hhCSVService->loadCSV($this->iso3, current($projects), $this->SHEET_ARRAY);
            $this->assertArrayHasKey("new", current($return["typo"]));
            $this->assertArrayHasKey("old", current($return["typo"]));
            $this->assertSame([], $return["duplicate"]);
            $this->assertSame([], $return["more"]);
            $this->assertSame([], $return["less"]);
            $this->assertSame(0, $return["statistic"]->getNbAdded());
            $this->assertSame([], $return["statistic"]->getIncompleteLine());

            // Should return a line incomplete
            $this->SHEET_ARRAY[4]["N"] = null;
            $return = $this->hhCSVService->loadCSV($this->iso3, current($projects), $this->SHEET_ARRAY);
            $this->assertSame([], $return["typo"]);
            $this->assertSame([], $return["duplicate"]);
            $this->assertSame([], $return["more"]);
            $this->assertSame([], $return["less"]);
            $this->assertSame(0, $return["statistic"]->getNbAdded());
            $this->assertSame(3, current($return["statistic"]->getIncompleteLine())->getLineIncomplete());

            // Should return an issue => less beneficiaries in the CSV than in the db
            unset($this->SHEET_ARRAY[4]);
            $return = $this->hhCSVService->loadCSV($this->iso3, current($projects), $this->SHEET_ARRAY);
            $this->assertArrayHasKey("new", current($return["less"]));
            $this->assertArrayHasKey("old", current($return["less"]));
            $this->assertSame([], $return["typo"]);
            $this->assertSame([], $return["duplicate"]);
            $this->assertSame([], $return["more"]);
            $this->assertSame(0, $return["statistic"]->getNbAdded());
            $this->assertSame([], $return["statistic"]->getIncompleteLine());

            // Should return an issue => duplicate beneficiary
            $this->SHEET_ARRAY[3]['A'] = 'a';
            $this->SHEET_ARRAY[3]['B'] = 'b';
            $this->SHEET_ARRAY[4] = [
                "A" => null,
                "B" => null,
                "C" => null,
                "D" => null,
                "E" => null,
                "F" => null,
                "G" => null,
                "H" => null,
                "I" => null,
                "J" => null,
                "K" => null,
                "L" => null,
                "M" => null,
                "N" => "FIRSTNAME2 TEST_IMPORT",
                "O" => "NAME2 TEST_IMPORT",
                "P" => "M",
                "Q" => 0,
                "R" => "1995-04-25",
                "S" => "lactating",
                "T" => "Type1 - 2",
                "U" => "id-45f",
            ];
            $return = $this->hhCSVService->loadCSV($this->iso3, current($projects), $this->SHEET_ARRAY);
            $this->assertArrayHasKey("new", current($return["duplicate"]));
            $this->assertArrayHasKey("old", current($return["duplicate"]));
            $this->assertSame([], $return["typo"]);
            $this->assertSame([], $return["more"]);
            $this->assertSame([], $return["less"]);
            $this->assertSame(0, $return["statistic"]->getNbAdded());
            $this->assertSame([], $return["statistic"]->getIncompleteLine());
        }
        catch (\Exception $exception)
        {
            $this->remove($this->addressStreet);
            $this->fail($exception->getMessage() . "\n\n");
        }
        $this->remove($this->addressStreet);
    }

    /**
     * @depends testGetHouseholds
     *
     * @param $addressStreet
     * @throws \Doctrine\Common\Persistence\Mapping\MappingException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function remove($addressStreet)
    {
        $this->em->clear();
        /** @var Household $household */
        $household = $this->em->getRepository(Household::class)->findOneByAddressStreet($addressStreet);
        if ($household instanceof Household)
        {
            $beneficiaries = $this->em->getRepository(Beneficiary::class)->findByHousehold($household);
            if (!empty($beneficiaries))
            {
                /** @var Beneficiary $beneficiary */
                foreach ($beneficiaries as $beneficiary)
                {
                    $phones = $this->em->getRepository(Phone::class)->findByBeneficiary($beneficiary);
                    $nationalIds = $this->em->getRepository(NationalId::class)->findByBeneficiary($beneficiary);
                    foreach ($phones as $phone)
                    {
                        $this->em->remove($phone);
                    }
                    foreach ($nationalIds as $nationalId)
                    {
                        $this->em->remove($nationalId);
                    }
                    $this->em->remove($beneficiary->getProfile());
                    $this->em->remove($beneficiary);
                }
            }
            $location = $household->getLocation();
            $this->em->remove($location);

            $countrySpecificAnswers = $this->em->getRepository(CountrySpecificAnswer::class)
                ->findByHousehold($household);
            foreach ($countrySpecificAnswers as $countrySpecificAnswer)
            {
                $this->em->remove($countrySpecificAnswer);
            }

            $this->em->remove($household);
            $this->em->flush();
        }
    }

}