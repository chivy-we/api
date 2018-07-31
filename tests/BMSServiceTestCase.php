<?php


namespace Tests;


use BeneficiaryBundle\Entity\CountrySpecific;
use BeneficiaryBundle\Entity\VulnerabilityCriterion;
use Doctrine\ORM\EntityManager;
use ProjectBundle\Entity\Project;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\BrowserKit\Client;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Serializer\SerializerInterface;
use UserBundle\Entity\User;
use UserBundle\Security\Authentication\Token\WsseUserToken;


class BMSServiceTestCase extends KernelTestCase
{

    /** @var Client $client */
    protected $client;
    const USER_PHPUNIT = 'phpunit';
    const USER_TESTER = 'tester';

    // SERVICES

    /** @var EntityManager $em */
    protected $em;

    /** @var Container $container */
    protected $container;

    /** @var $serializer */
    protected $serializer;

    protected $tokenStorage;

    protected $bodyHousehold = [
        "project" => 1,
        "address_street" => "STREET_TEST",
        "address_number" => "NUMBER_TEST",
        "address_postcode" => "POSTCODE_TEST",
        "livelihood" => 10,
        "notes" => "NOTES_TEST",
        "latitude" => "1.1544",
        "longitude" => "120.12",
        "location" => [
            "country_iso3" => "FRA",
            "adm1" => "Rhone-Alpes",
            "adm2" => "Savoie",
            "adm3" => "Chambery",
            "adm4" => "Sainte Hélène sur Isère"
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
     * @var $defaultSeralizerName
     * If you plan to use another serializer, use the setter before calling this setUp Method in the child class setUp method.
     * Ex :
     * function setUp(){
     *      $this->setDefaultSerializerName("jms_serializer");
     *      parent::setUp();
     * }
     */
    private $defaultSerializerName = "serializer";


    public function setDefaultSerializerName($serializerName)
    {
        $this->defaultSerializerName = $serializerName;
        return $this;
    }


    public function setUpFunctionnal()
    {

        self::bootKernel();

        $this->container = static::$kernel->getContainer();

        //Preparing the EntityManager
        $this->em = $this->container
            ->get('doctrine')
            ->getManager();

        //Mocking Serializer, Container
        $this->serializer = $this->container
            ->get($this->defaultSerializerName);

        //setting the token_storage
        $this->tokenStorage = $this->container->get('security.token_storage');

    }


    public function setUpUnitTest()
    {
        //EntityManager mocking
        $this->mockEntityManager(['getRepository']);
        //Serializer mocking
        $this->mockSerializer();
        //Container mocking
        $this->mockContainer();

    }

    /**
     * {@inheritDoc}
     */
    protected function tearDown()
    {
        //parent::tearDown();
        if (!empty($this->em))
        {
            //$this->em->close();
            unset($this->em);
            $this->em = null; // avoid memory leaks
        }
    }

    /**
     * Mock the EntityManager with the given functions
     * @param  array $requiredFunctions [names of functions to setup on the mock]
     * @return EntityManager {[MockClass]       [a mock instance of EntityManager]
     */
    protected function mockEntityManager(array $requiredFunctions)
    {
        $this->em = $this->getMockBuilder(EntityManager::class)
            ->setMethods($requiredFunctions)
            ->disableOriginalConstructor()
            ->getMock();
        return $this->em;
    }

    protected function mockSerializer()
    {
        $this->serializer = $this->getMockBuilder(SerializerInterface::class)
            ->setMethods(['serialize', 'deserialize'])
            ->disableOriginalConstructor()
            ->getMock();
        return $this->serializer;
    }

    protected function mockRepository($repositoryClass, array $requiredFunctions)
    {
        return $this->getMockBuilder($repositoryClass)
            ->disableOriginalConstructor()
            ->setMethods($requiredFunctions)
            ->getMock();
    }

    protected function mockContainer()
    {
        $this->container = $this->getMockBuilder(Container::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->container->method('get')
            ->with($this->defaultSerializerName)
            ->will($this->returnValue($this->serializer));
        return $this->container;
    }

    protected function getUserToken(User $user)
    {
        $token = new WsseUserToken($user->getRoles());
        $token->setUser($user);

        return $token;
    }

    /**
     * Require Functional tests and real Entity Manager
     * @param string $username
     * @return null|object|User {[type] [description]
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    protected function getTestUser(string $username)
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['username' => $username]);

        if ($user instanceOf User)
        {
            return $user;
        }

        $user = new User();
        $user->setUsername($username)
            ->setEmail($username)
            ->setPassword("");
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    /**
     * @return bool|mixed
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function createHousehold()
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

        $vulnerabilityCriterion = $this->em->getRepository(VulnerabilityCriterion::class)->findOneBy([
            "fieldString" => "disabled"
        ]);
        $beneficiaries = $this->bodyHousehold["beneficiaries"];
        $vulnerabilityId = $vulnerabilityCriterion->getId();
        foreach ($beneficiaries as $index => $b)
        {
            $this->bodyHousehold["beneficiaries"][$index]["vulnerability_criteria"] = [["id" => $vulnerabilityId]];
        }

        $countrySpecific = $this->em->getRepository(CountrySpecific::class)->findOneBy([
            "fieldString" => 'ID Poor',
            "type" => 'Number',
            "countryIso3" => 'FRA'
        ]);
        $country_specific_answers = $this->bodyHousehold["country_specific_answers"];
        $countrySpecificId = $countrySpecific->getId();
        foreach ($country_specific_answers as $index => $c)
        {
            $this->bodyHousehold["country_specific_answers"][$index]["country_specific"] = ["id" => $countrySpecificId];
        }

        $crawler = $this->client->request(
            'PUT',
            '/api/wsse/households/project/' . current($projects)->getId(),
            $this->bodyHousehold,
            [],
            ['HTTP_COUNTRY' => 'COUNTRY_TEST']
        );
        $household = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($this->client->getResponse()->isSuccessful());

        return $household;
    }

}