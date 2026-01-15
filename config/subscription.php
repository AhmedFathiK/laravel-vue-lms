<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Subscription Grace Period
    |--------------------------------------------------------------------------
    |
    | Here you may define the settings for the subscription grace period.
    | This determines how long a user retains access after a subscription
    | becomes past due.
    |
    | percentage: The percentage of the subscription duration to allow as grace.
    | max_days: The absolute maximum number of days allowed for grace period.
    |
    */
    'grace_period' => [
        'percentage' => env('SUBSCRIPTION_GRACE_PERCENTAGE', 10),
        'max_days' => env('SUBSCRIPTION_GRACE_MAX_DAYS', 7),
    ],
];
