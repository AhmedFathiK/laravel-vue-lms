<?php

namespace App\Services\Payment;

use App\Services\Payment\Currency;
use App\Services\Payments\PaymentServiceInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * MyFatoorah payment gateway implementation.
 */
class MyFatoorahService implements PaymentServiceInterface
{
    private string $baseUrl;

    private string $apiKey;

    private array $headers;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.myfatoorah.base_url', ''), '/');
        $this->apiKey = (string) config('services.myfatoorah.api_key', '');

        $this->headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        if ($this->apiKey) {
            $this->headers['Authorization'] = $this->formatBearerAuthorizationHeader($this->apiKey);
        }

        Log::info('MyFatoorah Config:', [
            'url' => $this->baseUrl,
            'ssl_verify' => ! app()->isLocal(),
        ]);
    }

    public function createCheckout(
        float $amount,
        string $currency,
        array $customer,
        array $metadata,
        string $callbackUrl,
        string $errorUrl,
        ?string $paymentMethodId = null
    ): array {
        $currency = Currency::normalize($currency);

        $customerName = (string) ($customer['name'] ?? 'Guest');
        $customerEmail = (string) ($customer['email'] ?? 'guest@example.com');
        $customerReference = (string) ($metadata['customer_reference'] ?? '');

        $payload = [
            'PaymentMethodId' => $paymentMethodId ?? '2',
            'InvoiceValue' => $amount,
            'DisplayCurrencyIso' => $currency,
            'CustomerName' => $customerName,
            'CustomerEmail' => $customerEmail,
            'CallBackUrl' => $callbackUrl,
            'ErrorUrl' => $errorUrl,
            'CustomerReference' => $customerReference,
            'Language' => 'en',
        ];

        $data = $this->executePayment($payload);

        return [
            'payment_url' => (string) ($data['PaymentURL'] ?? ''),
            'transaction_id' => (string) ($data['InvoiceId'] ?? ''),
            'gateway_data' => $data,
        ];
    }

    public function getPaymentMethods(float $amount, string $currency): array
    {
        $currency = Currency::normalize($currency);

        $payload = [
            'InvoiceAmount' => $amount,
            'CurrencyIso' => $currency,
        ];

        $response = $this->getRequest()->post($this->baseUrl . '/v2/InitiatePayment', $payload);
        $data = $this->handleResponse($response);

        return $data['PaymentMethods'] ?? [];
    }

    public function getPaymentStatus(string $paymentId): array
    {
        $data = $this->fetchPaymentStatus($paymentId);
        $invoiceStatus = (string) ($data['InvoiceStatus'] ?? '');

        $normalizedStatus = match ($invoiceStatus) {
            'Paid' => 'paid',
            'Pending' => 'pending',
            default => 'failed',
        };

        return [
            'local_payment_id' => $data['CustomerReference'] ?? null,
            'status' => $normalizedStatus,
            'transaction_id' => $data['InvoiceId'] ?? null,
            'gateway_data' => $data,
        ];
    }

    public function gatewayKey(): string
    {
        return 'myfatoorah';
    }

    /**
     * Get the HTTP client with default headers and environment-specific settings.
     */
    protected function getRequest(): PendingRequest
    {
        if (empty($this->apiKey)) {
            throw new InvalidArgumentException('MyFatoorah API key is not configured.');
        }

        if (empty($this->baseUrl)) {
            throw new InvalidArgumentException('MyFatoorah base URL is not configured.');
        }

        $options = [
            'curl' => [
                CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
            ],
        ];

        if (app()->isLocal()) {
            $options['curl'][CURLOPT_SSL_CIPHER_LIST] = 'DEFAULT@SECLEVEL=1';
        }

        $request = Http::withHeaders($this->headers)
            ->withOptions($options);

        if (app()->isLocal()) {
            $request = $request->withoutVerifying();
        }

        return $request;
    }

    /**
     * Handle and normalize MyFatoorah responses.
     *
     * @return array<string, mixed>
     */
    protected function handleResponse(Response $response): array
    {
        if (! $response->successful()) {
            Log::error('MyFatoorah API Error: ' . $response->body());
            throw new \Exception('MyFatoorah Connection Error: ' . $response->status());
        }

        $body = $response->json();
        if (! is_array($body)) {
            throw new \Exception('MyFatoorah API Error: Invalid JSON response');
        }

        if (($body['IsSuccess'] ?? null) === true) {
            return (array) ($body['Data'] ?? []);
        }

        throw new \Exception((string) ($body['Message'] ?? 'MyFatoorah API Error'));
    }

    /**
     * Execute a payment (create invoice) and return MyFatoorah Data.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function executePayment(array $data): array
    {
        $response = $this->getRequest()->post($this->baseUrl . '/v2/ExecutePayment', $data);

        return $this->handleResponse($response);
    }

    /**
     * Get payment status from MyFatoorah.
     *
     * @return array<string, mixed>
     */
    protected function fetchPaymentStatus(string $paymentId): array
    {
        $response = $this->getRequest()->post($this->baseUrl . '/v2/GetPaymentStatus', [
            'Key' => $paymentId,
            'KeyType' => 'PaymentId',
        ]);

        return $this->handleResponse($response);
    }

    private function formatBearerAuthorizationHeader(string $apiKey): string
    {
        $token = trim($apiKey);
        if ($token === '') {
            throw new InvalidArgumentException('MyFatoorah API key is not configured.');
        }

        if (stripos($token, 'bearer ') === 0) {
            $token = substr($token, 7);
            $token = ltrim($token);
        }

        return 'Bearer ' . $token;
    }
}
