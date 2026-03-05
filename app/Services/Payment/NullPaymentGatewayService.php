<?php

namespace App\Services\Payment;

use App\Services\Payments\PaymentServiceInterface;

/**
 * Non-network payment gateway for development and testing.
 */
class NullPaymentGatewayService implements PaymentServiceInterface
{
    public function createCheckout(
        float $amount,
        string $currency,
        array $customer,
        array $metadata,
        string $callbackUrl,
        string $errorUrl,
        ?string $paymentMethodId = null,
        ?array $items = null
    ): array {
        $currency = Currency::normalize($currency);
        $reference = (string) ($metadata['customer_reference'] ?? '');

        return [
            'payment_url' => $callbackUrl . '?payment_id=null-' . urlencode($reference),
            'transaction_id' => 'null-' . $reference,
            'gateway_data' => [
                'amount' => $amount,
                'currency' => $currency,
                'customer' => $customer,
                'customer_reference' => $reference,
                'payment_method_id' => $paymentMethodId,
            ],
        ];
    }

    public function getPaymentMethods(float $amount, string $currency, bool $filter = true): array
    {
        return [
            [
                'id' => '1',
                'name' => 'KNET',
                'image' => 'https://portal.myfatoorah.com/imgs/payment-methods/knet.png',
            ],
            [
                'id' => '2',
                'name' => 'VISA/MASTER',
                'image' => 'https://portal.myfatoorah.com/imgs/payment-methods/vm.png',
            ],
        ];
    }

    public function getPaymentStatus(string $paymentId): array
    {
        return [
            'local_payment_id' => str_starts_with($paymentId, 'null-') ? substr($paymentId, 5) : null,
            'status' => 'paid',
            'transaction_id' => $paymentId,
            'gateway_data' => [
                'payment_id' => $paymentId,
            ],
        ];
    }

    public function gatewayKey(): string
    {
        return 'null';
    }

    public function chargeToken(
        string $token,
        float $amount,
        string $currency,
        array $customer,
        array $metadata
    ): array {
        return [
            'status' => 'paid',
            'transaction_id' => 'null-auto-renew-' . uniqid(),
            'gateway_data' => [
                'token' => $token,
                'amount' => $amount,
                'customer' => $customer,
            ],
        ];
    }
}
