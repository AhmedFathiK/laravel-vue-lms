<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\BillingPlan;
use App\Models\UserEntitlement;
use App\Models\Payment;
use App\Models\PaymentToken;
use App\Services\EntitlementService;
use App\Services\Payment\PaymobService;
use App\Services\Payment\MyFatoorahService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;
use Mockery;

class EntitlementAutoRenewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'Student']);
    }

    public function test_attempt_auto_renew_with_paymob_token()
    {
        // 1. Setup User and Plan
        $user = User::factory()->create();
        $plan = BillingPlan::factory()->create([
            'billing_type' => 'recurring',
            'billing_interval' => 'month',
            'price' => 100,
            'currency' => 'EGP',
        ]);

        // 2. Setup Entitlement
        $entitlement = UserEntitlement::create([
            'user_id' => $user->id,
            'billing_plan_id' => $plan->id,
            'status' => 'past_due',
            'auto_renew' => true,
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subDay(),
        ]);

        // 3. Setup Saved Token
        $token = PaymentToken::create([
            'user_id' => $user->id,
            'gateway' => 'paymob',
            'token' => 'test_paymob_token',
            'is_default' => true,
        ]);

        // 4. Mock Paymob Service
        $paymobMock = Mockery::mock(PaymobService::class);
        $paymobMock->shouldReceive('chargeToken')
            ->once()
            ->andReturn([
                'status' => 'paid',
                'transaction_id' => 'pm_tx_123',
                'gateway_data' => ['foo' => 'bar'],
            ]);

        $this->app->instance(PaymobService::class, $paymobMock);

        // 5. Run Auto-Renew
        $service = new EntitlementService();
        $result = $service->attemptAutoRenew($entitlement);

        // 6. Assertions
        $this->assertTrue($result);
        $this->assertEquals('active', $entitlement->fresh()->status);
        $this->assertTrue($entitlement->fresh()->ends_at->isFuture());

        $payment = Payment::where('user_id', $user->id)->where('status', 'completed')->first();
        $this->assertNotNull($payment);
        $this->assertEquals('paymob', $payment->payment_method);
        $this->assertEquals('pm_tx_123', $payment->transaction_id);
    }

    public function test_attempt_auto_renew_with_myfatoorah_token()
    {
        // 1. Setup User and Plan
        $user = User::factory()->create();
        $plan = BillingPlan::factory()->create([
            'billing_type' => 'recurring',
            'billing_interval' => 'month',
            'price' => 100,
            'currency' => 'KWD',
        ]);

        // 2. Setup Entitlement
        $entitlement = UserEntitlement::create([
            'user_id' => $user->id,
            'billing_plan_id' => $plan->id,
            'status' => 'past_due',
            'auto_renew' => true,
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subDay(),
        ]);

        // 3. Setup Saved Token
        $token = PaymentToken::create([
            'user_id' => $user->id,
            'gateway' => 'myfatoorah',
            'token' => 'test_myfatoorah_token',
            'is_default' => true,
        ]);

        // 4. Mock MyFatoorah Service
        $mfMock = Mockery::mock(MyFatoorahService::class);
        $mfMock->shouldReceive('chargeToken')
            ->once()
            ->andReturn([
                'status' => 'paid',
                'transaction_id' => 'mf_tx_123',
                'gateway_data' => ['foo' => 'bar'],
            ]);

        $this->app->instance(MyFatoorahService::class, $mfMock);

        // 5. Run Auto-Renew
        $service = new EntitlementService();
        $result = $service->attemptAutoRenew($entitlement);

        // 6. Assertions
        $this->assertTrue($result);
        $this->assertEquals('active', $entitlement->fresh()->status);

        $payment = Payment::where('user_id', $user->id)->where('status', 'completed')->first();
        $this->assertNotNull($payment);
        $this->assertEquals('myfatoorah', $payment->payment_method);
    }

    public function test_attempt_auto_renew_fails_if_no_token()
    {
        $user = User::factory()->create();
        $plan = BillingPlan::factory()->create(['billing_type' => 'recurring']);
        $entitlement = UserEntitlement::create([
            'user_id' => $user->id,
            'billing_plan_id' => $plan->id,
            'status' => 'past_due',
            'auto_renew' => true,
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subDay(),
        ]);

        $service = new EntitlementService();
        $result = $service->attemptAutoRenew($entitlement);

        $this->assertFalse($result);
        $this->assertEquals('past_due', $entitlement->fresh()->status);
    }
}
