<?php

/*
 *
 * Juno é uma solução para emissão de cobranças
 * Para usar, é necessário ter um cadastro, gerar um token de integração e solicitar um clientId e secret.
 * Acesse e confira: https: //juno.com.br/
 * Documentação: https: //dev.juno.com.br/api/v2
 * Criado por: Henrique Bispo dos Santos
 *
 */

namespace Juno;

class Juno
{
    private $token;
    private $clientId;
    private $clientSecret;
    private $sandbox;
    private $authorizationToken;

    const PROD_URL = "https://api.juno.com.br/";
    const SANDBOX_URL = "https://sandbox.boletobancario.com/api-integration/";

    const AUTH_PROD_URL = "https://api.juno.com.br/authorization-server/";
    const AUTH_SANDBOX_URL = "https://sandbox.boletobancario.com/authorization-server/";

    public function __construct($token, $clientId, $clientSecret, $sandbox = false)
    {
        $this->token = $token;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->sandbox = $sandbox;
        $this->authorizationToken = $this->fetchAuthorizationToken();
    }

    private function fetchAuthorizationToken()
    {
        $headers = [
            'Authorization: Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret),
        ];

        $requestData = [
            'grant_type' => 'client_credentials',
        ];

        $result = json_decode($this->request('oauth/token', $requestData, $headers, ($this->sandbox ? Juno::AUTH_SANDBOX_URL : Juno::AUTH_PROD_URL), false), true);

        if (!array_key_exists('access_token', $result)) {
            throw new \Exception('Acesso não autorizado, confira os dados configurados em Serviços Externos -> Integração Juno');
        }

        return $result['access_token'];
    }

    public function createWebhook($url, $eventTypes)
    {
        $requestData = array(
            'url' => $url,
            'eventTypes' => $eventTypes,
        );

        return $this->request("notifications/webhooks", $requestData);
    }

    public function fetchWebhooks()
    {
        return $this->request("notifications/webhooks");
    }

    public function deleteWebhook($id)
    {
        return $this->request("notifications/webhooks/$id");
    }

    public function createCharge($payerName, $payerCpfCnpj, $payerEmail, $payerPhone, $payerBirthDate, $description, $amount, $dueDate, $maxOverdueDays, $fine, $interest, $discountAmount, $discountDays)
    {
        $requestData = [
            'charge' => [
                'description' => $description,
                'amount' => $amount,
                'dueDate' => $dueDate,
                'maxOverdueDays' => $maxOverdueDays,
                'fine' => $fine,
                'interest' => $interest,
            ],
            'billing' => [
                'name' => $payerName,
                'document' => $payerCpfCnpj,
                'email' => $payerEmail,
                'phone' => $payerPhone,
                'birthDate' => $payerBirthDate,
            ],
        ];

        if ($discountAmount > 0) {
            $requestData['charge']['discountAmount'] = $discountAmount;
            $requestData['charge']['discountDays'] = $discountDays;
        }

        return $this->request("charges", $requestData);
    }

    public function fetchCharge($id)
    {
        return $this->request("charges/$id");
    }

    public function fetchPaymentDetails($paymentToken)
    {
        $requestData = array(
            'paymentToken' => $paymentToken,
        );

        return $this->request("fetch-payment-details", $requestData);
    }

    public function fetchBalance()
    {
        $requestData = array(
            'token' => $this->token,
        );

        return $this->request("fetch-balance", $requestData);
    }

    public function requestTransfer()
    {
        $requestData = array(
            'token' => $this->token,
        );

        return $this->request("request-transfer", $requestData);
    }

    public function cancelCharge($id)
    {
        return $this->request("charges/$id/cancelation");
    }

    private function request($urlSufix, $data = [], $headers = [], $url = '', $json = true)
    {
        $method = (empty($data) ? (substr($urlSufix, -11) == 'cancelation' ? "PUT" : "GET") : "POST");
        $data = ($json ? json_encode($data) : http_build_query($data));

        if (empty($headers)) {
            $headers = [
                'Authorization: Bearer ' . $this->authorizationToken,
                'X-Api-Version: 2',
                'X-Resource-Token: ' . $this->token,
            ];
        }

        if ($json) {
            $headers[] = 'Content-Type: application/json';
            $headers[] = 'Content-Length: ' . strlen($data);
        }

        if (empty($url)) {
            $url = ($this->sandbox ? Juno::SANDBOX_URL : Juno::PROD_URL);
        }

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url . $urlSufix,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "UTF-8",
            CURLOPT_MAXREDIRS => 2,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }
}
