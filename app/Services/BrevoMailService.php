<?php

namespace App\Services;

use GuzzleHttp\Client;

class BrevoMailService
{
    protected $apiKey;
    protected $client;

    public function __construct()
    {
        $this->apiKey = config('services.brevo.api_key');
        $this->client = new Client([
            'base_uri' => 'https://api.brevo.com/v3/',
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Api-Key' => $this->apiKey,
            ],
        ]);
    }

    public function send($toEmail, $toName, $subject, $htmlContent)
    {
        $response = $this->client->post('smtp/email', [
            'json' => [
                'sender' => [
                    'name' => 'SweetLens',
                    'email' => 'sweetlensp@gmail.com',
                ],
                'to' => [['email' => $toEmail, 'name' => $toName]],
                'subject' => $subject,
                'htmlContent' => nl2br($htmlContent),
            ],
        ]);

        return $response->getStatusCode() === 201;
    }
}