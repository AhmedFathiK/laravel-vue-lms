<x-mail::message>
# Subscription Renewal Failed

Hello {{ $entitlement->user->full_name }},

We were unable to renew your subscription for **{{ $entitlement->billingPlan->name }}**.

**Reason:** {{ $reason }}

Please update your payment method to ensure uninterrupted access.

<x-mail::button :url="route('login')">
Update Payment Method
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
