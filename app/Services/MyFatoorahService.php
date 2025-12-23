<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MyFatoorahService
{
    protected $baseUrl;
    protected $apiKey;
    protected $headers;

    public function __construct()
    {
        $this->baseUrl = config('myfatoorah.base_url');
        $this->apiKey = config('myfatoorah.api_key');

        $this->headers = [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Initiate Payment (Get Payment Methods)
     */
    public function initiatePayment($amount, $currency)
    {
        $response = Http::withHeaders($this->headers)
            ->post($this->baseUrl . '/v2/InitiatePayment', [
                'InvoiceAmount' => $amount,
                'CurrencyIso' => $currency,
            ]);

        return $this->handleResponse($response);
    }

    /**
     * Execute Payment (Create Invoice)
     */
    public function executePayment(array $data)
    {
        $response = Http::withHeaders($this->headers)
            ->post($this->baseUrl . '/v2/ExecutePayment', $data);

        return $this->handleResponse($response);
    }

    /**
     * Get Payment Status
     */
    public function getPaymentStatus($key, $keyType = 'PaymentId')
    {
        $response = Http::withHeaders($this->headers)
            ->post($this->baseUrl . '/v2/GetPaymentStatus', [
                'Key' => $key,
                'KeyType' => $keyType,
            ]);

        return $this->handleResponse($response);
    }

    /**
     * Handle API Response
     */
    protected function handleResponse($response)
    {
        if ($response->successful()) {
            $body = $response->json();
            if (isset($body['IsSuccess']) && $body['IsSuccess'] === true) {
                return $body['Data'];
            }

            throw new \Exception($body['Message'] ?? 'MyFatoorah API Error');
        }

        Log::error('MyFatoorah API Error: ' . $response->body());
        throw new \Exception('MyFatoorah Connection Error: ' . $response->status());
    }
}
