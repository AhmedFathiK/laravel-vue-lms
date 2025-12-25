<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\PaymentServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentGatewayControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_creates_payment_and_returns_payment_url(): void
    {
        $this->app->instance(PaymentServiceInterface::class, new class implements PaymentServiceInterface
        {
            public function createCheckout(
                float $amount,
                string $currency,
                array $customer,
                array $metadata,
                string $callbackUrl,
                string $errorUrl
            ): array {
                return [
                    'payment_url' => 'https://pay.example/redirect',
                    'transaction_id' => 'tx-1',
                ];
            }

            public function getPaymentStatus(string $paymentId): array
            {
                return [];
            }

            public function gatewayKey(): string
            {
                return 'fake';
            }
        });

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/payments/checkout', [
            'amount' => 10.5,
            'currency' => 'le',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'paymentUrl' => 'https://pay.example/redirect',
        ]);

        $paymentId = $response->json('paymentId');
        $this->assertNotNull($paymentId);

        $payment = Payment::find($paymentId);
        $this->assertNotNull($payment);
        $this->assertSame('EGP', $payment->currency);
        $this->assertSame('fake', $payment->payment_method);
        $this->assertSame('fake', $payment->payment_provider);
        $this->assertSame('tx-1', $payment->transaction_id);
    }

    public function test_callback_marks_payment_completed_and_redirects_to_success_url(): void
    {
        $user = User::factory()->create();
        $payment = Payment::create([
            'user_id' => $user->id,
            'amount' => 10.5,
            'currency' => 'EGP',
            'status' => 'pending',
            'payment_method' => 'fake',
            'payment_provider' => 'fake',
            'payment_details' => [],
        ]);

        $this->app->instance(PaymentServiceInterface::class, new class($payment->id) implements PaymentServiceInterface
        {
            public function __construct(private readonly int $paymentId) {}

            public function createCheckout(
                float $amount,
                string $currency,
                array $customer,
                array $metadata,
                string $callbackUrl,
                string $errorUrl
            ): array {
                return [];
            }

            public function getPaymentStatus(string $paymentId): array
            {
                return [
                    'local_payment_id' => $this->paymentId,
                    'status' => 'paid',
                    'transaction_id' => 'gw-1',
                    'gateway_data' => ['payment_id' => $paymentId],
                ];
            }

            public function gatewayKey(): string
            {
                return 'fake';
            }
        });

        $response = $this->get('/api/payments/callback?payment_id=gw-1');

        $response->assertRedirect('/?payment=success&id=' . $payment->id);

        $payment->refresh();
        $this->assertSame('completed', $payment->status);
        $this->assertSame('gw-1', $payment->transaction_id);
    }
}
