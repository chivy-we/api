<?php


namespace Tests\BeneficiaryBundle\Controller;


use BeneficiaryBundle\Entity\Beneficiary;
use BeneficiaryBundle\Entity\CountrySpecificAnswer;
use BeneficiaryBundle\Entity\Household;
use BeneficiaryBundle\Entity\NationalId;
use BeneficiaryBundle\Entity\Phone;
use ProjectBundle\Entity\Project;
use Symfony\Component\BrowserKit\Client;
use Tests\BMSServiceTestCase;

class HouseholdControllerTest extends BMSServiceTestCase
{

    /** @var Client $client */
    private $client;

    private $namefullname = "STREET_TEST";


    private $body = [
        "project" => 1,
        "address_street" => "STREET_TEST",
        "address_number" => "NUMBER_TEST",
        "address_postcode" => "POSTCODE_TEST",
        "livelihood" => 10,
        "notes" => "NOTES_TEST",
        "latitude" => "1.1544",
        "longitude" => "120.12",
        "location" => [
            "country_iso3" => "COUNTRY_TEST",
            "adm1" => "ADM1_TEST",
            "adm2" => "ADM2_TEST",
            "adm3" => "ADM3_TEST",
            "adm4" => "ADM4_TEST"
        ],
        "country_specific_answers" => [
            [
                "answer" => "MY_ANSWER_TEST",
                "country_specific" => [
                    "id" => 1
                ]
            ]
        ],
        "beneficiaries" => [
            [
                "given_name" => "FIRSTNAME_TEST",
                "family_name" => "NAME_TEST",
                "gender" => "h",
                "status" => 1,
                "date_of_birth" => "1976-10-06",
                "updated_on" => "2018-06-13 12:12:12",
                "profile" => [
                    "photo" => "PHOTO_TEST"
                ],
                "vulnerability_criteria" => [
                    [
                        "id" => 1
                    ]
                ],
                "phones" => [
                    [
                        "number" => "0000_TEST",
                        "type" => "TYPE_TEST"
                    ]
                ],
                "national_ids" => [
                    [
                        "id_number" => "0000_TEST",
                        "id_type" => "ID_TYPE_TEST"
                    ]
                ]
            ],
            [
                "given_name" => "GIVENNAME_TEST",
                "family_name" => "FAMILYNAME_TEST",
                "gender" => "h",
                "status" => 0,
                "date_of_birth" => "1976-10-06",
                "updated_on" => "2018-06-13 12:12:12",
                "profile" => [
                    "photo" => "PHOTO_TEST"
                ],
                "vulnerability_criteria" => [
                    [
                        "id" => 1
                    ]
                ],
                "phones" => [
                    [
                        "number" => "1111_TEST",
                        "type" => "TYPE_TEST"
                    ]
                ],
                "national_ids" => [
                    [
                        "id_number" => "1111_TEST",
                        "id_type" => "ID_TYPE_TEST"
                    ]
                ]
            ]
        ]
    ];

    /**
     * @throws \Exception
     */
    public function setUp()
    {
        // Configuration of BMSServiceTest
        $this->setDefaultSerializerName("jms_serializer");
        parent::setUpFunctionnal();

        // Get a Client instance for simulate a browser
        $this->client = $this->container->get('test.client');
    }

    /**
     * @throws \Exception
     */
    public function testCreateHousehold()
    {
        // Fake connection with a token for the user tester (ADMIN)
        $user = $this->getTestUser(self::USER_TESTER);
        $token = $this->getUserToken($user);
        $this->tokenStorage->setToken($token);

        $projects = $this->em->getRepository(Project::class)->findAll();
        if (empty($projects))
        {
            print_r("There is no project inside your database");
            return false;
        }
        $crawler = $this->client->request(
            'PUT',
            '/api/wsse/households/project/' . current($projects)->getId(),
            $this->body,
            [],
            ['HTTP_COUNTRY' => 'COUNTRY_TEST']
        );
        $household = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($this->client->getResponse()->isSuccessful());

        try
        {
            $this->assertArrayHasKey('id', $household);
            $this->assertArrayHasKey('address_street', $household);
            $this->assertArrayHasKey('address_number', $household);
            $this->assertArrayHasKey('address_postcode', $household);
            $this->assertArrayHasKey('livelihood', $household);
            $this->assertArrayHasKey('notes', $household);
            $this->assertArrayHasKey('latitude', $household);
            $this->assertArrayHasKey('longitude', $household);
            $this->assertArrayHasKey('location', $household);
            $this->assertArrayHasKey('country_specific_answers', $household);
            $this->assertArrayHasKey('beneficiaries', $household);
            $location = $household["location"];
            $this->assertArrayHasKey('country_iso3', $location);
            $this->assertArrayHasKey('adm1', $location);
            $this->assertArrayHasKey('adm2', $location);
            $this->assertArrayHasKey('adm3', $location);
            $this->assertArrayHasKey('adm4', $location);
            $country_specific_answer = current($household["country_specific_answers"]);
            $this->assertArrayHasKey('answer', $country_specific_answer);
            $this->assertArrayHasKey('country_specific', $country_specific_answer);
            $beneficiary = current($household["beneficiaries"]);
            $this->assertArrayHasKey('given_name', $beneficiary);
            $this->assertArrayHasKey('family_name', $beneficiary);
            $this->assertArrayHasKey('gender', $beneficiary);
            $this->assertArrayHasKey('status', $beneficiary);
            $this->assertArrayHasKey('date_of_birth', $beneficiary);
            $this->assertArrayHasKey('updated_on', $beneficiary);
            $this->assertArrayHasKey('profile', $beneficiary);
            $this->assertArrayHasKey('vulnerability_criteria', $beneficiary);
            $this->assertArrayHasKey('phones', $beneficiary);
            $this->assertArrayHasKey('national_ids', $beneficiary);
            $profile = $beneficiary["profile"];
            $this->assertArrayHasKey('photo', $profile);
            $vulnerability_criterion = current($beneficiary["vulnerability_criteria"]);
            $this->assertArrayHasKey('id', $vulnerability_criterion);
            $phone = current($beneficiary["phones"]);
            $this->assertArrayHasKey('number', $phone);
            $this->assertArrayHasKey('type', $phone);
            $national_ids = current($beneficiary["national_ids"]);
            $this->assertArrayHasKey('id_number', $national_ids);
            $this->assertArrayHasKey('id_type', $national_ids);
        }
        catch (\Exception $exception)
        {
            $this->remove($this->namefullname);
            $this->fail("\nThe mapping of fields of Household entity is not correct (1).\n");
            return false;
        }

        return true;
    }

