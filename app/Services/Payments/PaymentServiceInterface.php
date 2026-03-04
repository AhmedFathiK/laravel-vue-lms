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
     * @param  array<array<string, mixed>>|null  $items
     * @return array<string, mixed>
     */
    public function createCheckout(
        float $amount,
        string $currency,
        array $customer,
        array $metadata,
        string $callbackUrl,
        string $errorUrl,
        ?string $paymentMethodId = null,
        ?array $items = null
    ): array;

    /**
     * Retrieve available payment methods from the gateway.
     */
    public function getPaymentMethods(float $amount, string $currency, bool $filter = true): array;

    /**
     * Retrieve payment status from the gateway.
     */
    public function getPaymentStatus(string $paymentId): array;

    /**
     * Machine key for the gateway (e.g., myfatoorah).
     */
    public function gatewayKey(): string;

    /**
     * Charge a saved token.
     */
    public function chargeToken(
        string $token,
        float $amount,
        string $currency,
        array $customer,
        array $metadata
    ): array;
}
