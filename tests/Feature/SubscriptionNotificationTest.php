<?php

namespace Tests\Feature;

use App\Events\SubscriptionCreated;
use App\Listeners\SendSubscriptionReceipt;
use App\Mail\SubscriptionReceiptMail;
use App\Models\Course;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SubscriptionNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Create the Student role required by UserFactory
        \Spatie\Permission\Models\Role::create(['name' => 'Student', 'guard_name' => 'web']);
    }

    public function test_subscription_creation_dispatches_event(): void
    {
        Event::fake();

        $user = User::factory()->create();
        $course = Course::factory()->create();
        $plan = SubscriptionPlan::create([
            'course_id' => $course->id,
            'name' => 'Test Plan',
            'price' => 100,
            'currency' => 'USD',
            'plan_type' => 'one-time',
            'billing_cycle' => 'one-time',
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

        $service = new SubscriptionService();
        $subscription = $service->createSubscription($user, $plan, $payment);

        Event::assertDispatched(SubscriptionCreated::class, function ($event) use ($subscription) {
            return $event->subscription->id === $subscription->id;
        });
    }

    public function test_listener_sends_email_with_pdf_attachment(): void
    {
        Mail::fake();
        
        // Setup data
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $plan = SubscriptionPlan::create([
            'course_id' => $course->id,
            'name' => 'Test Plan',
            'price' => 100,
            'currency' => 'USD',
            'plan_type' => 'one-time',
            'billing_cycle' => 'one-time',
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
            'item_type' => 'subscription_plan',
            'item_id' => $plan->id,
            'item_name' => $plan->name,
            'amount' => 100,
            'currency' => 'USD',
        ]);

        $service = new SubscriptionService();
        $subscription = $service->createSubscription($user, $plan, $payment);

        // We can't easily test the PDF generation content without mocking, 
        // but we can test that the Listener triggers the Mail.
        // To test the listener logic, we can manually trigger the listener or rely on integration.
        // Since we didn't fake Event here, the actual event flow should run.
        // However, PDF generation might fail in test env if dompdf binaries/font cache issues exist.
        // Let's mock the ReceiptPdfService if we want to isolate Mail testing.
        
        // For now, let's try running it. If PDF fails, we'll mock.
        // Actually, let's mock the PdfService to be safe and fast.
        
        $mockPdf = \Mockery::mock(\App\Services\ReceiptPdfService::class);
        $mockPdf->shouldReceive('generate')->andReturn(new class {
            public function output() { return 'fake-pdf-content'; }
        });
        
        $this->app->instance(\App\Services\ReceiptPdfService::class, $mockPdf);

        // Re-dispatch event manually to trigger listener with mocked service
        $listener = $this->app->make(SendSubscriptionReceipt::class);
        $event = new SubscriptionCreated($subscription);
        $listener->handle($event);

        Mail::assertSent(SubscriptionReceiptMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email) &&
                   $mail->pdfContent === 'fake-pdf-content';
        });
    }
}