    /**
     * @depends testCreateHousehold
     * @throws \Exception
     */
    public function testGetAllHouseholds($isSuccess = true)
    {
        if (!$isSuccess)
        {
            print_r("\nThe creation of household failed. We can't test the get all.\n");
            $this->markTestIncomplete("The creation of household failed. We can't test the get all.");
        }


        // Fake connection with a token for the user tester (ADMIN)
        $user = $this->getTestUser(self::USER_TESTER);
        $token = $this->getUserToken($user);
        $this->tokenStorage->setToken($token);

        $body = [
            "offset" => 0,
            "limit" => 1
        ];

        $crawler = $this->client->request('POST', '/api/wsse/households/get/all', $body, [], ['HTTP_COUNTRY' => 'KHM']);
        $listHousehold = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($this->client->getResponse()->isSuccessful());

        return true;
    }

    /**
     * @depends testGetAllHouseholds
     * @throws \Exception
     */
    public function testEditHousehold($isSuccess = true)
    {
        if (!$isSuccess)
        {
            print_r("\nThe get all of household failed. We can't test the update.\n");
            $this->markTestIncomplete("The get all of household failed. We can't test the update.");
        }

        $this->em->clear();
        $household = $this->em->getRepository(Household::class)->findOneBy([
            "addressStreet" => $this->body["address_street"],
            "addressNumber" => $this->body["address_number"],
            "addressPostcode" => $this->body["address_postcode"],
        ]);
        if (!$household instanceof Household)
            $this->fail("ISSUE : This test must be executed after the createTest");

        $user = $this->getTestUser(self::USER_TESTER);
        $token = $this->getUserToken($user);
        $this->tokenStorage->setToken($token);

        $this->em->clear();

        $projects = $this->em->getRepository(Project::class)->findAll();
        if (empty($projects))
        {
            print_r("There is no project inside your database");
            return false;
        }

        $this->body['address_street'] .= '(u)';

        foreach ($this->body['beneficiaries'] as $index => $beneficiaryArray)
        {
            $beneficiary = $this->em->getRepository(Beneficiary::class)
                ->findOneByGivenName($beneficiaryArray['given_name']);
            $this->body['beneficiaries'][$index]['id'] = $beneficiary->getId();

            foreach ($beneficiaryArray['phones'] as $index2 => $phoneArray)
            {
                $phone = $this->em->getRepository(Phone::class)
                    ->findOneByNumber($phoneArray['number']);
                $this->body['beneficiaries'][$index]['phones'][$index2]['id'] = $phone->getId();
            }

            foreach ($beneficiaryArray['national_ids'] as $index2 => $national_idArray)
            {
                $national_id = $this->em->getRepository(NationalId::class)
                    ->findOneByIdNumber($national_idArray['id_number']);
                $this->body['beneficiaries'][$index]['national_ids'][$index2]['id'] = $national_id->getId();
            }
        }

        $crawler = $this->client->request(
            'POST',
            '/api/wsse/households/' . $household->getId() . '/project/' . current($projects)->getId(),
            $this->body,
            [],
            ['HTTP_COUNTRY' => 'KHM']
        );
        $this->body['fullname'] = $this->namefullname;

        $household = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($this->client->getResponse()->isSuccessful());

        $this->em->clear();
        try
        {
            $this->assertArrayHasKey('id', $household);
            $this->assertArrayHasKey('address_street', $household);
            $this->assertArrayHasKey('address_number', $household);
            $this->assertArrayHasKey('address_postcode', $household);
            $this->assertArrayHasKey('livelihood', $household);
            $this->assertArrayHasKey('notes', $household);
            $this->assertArrayHasKey('latitude', $household);
            $this->assertArrayHasKey('longitude', $household);
            $this->assertArrayHasKey('location', $household);
            $this->assertArrayHasKey('country_specific_answers', $household);
            $this->assertArrayHasKey('beneficiaries', $household);
            $location = $household["location"];
            $this->assertArrayHasKey('country_iso3', $location);
            $this->assertArrayHasKey('adm1', $location);
            $this->assertArrayHasKey('adm2', $location);
            $this->assertArrayHasKey('adm3', $location);
            $this->assertArrayHasKey('adm4', $location);
            $country_specific_answer = current($household["country_specific_answers"]);
            $this->assertArrayHasKey('answer', $country_specific_answer);
            $this->assertArrayHasKey('country_specific', $country_specific_answer);
            $beneficiary = current($household["beneficiaries"]);
            $this->assertArrayHasKey('given_name', $beneficiary);
            $this->assertArrayHasKey('family_name', $beneficiary);
            $this->assertArrayHasKey('gender', $beneficiary);
            $this->assertArrayHasKey('status', $beneficiary);
            $this->assertArrayHasKey('date_of_birth', $beneficiary);
            $this->assertArrayHasKey('updated_on', $beneficiary);
            $this->assertArrayHasKey('profile', $beneficiary);
            $this->assertArrayHasKey('vulnerability_criteria', $beneficiary);
            $this->assertArrayHasKey('phones', $beneficiary);
            $this->assertArrayHasKey('national_ids', $beneficiary);
            $profile = $beneficiary["profile"];
            $this->assertArrayHasKey('photo', $profile);
            $vulnerability_criterion = current($beneficiary["vulnerability_criteria"]);
            $this->assertArrayHasKey('id', $vulnerability_criterion);
            $phone = current($beneficiary["phones"]);
            $this->assertArrayHasKey('number', $phone);
            $this->assertArrayHasKey('type', $phone);
            $national_ids = current($beneficiary["national_ids"]);
            $this->assertArrayHasKey('id_number', $national_ids);
            $this->assertArrayHasKey('id_type', $national_ids);

            $this->assertSame($household['address_street'], $this->namefullname . '(u)');
        }
        catch (\Exception $exception)
        {
            $this->remove($this->namefullname . "(u)");
            $this->fail("\nThe mapping of fields of Household entity is not correct (2).\n");
            return false;
        }

        return true;
    }

