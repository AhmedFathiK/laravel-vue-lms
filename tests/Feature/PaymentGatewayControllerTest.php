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

    public function test_callback_creates_subscription_and_receipt_for_valid_plan_payment(): void
    {
        $user = User::factory()->create();
        $course = \App\Models\Course::factory()->create();
        $plan = \App\Models\SubscriptionPlan::create([
            'course_id' => $course->id,
            'name' => 'Test Plan',
            'price' => 100,
            'currency' => 'USD',
            'billing_cycle' => 'one-time',
            'plan_type' => 'one-time',
            'is_active' => true,
        ]);

        $payment = Payment::create([
            'user_id' => $user->id,
            'amount' => 100,
            'currency' => 'USD',
            'status' => 'pending',
            'payment_method' => 'fake',
            'payment_provider' => 'fake',
            'payment_details' => [
                'plan_id' => $plan->id,
                'course_id' => $course->id,
            ],
        ]);

        $this->app->instance(PaymentServiceInterface::class, new class($payment->id) implements PaymentServiceInterface
        {
            public function __construct(private readonly int $paymentId) {}
            public function createCheckout(float $a, string $c, array $cus, array $meta, string $call, string $err, ?string $pm = null): array { return []; }
            public function getPaymentMethods(float $a, string $c): array { return []; }
            public function getPaymentStatus(string $paymentId): array {
                return [
                    'local_payment_id' => $this->paymentId,
                    'status' => 'paid',
                    'transaction_id' => 'gw-1',
                    'gateway_data' => ['payment_id' => $paymentId],
                ];
            }
            public function gatewayKey(): string { return 'fake'; }
        });

        $response = $this->get('/api/payments/callback?payment_id=gw-1');

        $response->assertRedirect('/courses/' . $course->id . '?payment=success&payment_id=' . $payment->id);

        $this->assertDatabaseHas('user_subscriptions', [
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('receipts', [
            'payment_id' => $payment->id,
            'item_id' => $plan->id,
        ]);
    }

    public function test_callback_handles_duplicate_subscription_gracefully(): void
    {
        $user = User::factory()->create();
        $course = \App\Models\Course::factory()->create();
        $plan = \App\Models\SubscriptionPlan::create([
            'course_id' => $course->id,
            'name' => 'Test Plan',
            'price' => 100,
            'currency' => 'USD',
            'billing_cycle' => 'one-time',
            'plan_type' => 'one-time',
            'is_active' => true,
        ]);

        // Create existing subscription
        \App\Models\UserSubscription::create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'starts_at' => now(),
            'status' => 'active',
        ]);

        $payment = Payment::create([
            'user_id' => $user->id,
            'amount' => 100,
            'currency' => 'USD',
            'status' => 'pending',
            'payment_method' => 'fake',
            'payment_provider' => 'fake',
            'payment_details' => [
                'plan_id' => $plan->id,
                'course_id' => $course->id,
            ],
        ]);

        $this->app->instance(PaymentServiceInterface::class, new class($payment->id) implements PaymentServiceInterface
        {
            public function __construct(private readonly int $paymentId) {}
            public function createCheckout(float $a, string $c, array $cus, array $meta, string $call, string $err, ?string $pm = null): array { return []; }
            public function getPaymentMethods(float $a, string $c): array { return []; }
            public function getPaymentStatus(string $paymentId): array {
                return [
                    'local_payment_id' => $this->paymentId,
                    'status' => 'paid',
                    'transaction_id' => 'gw-1',
                    'gateway_data' => ['payment_id' => $paymentId],
                ];
            }
            public function gatewayKey(): string { return 'fake'; }
        });

        $response = $this->get('/api/payments/callback?payment_id=gw-1');

        $response->assertRedirect('/courses/' . $course->id . '?payment=success&payment_id=' . $payment->id);

        // Verify receipt is still created
        $this->assertDatabaseHas('receipts', [
            'payment_id' => $payment->id,
            'item_id' => $plan->id,
        ]);

        // Verify only one subscription exists
        $this->assertEquals(1, \App\Models\UserSubscription::where('user_id', $user->id)->where('subscription_plan_id', $plan->id)->count());
    }
}
