<?php

namespace App\Services;

use App\Models\User;
use App\Models\SubscriptionPlan;
use App\Models\UserSubscription;
use Carbon\Carbon;

class SubscriptionService
{
    public function create(User $user, SubscriptionPlan $plan, ?int $paymentId = null): UserSubscription
    {
        $startsAt = Carbon::now();
        $endsAt = $plan->duration_days ? $startsAt->copy()->addDays($plan->duration_days) : null;

        return UserSubscription::create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'payment_id' => $paymentId,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => 'active',
            'auto_renew' => $plan->isRecurring(),
        ]);
    }
}
