<?php

namespace App\Services\Payment;

use App\Models\Setting;
use App\Services\Payments\PaymentServiceInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymobService implements PaymentServiceInterface
{
    private string $secretKey;
    private string $publicKey;
    private string $apiKey;
    private string $baseUrl = 'https://accept.paymob.com/v1';
    private string $authBaseUrl = 'https://accept.paymob.com/api';

    public function __construct()
    {
        $this->secretKey = Setting::get('paymob_secret_key', config('services.paymob.secret_key'));
        $this->publicKey = Setting::get('paymob_public_key', config('services.paymob.public_key'));
        $this->apiKey = Setting::get('paymob_api_key', config('services.paymob.api_key'));
    }

    /**
     * Get the HTTP client with environment-specific settings.
     */
    protected function getRequest(): PendingRequest
    {
        $request = Http::asJson()->acceptJson();

        // Check environment or test mode setting
        if (app()->isLocal() || config('services.paymob.test_mode', false)) {
            $request->withoutVerifying();
        }

        return $request;
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
        try {
            // Get Integration ID from the selected payment method, or use the first available one
            $integrationId = $paymentMethodId ?? $this->getDefaultIntegrationId();

            if (!$integrationId) {
                throw new \Exception('No Paymob Integration ID configured. Please add an integration in settings.');
            }

            // Create Payment Intention
            $intention = $this->createIntention($amount, $currency, $items, $metadata, $customer, (int)$integrationId, $callbackUrl);

            // Paymob Unified Checkout URL
            return [
                'payment_url' => "https://accept.paymob.com/unifiedcheckout/?publicKey={$this->publicKey}&clientSecret={$intention['client_secret']}",
                'transaction_id' => (string) $intention['id'],
                'gateway_data' => [
                    'order_id' => $intention['order_id'] ?? $intention['intention_order_id'],
                    'payment_token' => $intention['client_secret'], // Intention uses client_secret, not payment key
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Paymob Checkout Failed: ' . $e->getMessage());
            throw $e;
        }
    }

    private function createIntention(
        float $amount,
        string $currency,
        ?array $items,
        array $metadata,
        array $customer,
        int $integrationId,
        string $redirectionUrl
    ): array {
        $names = explode(' ', $customer['name'] ?? 'Guest User', 2);
        $firstName = $names[0] ?? 'Guest';
        $lastName = $names[1] ?? 'User';

        $payload = [
            'amount' => (int) ($amount * 100), // Amount in cents
            'currency' => $currency,
            'payment_methods' => [$integrationId],
            'items' => array_map(function ($item) {
                return [
                    'name' => $item['name'] ?? 'Item',
                    'amount' => (int) (($item['price'] ?? 0) * 100),
                    'description' => $item['name'] ?? '',
                    'quantity' => $item['quantity'] ?? 1,
                ];
            }, $items ?? []),
            'billing_data' => [
                'apartment' => 'NA',
                'first_name' => $firstName,
                'last_name' => $lastName,
                'street' => 'NA',
                'building' => 'NA',
                'phone_number' => $customer['phone'] ?? '01012345678',
                'city' => 'NA',
                'country' => 'NA',
                'email' => $customer['email'] ?? 'guest@example.com',
                'floor' => 'NA',
                'state' => 'NA',
            ],
            'extras' => $metadata,
            'special_reference' => $metadata['customer_reference'] ?? null,
            'expiration' => 3600,
            // 'notification_url' => route('payment.webhook', ['gateway' => 'paymob']), // Webhook URL
            'redirection_url' => $redirectionUrl, // Callback URL
        ];

        Log::info('Paymob Request: Create Intention', $payload);

        // Using Secret Key for Authorization
        // Note: Http::asJson() already adds 'Content-Type: application/json'
        // We only need to add Authorization
        $response = $this->getRequest()
            ->withHeaders([
                'Authorization' => 'Token ' . $this->secretKey,
            ])
            ->post("{$this->baseUrl}/intention/", $payload);

        if (!$response->successful()) {
            Log::error('Paymob Intention Creation Failed', ['body' => $response->body()]);
            throw new \Exception('Paymob Intention Creation Failed: ' . $response->body());
        }

        Log::info('Paymob Intention Success', ['response' => $response->json()]);
        return $response->json();
    }

    public function getPaymentStatus(string $paymentId): array
    {
        Log::info('Paymob Request: Check Transaction Status', ['id' => $paymentId]);

        try {
            // Use API Key Authentication Flow for Transaction Status Check
            // 1. Get Auth Token using API Key
            $authToken = $this->getAuthToken();

            // 2. Check Transaction Status
            $response = $this->getRequest()
                ->withToken($authToken)
                ->get("{$this->authBaseUrl}/acceptance/transactions/{$paymentId}");

            if (!$response->successful()) {
                Log::error('Paymob Transaction Status Failed', ['body' => $response->body()]);
                throw new \Exception('Failed to fetch Paymob transaction status');
            }

            $data = $response->json();

            // Handle Transaction Response Structure (Legacy/Standard)
            $success = $data['success'] ?? false;
            $pending = $data['pending'] ?? false;

            $status = 'failed';
            if ($success === true && $pending === false) {
                $status = 'paid';
            } elseif ($pending === true) {
                $status = 'pending';
            }

            return [
                'local_payment_id' => $data['order']['merchant_order_id'] ?? $data['extra_data']['customer_reference'] ?? null,
                'status' => $status,
                'transaction_id' => $data['id'] ?? null,
                'gateway_data' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('Paymob Transaction Status Error: ' . $e->getMessage());
            throw $e;
        }
    }

    private function getAuthToken(): string
    {
        Log::info('Paymob Request: Auth Token');
        $response = $this->getRequest()->post("{$this->authBaseUrl}/auth/tokens", [
            'api_key' => $this->apiKey,
        ]);

        if (!$response->successful()) {
            Log::error('Paymob Auth Failed', ['response' => $response->body()]);
            throw new \Exception('Paymob Authentication Failed');
        }

        Log::info('Paymob Auth Success');
        return $response->json('token');
    }

    public function gatewayKey(): string
    {
        return 'paymob';
    }

    private function getDefaultIntegrationId(): ?string
    {
        $integrations = $this->getPaymentMethods(0, 'EGP', false); // Dummy amount/currency to get list
        return $integrations[0]['id'] ?? null;
    }

    public function getPaymentMethods(float $amount, string $currency, bool $filter = true): array
    {
        $integrationsJson = Setting::get('paymob_integrations');
        $integrations = $integrationsJson ? json_decode($integrationsJson, true) : null;

        if ($integrations && is_array($integrations)) {
            return array_map(function ($int) {
                return [
                    'id' => (string) ($int['id'] ?? ''),
                    'name' => (string) ($int['name'] ?? 'Paymob Method'),
                    'type' => (string) ($int['type'] ?? 'CARD'),
                    'image' => (string) ($int['image'] ?? null),
                ];
            }, $integrations);
        }

        // Fallback to default integration if no list defined
        return [
            // No default hardcoded integration anymore
        ];
    }

    private function getIntegrationType(string $integrationId): string
    {
        $integrationsJson = Setting::get('paymob_integrations');
        $integrations = $integrationsJson ? json_decode($integrationsJson, true) : [];

        if (is_array($integrations)) {
            foreach ($integrations as $int) {
                if ((string) ($int['id'] ?? '') === (string) $integrationId) {
                    return (string) ($int['type'] ?? 'CARD');
                }
            }
        }

        return 'CARD';
    }

    private function createWalletCheckout(string $paymentKey, int $orderId, array $customer): array
    {
        $response = $this->getRequest()->post("{$this->baseUrl}/acceptance/payments/pay", [
            'source' => [
                'identifier' => $customer['phone'] ?? '01000000000',
                'subtype' => 'WALLET',
            ],
            'payment_token' => $paymentKey,
        ]);

        if (!$response->successful()) {
            Log::error('Paymob Wallet Payment Failed', ['body' => $response->body()]);
            throw new \Exception('Paymob Wallet Payment Failed');
        }

        $data = $response->json();

        return [
            'payment_url' => (string) ($data['redirect_url'] ?? ''),
            'transaction_id' => (string) $orderId,
            'gateway_data' => $data,
        ];
    }
}
