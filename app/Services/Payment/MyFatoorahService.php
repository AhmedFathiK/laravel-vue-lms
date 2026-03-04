<?php

namespace App\Services\Payment;

use App\Models\Setting;
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

        // 1. Try to get Config (ENV)
        $baseUrl = config('services.myfatoorah.base_url');

        // 2. If empty, use hardcoded default
        if (empty($baseUrl)) {
            $baseUrl = 'https://apitest.myfatoorah.com';
        }

        $this->baseUrl = rtrim($baseUrl, '/');

        $this->apiKey = (string) Setting::get('payment_myfatoorah_api_key', config('services.myfatoorah.api_key', ''));

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
        ?string $paymentMethodId = null,
        ?array $items = null
    ): array {
        $currency = Currency::normalize($currency);

        $customerName = (string) ($customer['name'] ?? 'Guest');
        $customerEmail = (string) ($customer['email'] ?? 'guest@example.com');
        $customerReference = (string) ($metadata['customer_reference'] ?? '');

        $payload = [
            'PaymentMethodId' => $paymentMethodId,
            'InvoiceValue' => $amount,
            'DisplayCurrencyIso' => $currency,
            'CustomerName' => $customerName,
            'CustomerEmail' => $customerEmail,
            'CallBackUrl' => $callbackUrl,
            'ErrorUrl' => $errorUrl,
            'CustomerReference' => $customerReference,
            'Language' => 'en',
            'ExpiryDate' => now()->addDays(7)->toIso8601String(), // Extend expiry to 7 days
            'SaveToken' => true,
        ];

        if (!empty($items)) {
            $payload['InvoiceItems'] = array_map(function ($item) {
                return [
                    'ItemName' => $item['name'] ?? 'Item',
                    'Quantity' => $item['quantity'] ?? 1,
                    'UnitPrice' => $item['price'] ?? 0,
                ];
            }, $items);

            // Also set UserDefinedField and Comments for better visibility on some MyFatoorah templates
            $payload['UserDefinedField'] = $items[0]['name'] ?? '';
            // Some versions of MyFatoorah display Comments prominently
            $payload['Comments'] = $items[0]['name'] ?? '';
        }

        $data = $this->executePayment($payload);

        return [
            'payment_url' => (string) ($data['PaymentURL'] ?? ''),
            'transaction_id' => (string) ($data['InvoiceId'] ?? ''),
            'gateway_data' => $data,
        ];
    }

    public function getPaymentMethods(float $amount, string $currency, bool $filter = true): array
    {
        $currency = Currency::normalize($currency);

        $payload = [
            'InvoiceAmount' => $amount,
            'CurrencyIso' => $currency,
        ];

        $response = $this->getRequest()->post($this->baseUrl . '/v2/InitiatePayment', $payload);
        $data = $this->handleResponse($response);

        $methods = $data['PaymentMethods'] ?? [];

        // Filter based on allowed methods in settings
        if ($filter) {
            $allowedMethodsJson = Setting::get('myfatoorah_allowed_methods');
            $allowedMethods = $allowedMethodsJson ? json_decode($allowedMethodsJson, true) : null;

            if ($allowedMethods && is_array($allowedMethods)) {
                $methods = array_filter($methods, function ($method) use ($allowedMethods) {
                    return in_array((string) ($method['PaymentMethodId'] ?? ''), array_map('strval', $allowedMethods));
                });

                // Re-index after filtering
                $methods = array_values($methods);
            }
        }

        return array_map(function ($method) {
            return [
                'id' => (string) ($method['PaymentMethodId'] ?? ''),
                'name' => (string) ($method['PaymentMethodEn'] ?? ''),
                'image' => (string) ($method['ImageUrl'] ?? null),
            ];
        }, $methods);
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

    public function chargeToken(
        string $token,
        float $amount,
        string $currency,
        array $customer,
        array $metadata
    ): array {
        $currency = Currency::normalize($currency);

        $payload = [
            'Token' => $token,
            'InvoiceValue' => $amount,
            'DisplayCurrencyIso' => $currency,
            'CustomerName' => $customer['name'] ?? 'Guest',
            'CustomerEmail' => $customer['email'] ?? 'guest@example.com',
            'CustomerReference' => $metadata['customer_reference'] ?? '',
        ];

        try {
            Log::info('MyFatoorah Token Charge Request:', [
                'url' => $this->baseUrl . '/v2/ExecutePayment',
                'payload' => $payload,
            ]);

            $response = $this->getRequest()->post($this->baseUrl . '/v2/ExecutePayment', $payload);
            $data = $this->handleResponse($response);

            // For token charge, MyFatoorah returns the status directly if successful
            // We need to fetch the payment status to be sure
            $paymentId = (string) ($data['InvoiceId'] ?? '');
            $statusData = $this->getPaymentStatus($paymentId);

            return [
                'status' => $statusData['status'],
                'transaction_id' => $paymentId,
                'gateway_data' => array_merge($data, ['status_data' => $statusData['gateway_data']]),
            ];
        } catch (\Exception $e) {
            Log::error('MyFatoorah Token Charge Failed: ' . $e->getMessage());
            throw $e;
        }
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
        Log::info('MyFatoorah Request:', [
            'url' => $this->baseUrl . '/v2/ExecutePayment',
            'payload' => $data,
        ]);

        $response = $this->getRequest()->post($this->baseUrl . '/v2/ExecutePayment', $data);

        Log::info('MyFatoorah Response:', [
            'status' => $response->status(),
            'body' => $response->json(),
        ]);

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
