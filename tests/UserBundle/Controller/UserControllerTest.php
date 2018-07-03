<?php


namespace Tests\UserBundle\Controller;


use Symfony\Component\BrowserKit\Client;
use Tests\BMSServiceTestCase;
use UserBundle\Entity\User;

class UserControllerTest extends BMSServiceTestCase
{

    /** @var Client $client */
    private $client;
    /** @var string $username */
    private $username = "TESTER_PHPUNIT";


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
    public function testGetUsers()
    {
        // Log a user in order to go through the security firewall
        $user = $this->getTestUser(self::USER_TESTER);
        $token = $this->getUserToken($user);
        $this->tokenStorage->setToken($token);

        $crawler = $this->client->request('GET', '/api/wsse/users');
        $users = json_decode($this->client->getResponse()->getContent(), true);

        if (!empty($users))
        {
            $user = $users[0];

            $this->assertArrayHasKey('id', $user);
            $this->assertArrayHasKey('username', $user);
            $this->assertArrayHasKey('email', $user);
            $this->assertArrayHasKey('roles', $user);
            $this->assertArrayHasKey('countries', $user);
            $this->assertArrayHasKey('user_projects', $user);
        }
        else
        {
            $this->markTestIncomplete("You currently don't have any user in your database.");
        }
    }

    /**
     * @throws \Exception
     */
    public function testGetSalt()
    {
        $crawler = $this->client->request('GET', '/api/wsse/salt/' . $this->username);
        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('user_id', $data);
        $this->assertArrayHasKey('salt', $data);

        $crawler = $this->client->request('GET', '/api/wsse/salt/o');
        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertTrue(!$this->client->getResponse()->isSuccessful());
    }

    /**
     * @throws \Exception
     */
    public function testCreateUser()
    {
        // First step
        // Get salt for a new user => save the username with the salt in database (user disabled for now)
        $return = $this->container->get('user.user_service')->getSalt($this->username);
        // Check if the first step has been done correctly
        $this->assertArrayHasKey('user_id', $return);
        $this->assertArrayHasKey('salt', $return);

        $body = [
            "username" => $this->username,
            "email" => $this->username . "@gmail.com",
            "password" => "PSWUNITTEST"
        ];

        // Fake connection with a token for the user tester (ADMIN)
        $user = $this->getTestUser(self::USER_TESTER);
        $token = $this->getUserToken($user);
        $this->tokenStorage->setToken($token);

        // Second step
        // Create the user with the email and the salted password. The user should be enable
        $crawler = $this->client->request('PUT', '/api/wsse/users', $body);
        $user = json_decode($this->client->getResponse()->getContent(), true);
        // Check if the second step succeed
        $this->assertTrue($this->client->getResponse()->isSuccessful());
        $this->assertArrayHasKey('id', $user);
        $this->assertArrayHasKey('username', $user);
        $this->assertArrayHasKey('email', $user);
        $this->assertSame($user['email'], $this->username . "@gmail.com");

        return $user;
    }

    /**
     * @depends testCreateUser
     * @throws \Exception
     */
    public function testEditUser($newuser)
    {
        $roles = ["ROLE_TEST", "ROLE_USER"];

        $body = ["roles" => $roles];

        $user = $this->getTestUser(self::USER_TESTER);
        $token = $this->getUserToken($user);
        $this->tokenStorage->setToken($token);

        $crawler = $this->client->request('POST', '/api/wsse/users/' . $newuser['id'], $body);
        $newUserReceived = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertTrue($this->client->getResponse()->isSuccessful());

        $this->em->clear();

        $userSearch = $this->em->getRepository(User::class)->find($newUserReceived['id']);
        $this->assertEquals($userSearch->getRoles(), $roles);

        return $newUserReceived;
    }

    /**
     * @depends testEditUser
     * @param $userToChange
     * @return mixed
     * @throws \Doctrine\Common\Persistence\Mapping\MappingException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function testChangePassword($userToChange)
    {
        $user = $this->getTestUser(self::USER_TESTER);
        $token = $this->getUserToken($user);
        $this->tokenStorage->setToken($token);

        $body = ["oldPassword" => "PSWUNITTEST", "newPassword" => "PSWUNITTEST1"];

        $crawler = $this->client->request('POST', '/api/wsse/users/' . $userToChange['id'] . '/password', $body);
        $newUserReceived = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertTrue($this->client->getResponse()->isSuccessful());

        $this->em->clear();

        $userSearch = $this->em->getRepository(User::class)->find($userToChange['id']);
        $this->assertSame($userSearch->getPassword(), "PSWUNITTEST1");

        return $newUserReceived;
    }

    /**
     * @depends testEditUser
     *
     * @param $userToDelete
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function testDelete($userToDelete)
    {
        // Fake connection with a token for the user tester (ADMIN)
        $user = $this->getTestUser(self::USER_TESTER);
        $token = $this->getUserToken($user);
        $this->tokenStorage->setToken($token);

        // Second step
        // Create the user with the email and the salted password. The user should be enable
        $crawler = $this->client->request('DELETE', '/api/wsse/users/' . $userToDelete['id']);
        $success = json_decode($this->client->getResponse()->getContent(), true);

        // Check if the second step succeed
        $this->assertTrue($this->client->getResponse()->isSuccessful());
        $this->assertTrue($success);
    }
}