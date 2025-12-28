<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\PaymentServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaymentGatewayControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'Student', 'guard_name' => 'web']);
    }

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
                string $errorUrl,
                ?string $paymentMethodId = null
            ): array {
                return [
                    'payment_url' => 'https://pay.example/redirect',
                    'transaction_id' => 'tx-1',
                ];
            }

            public function getPaymentMethods(float $amount, string $currency): array
            {
                return [];
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
                string $errorUrl,
                ?string $paymentMethodId = null
            ): array {
                return [];
            }

            public function getPaymentMethods(float $amount, string $currency): array
            {
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

    public function test_get_payment_methods_returns_list_of_methods(): void
    {
        $this->app->instance(PaymentServiceInterface::class, new class implements PaymentServiceInterface
        {
            public function createCheckout(float $a, string $c, array $cus, array $meta, string $call, string $err, ?string $pm = null): array
            {
                return [];
            }
            public function getPaymentStatus(string $id): array
            {
                return [];
            }
            public function gatewayKey(): string
            {
                return 'fake';
            }
            public function getPaymentMethods(float $amount, string $currency): array
            {
                return [
                    ['PaymentMethodId' => '1', 'PaymentMethodEn' => 'KNET'],
                ];
            }
        });

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/payments/methods?amount=10&currency=EGP');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                ['paymentMethodId' => '1', 'paymentMethodEn' => 'KNET'],
            ],
        ]);
    }
}
