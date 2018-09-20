<?php

namespace TransactionBundle\Utils;

use Doctrine\ORM\EntityManagerInterface;
use BeneficiaryBundle\Entity\Beneficiary;

class KHMFinancialProvider implements DefaultFinancialProvider {

    /** @var EntityManagerInterface $em */
    private $em;
    
    private $url = "https://stageonline.wingmoney.com:8443/RestEngine";
    private $token;
    private $lastTokenDate;

    /**
     * DefaultFinancialProvider constructor.
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->em = $entityManager;
    }

    /**
     * Get token to connect to API
     * @return object token
     * @throws \Exception
     */
    public function getToken()
    {
        $route = "/oauth/token";
        $body = array(
            "username"      => "thirdParty",
            "password"      => "ba0228f6e48ba7942d79e2b44e6072ee",
            "grant_type"    => "password",
            "client_id"     => "third_party",
            "client_secret" => "16681c9ff419d8ecc7cfe479eb02a7a",
            "scope"         => "trust"
        );
        
        try {
            $this->token = $this->sendRequest("POST", $route, $body);
            $this->lastTokenDate = new \Date();
            return $this->token;
        } catch (Exception $e) {
            throw $e;
        }
        
    }
    
    /**
     * Send money to beneficiaries
     * @param  Beneficiary  $beneficiary
     * @return object       transaction
     */
    public function sendMoneyToOne(string $phone_number = "0962620581")
    {
        $route = "/api/v1/sendmoney/nowing/commit";
        $body = array(
            "amount"          => 50,
            "sender_msisdn"   => "012249184",
            "receiver_msisdn" => $phone_number
        );
        
        try {
            $sent = $this->sendRequest("POST", $route, $body);
            return $sent;
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Send request to WING API for Cambodia
     * @param  string $type    type of the request ("GET", "POST", etc.)
     * @param  string $route   url of the request
     * @param  array  $headers headers of the request (optional)
     * @param  array  $body    body of the request (optional)
     * @return mixed  response
     * @throws \Exception
     */
    public function sendRequest(string $type, string $route, array $body = array()) {
        $curl = curl_init();
        
        $headers = array();
        
        if(!preg_match('/\/oauth\/token/', $route)) {
            if (new \Date - $this->lastTokenDate > $this->token->expires_in) {
                $this->getToken();
            }
            array_push($headers, "Authorization: Bearer " . $this->token->access_token);
        }
        
        curl_setopt_array($curl, array(
          CURLOPT_PORT           => "8443",
          CURLOPT_URL            => $this->url . $route,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING       => "",
          CURLOPT_MAXREDIRS      => 10,
          CURLOPT_TIMEOUT        => 30,
          CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST  => $type,
          CURLOPT_POSTFIELDS     => http_build_query($body),
          CURLOPT_HTTPHEADER     => $headers,
          CURLOPT_FAILONERROR    => true
        ));
        
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        
        if ($err) {
            throw new \Exception($err);
        } else {
            $result = json_decode($response);
            return $result;
        }
    }

}