<?php

namespace Tests\Unit\Payment;

use App\Services\Payment\NullPaymentGatewayService;
use PHPUnit\Framework\TestCase;

class NullPaymentGatewayServiceTest extends TestCase
{
    public function test_create_checkout_returns_payment_url_and_transaction_id(): void
    {
        $service = new NullPaymentGatewayService;

        $result = $service->createCheckout(
            amount: 10.5,
            currency: 'EGP',
            customer: ['name' => 'Test', 'email' => 'test@example.com'],
            metadata: ['customer_reference' => '123'],
            callbackUrl: 'https://example.com/callback',
            errorUrl: 'https://example.com/error'
        );

        $this->assertSame('null-123', $result['transaction_id']);
        $this->assertSame('https://example.com/callback?payment_id=null-123', $result['payment_url']);
    }

    public function test_get_payment_status_is_paid_and_maps_local_payment_id(): void
    {
        $service = new NullPaymentGatewayService;

        $result = $service->getPaymentStatus('null-456');

        $this->assertSame('paid', $result['status']);
        $this->assertSame('456', $result['local_payment_id']);
        $this->assertSame('null-456', $result['transaction_id']);
    }
}