    /**
     * @depends testEditHousehold
     * @throws \Exception
     */
    public function testGetHouseholds($isSuccess)
    {
        if (!$isSuccess)
        {
            print_r("\nThe edition of household failed. We can't test the update.\n");
            $this->markTestIncomplete("The edition of household failed. We can't test the update.");
        }

        // Log a user in order to go through the security firewall
        $user = $this->getTestUser(self::USER_TESTER);
        $token = $this->getUserToken($user);
        $this->tokenStorage->setToken($token);

        $crawler = $this->client->request('POST', '/api/wsse/households/get/all', [], [], ['HTTP_COUNTRY' => 'COUNTRY_TEST']);
        $households = json_decode($this->client->getResponse()->getContent(), true);

        if (!empty($households))
        {
            $household = current($households);
            try
            {
                $this->assertArrayHasKey('id', $household);
                $this->assertArrayHasKey('location', $household);
                $this->assertArrayHasKey('beneficiaries', $household);
                $location = $household["location"];
                $this->assertArrayHasKey('country_iso3', $location);
                $this->assertArrayHasKey('adm1', $location);
                $this->assertArrayHasKey('adm2', $location);
                $this->assertArrayHasKey('adm3', $location);
                $this->assertArrayHasKey('adm4', $location);
                $beneficiary = current($household["beneficiaries"]);
                $this->assertArrayHasKey('given_name', $beneficiary);
                $this->assertArrayHasKey('family_name', $beneficiary);
                $this->assertArrayHasKey('vulnerability_criteria', $beneficiary);
                $this->assertSame("FIRSTNAME_TEST", $beneficiary["given_name"]);
                $vulnerability_criterion = current($beneficiary["vulnerability_criteria"]);
                $this->assertArrayHasKey('id', $vulnerability_criterion);
                $this->assertArrayHasKey('value', $vulnerability_criterion);
            }
            catch (\Exception $exception)
            {
                $this->remove($this->namefullname . '(u)');
                $this->fail("\nThe mapping of fields of Household entity is not correct (3).\n");
                return false;
            }
        }
        else
        {
            $this->remove($this->namefullname);
            $this->markTestIncomplete("You currently don't have any household in your database.");
        }

        return $this->remove($this->namefullname . '(u)');
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