<?php

/**
 * The SOAP client to authenticate against Atlassian Crowd.
 *
 * @link       https://www.auderset.dev
 * @since      1.0.0
 *
 * @package    Crowd
 * @subpackage Crowd/admin
 */

/**
 * Atlassian Crowd SOAP Client
 *
 * Provides a SOAP client for to authenticate against Atlassian Crowd.
 *
 * @package    Crowd
 * @subpackage Crowd/admin
 * @author     Florian Auderset <florian@auderset.dev>
 */
class Crowd_Client
{

    /**
     * The soap client.
     *
     * @since    1.0.0
     * @access   private
     * @var      SoapClient $crowd_login_soap_client The SOAP client.
     */
    private $crowd_login_soap_client;

    /**
     * The soap client configuration.
     *
     * @since    1.0.0
     * @access   private
     * @var      array $crowd_login_configuration The SOAP client configuration.
     */
    private $crowd_login_configuration;

    /**
     * The soap client token.
     *
     * @since    1.0.0
     * @access   private
     * @var      string $crowd_login_app_token The SOAP client token.
     */
    private $crowd_login_app_token;

    /**
     * Crowd_Client constructor.
     *
     * @since    1.0.0
     * @throws Crowd_Connection_Exception
     */
    public function __construct()
    {
        $this->crowd_login_configuration = get_option('crowd_login_option_name');
        $service_url = $this->crowd_login_configuration['crowd_url'] . '/services/' . 'SecurityServer?wsdl';

        try {
            $this->crowd_login_soap_client = new SoapClient($service_url);
        } catch (SoapFault $soapFault) {
            $code = $soapFault->getCode();
            $message = $soapFault->getMessage();
            throw new Crowd_Connection_Exception($message, $code);
        }
    }

    /**
     * Authenticates the application against Atlassian Crowd server.
     *
     * @since    1.0.0
     * @return string
     * @throws Crowd_Login_Exception
     */
    public function authenticateApplication()
    {
        $params = [
            'in0' => [
                'credential' => [
                    'credential' => $this->crowd_login_configuration['crowd_application_password']
                ],
                'name' => $this->crowd_login_configuration['crowd_application_name']
            ]
        ];

        try {
            $response = $this->crowd_login_soap_client->authenticateApplication($params);
        } catch (SoapFault $soapFault) {
            $code = $soapFault->getCode();
            $message = $soapFault->getMessage();
            echo "SOAP Fault: faultcode: {$code}, faultstring: {$message}";
        }

        $this->crowd_login_app_token = $response->out->token;

        if (empty($this->crowd_login_app_token)) {
            throw new Crowd_Login_Exception("Unable to login to Crowd. Please check your credentials.");
        } else {
            return $this->crowd_login_app_token;
        }
    }

    /**
     * Authenticates a principal to the Atlassian Crowd server.
     *
     * @since    1.0.0
     * @param $name
     * @param $credential
     * @param $user_agent
     * @param $remote_address
     * @return |null
     */
    public function authenticatePrincipal($name, $credential, $user_agent, $remote_address)
    {
        $params = [
            'in0' => [
                'name' => $this->crowd_login_configuration['crowd_application_name'],
                'token' => $this->crowd_login_app_token
            ],
            'in1' => [
                'application' => $this->crowd_login_configuration['crowd_application_name'],
                'credential' => ['credential' => $credential],
                'name' => $name,
                'validationFactors' => [
                    [
                        'name' => 'User-Agent',
                        'value' => $user_agent
                    ],
                    [
                        'name' => 'remote_address',
                        'value' => $remote_address
                    ]
                ]
            ]
        ];

        try {
            $response = $this->crowd_login_soap_client->authenticatePrincipal($params);
        } catch (SoapFault $soapFault) {
            $code = $soapFault->getCode();
            $message = $soapFault->getMessage();
            echo "SOAP Fault: faultcode: {$code}, faultstring: {$message}";
            return null;
        }

        $principal_token = $response->out;

        return $principal_token;
    }

    /**
     * Determine if the current token is still valid in Atlassian Crowd server.
     *
     * @since    1.0.0
     * @param $principal_token
     * @param $user_agent
     * @param $remote_address
     * @return string
     */
    public function isValidPrincipalToken($principal_token, $user_agent, $remote_address)
    {
        $params = [
            'in0' => [
                'name' => $this->crowd_login_configuration['crowd_application_name'],
                'token' => $this->crowd_login_app_token
            ],
            'in1' => $principal_token,
            'in2' => [
                [
                    'name' => 'User-Agent',
                    'value' => $user_agent
                ],
                [
                    'name' => 'remote_address',
                    'value' => $remote_address
                ]
            ]
        ];

        try {
            $response = $this->crowd_login_soap_client->isValidPrincipalToken($params);
        } catch (SoapFault $soapFault) {
            $code = $soapFault->getCode();
            $message = $soapFault->getMessage();
            echo "SOAP Fault: faultcode: {$code}, faultstring: {$message}";
            return '';
        }

        $valid_token = $response->out;

        return $valid_token;
    }

    /**
     * Invalidates given principal token for all application clients in Atlassian Crowd server.
     *
     * @since    1.0.0
     * @param $principal_token
     * @return bool
     */
    public function invalidatePrincipalToken($principal_token)
    {
        $params = [
            'in0' => [
                'name' => $this->crowd_login_configuration['crowd_application_name'],
                'token' => $this->crowd_login_app_token
            ],
            'in1' => $principal_token
        ];

        try {
            $response = $this->crowd_login_soap_client->invalidatePrincipalToken($params);
            return true;
        } catch (SoapFault $soapFault) {
            $code = $soapFault->getCode();
            $message = $soapFault->getMessage();
            echo "SOAP Fault: faultcode: {$code}, faultstring: {$message}";
        }
        return false;
    }

    /**
     * Finds a principal by its token.
     *
     * @since    1.0.0
     * @param $principal_token
     * @return |null
     */
    public function findPrincipalByToken($principal_token)
    {
        $params = [
            'in0' => [
                'name' => $this->crowd_login_configuration['crowd_application_name'],
                'token' => $this->crowd_login_app_token
            ],
            'in1' => $principal_token
        ];

        try {
            $response = $this->crowd_login_soap_client->findPrincipalByToken($params);
            return $response->out;
        } catch (SoapFault $soapFault) {
            $code = $soapFault->getCode();
            $message = $soapFault->getMessage();
            echo "SOAP Fault: faultcode: {$code}, faultstring: {$message}";
            return null;
        }
    }

    /**
     * Returns array of groups of the given principal.
     *
     * @since    1.0.0
     * @param $principal_token
     * @return array
     */
    public function findGroupMemberships($principal_token)
    {
        $params = [
            'in0' => [
                'name' => $this->crowd_login_configuration['crowd_application_name'],
                'token' => $this->crowd_login_app_token
            ],
            'in1' => $principal_token
        ];

        try {
            $response = $this->crowd_login_soap_client->findGroupMemberships($params);
            if ($response->out == null){
                // No groups were found return empty array
                return [];
            } else {
                // Convert stdObject from api to array
                return json_decode(json_encode($response->out), true)['string'];
            }
        } catch (SoapFault $soapFault) {
            $code = $soapFault->getCode();
            $message = $soapFault->getMessage();
            echo "SOAP Fault: faultcode: {$code}, faultstring: {$message}";
            return null;
        }
    }
}