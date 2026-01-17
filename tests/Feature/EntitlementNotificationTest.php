<?php

namespace Tests\Feature;

use App\Events\EntitlementCreated;
use App\Listeners\SendEntitlementReceipt;
use App\Mail\EntitlementReceiptMail;
use App\Models\Course;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\BillingPlan;
use App\Models\User;
use App\Services\EntitlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EntitlementNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Create the Student role required by UserFactory
        \Spatie\Permission\Models\Role::create(['name' => 'Student', 'guard_name' => 'web']);
    }

    public function test_entitlement_creation_dispatches_event(): void
    {
        Event::fake();

        $user = User::factory()->create();
        $course = Course::factory()->create();
        $plan = BillingPlan::create([
            'name' => 'Test Plan',
            'price' => 100,
            'currency' => 'USD',
            'billing_type' => 'one_time',
            'access_type' => 'lifetime',
            'is_active' => true,
        ]);
        $payment = Payment::create([
            'user_id' => $user->id,
            'amount' => 100,
            'currency' => 'USD',
            'status' => 'paid',
            'payment_method' => 'card',
            'payment_provider' => 'stripe',
        ]);

        $service = new EntitlementService();
        $entitlement = $service->createEntitlement($user, $plan, $payment);

        Event::assertDispatched(EntitlementCreated::class, function ($event) use ($entitlement) {
            return $event->entitlement->id === $entitlement->id;
        });
    }

    public function test_listener_sends_email_with_pdf_attachment(): void
    {
        Mail::fake();
        
        // Setup data
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $plan = BillingPlan::create([
            'name' => 'Test Plan',
            'price' => 100,
            'currency' => 'USD',
            'billing_type' => 'one_time',
            'access_type' => 'lifetime',
            'is_active' => true,
        ]);
        
        $payment = Payment::create([
            'user_id' => $user->id,
            'amount' => 100,
            'currency' => 'USD',
            'status' => 'paid',
            'payment_method' => 'card',
            'payment_provider' => 'stripe',
        ]);

        $receipt = Receipt::create([
            'user_id' => $user->id,
            'payment_id' => $payment->id,
            'receipt_number' => 'REC-123456',
            'item_type' => 'billing_plan',
            'item_id' => $plan->id,
            'item_name' => $plan->name,
            'amount' => 100,
            'currency' => 'USD',
        ]);

        $service = new EntitlementService();
        $entitlement = $service->createEntitlement($user, $plan, $payment);

        $event = new EntitlementCreated($entitlement);

        // Mock ReceiptPdfService
        $mockPdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $mockPdf->shouldReceive('output')->andReturn('fake_pdf_content');
        
        $mockPdfService = \Mockery::mock(\App\Services\ReceiptPdfService::class);
        $mockPdfService->shouldReceive('generate')->andReturn($mockPdf);

        $listener = new SendEntitlementReceipt($mockPdfService);
        $listener->handle($event);

        Mail::assertSent(EntitlementReceiptMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }
}
