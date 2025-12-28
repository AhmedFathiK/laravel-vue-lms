<?php

namespace App\Services\Payments;

/**
 * Contract for payment gateway services (e.g., MyFatoorah, Stripe).
 */
interface PaymentServiceInterface
{
    /**
     * Create a checkout and return the gateway redirect URL and identifiers.
     *
     * @param  string  $currency  ISO 4217 currency code (e.g., EGP).
     * @param  array<string, mixed>  $customer
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    public function createCheckout(
        float $amount,
        string $currency,
        array $customer,
        array $metadata,
        string $callbackUrl,
        string $errorUrl,
        ?string $paymentMethodId = null
    ): array;

    /**
     * Retrieve available payment methods from the gateway.
     */
    public function getPaymentMethods(float $amount, string $currency): array;

    /**
     * Retrieve payment status from the gateway.
     */
    public function getPaymentStatus(string $paymentId): array;

    /**
     * Machine key for the gateway (e.g., myfatoorah).
     */
    public function gatewayKey(): string;
}
