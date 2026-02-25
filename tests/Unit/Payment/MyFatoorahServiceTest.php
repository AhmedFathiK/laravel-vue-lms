<?php

namespace Tests\Unit\Payment;

use App\Services\Payment\MyFatoorahService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MyFatoorahServiceTest extends TestCase
{
    public function test_create_checkout_sends_execute_payment_request_and_returns_checkout_data(): void
    {
        config()->set('services.myfatoorah.base_url', 'https://example.test');
        config()->set('services.myfatoorah.api_key', 'bearer test-key');

        Http::fake([
            'https://example.test/v2/ExecutePayment' => Http::response([
                'IsSuccess' => true,
                'Data' => [
                    'PaymentURL' => 'https://pay.example/redirect',
                    'InvoiceId' => 123,
                ],
            ], 200),
        ]);

        $service = new MyFatoorahService;

        $result = $service->createCheckout(
            amount: 10.5,
            currency: 'le',
            customer: ['name' => 'Test', 'email' => 'test@example.com'],
            metadata: ['customer_reference' => 'local-1'],
            callbackUrl: 'https://app.example/callback',
            errorUrl: 'https://app.example/error',
            items: [
                ['name' => 'Test Course', 'quantity' => 1, 'price' => 10.5]
            ]
        );

        $this->assertSame('https://pay.example/redirect', $result['payment_url']);
        $this->assertSame('123', $result['transaction_id']);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->url() === 'https://example.test/v2/ExecutePayment'
                && $request->method() === 'POST'
                && $request->hasHeader('Authorization', 'Bearer test-key')
                && ($data['DisplayCurrencyIso'] ?? null) === 'EGP'
                && ($data['CustomerReference'] ?? null) === 'local-1'
                && ($data['InvoiceItems'][0]['ItemName'] ?? null) === 'Test Course'
                && array_key_exists('ExpiryDate', $data);
        });
    }

    public function test_get_payment_status_maps_paid_status_and_local_payment_id(): void
    {
        config()->set('services.myfatoorah.base_url', 'https://example.test');
        config()->set('services.myfatoorah.api_key', 'test-key');

        Http::fake([
            'https://example.test/v2/GetPaymentStatus' => Http::response([
                'IsSuccess' => true,
                'Data' => [
                    'CustomerReference' => 55,
                    'InvoiceStatus' => 'Paid',
                    'InvoiceId' => 999,
                ],
            ], 200),
        ]);

        $service = new MyFatoorahService;

        $result = $service->getPaymentStatus('gw-1');

        $this->assertSame('paid', $result['status']);
        $this->assertSame(55, $result['local_payment_id']);
        $this->assertSame(999, $result['transaction_id']);
    }
}
