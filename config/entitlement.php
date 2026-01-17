<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Entitlement Grace Period
    |--------------------------------------------------------------------------
    |
    | Here you may define the settings for the entitlement grace period.
    | This determines how long a user retains access after an entitlement
    | becomes past due.
    |
    | percentage: The percentage of the entitlement duration to allow as grace.
    | max_days: The absolute maximum number of days allowed for grace period.
    |
    */
    'grace_period' => [
        'percentage' => env('ENTITLEMENT_GRACE_PERCENTAGE', 10),
        'max_days' => env('ENTITLEMENT_GRACE_MAX_DAYS', 7),
    ],
];
