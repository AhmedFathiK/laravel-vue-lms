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
            'payment_url' => $callbackUrl.'?payment_id=null-'.urlencode($reference),
            'transaction_id' => 'null-'.$reference,
            'gateway_data' => [
                'amount' => $amount,
                'currency' => $currency,
                'customer' => $customer,
                'customer_reference' => $reference,
                'payment_method_id' => $paymentMethodId,
            ],
        ];
    }

    public function getPaymentMethods(float $amount, string $currency): array
    {
        return [
            [
                'PaymentMethodId' => '1',
                'PaymentMethodEn' => 'KNET',
                'PaymentMethodAr' => 'KNET',
                'ImageUrl' => 'https://portal.myfatoorah.com/imgs/payment-methods/knet.png',
                'IsDirectPayment' => false,
                'ServiceCharge' => 0.5,
                'TotalAmount' => $amount + 0.5,
                'CurrencyIso' => $currency,
            ],
            [
                'PaymentMethodId' => '2',
                'PaymentMethodEn' => 'VISA/MASTER',
                'PaymentMethodAr' => 'VISA/MASTER',
                'ImageUrl' => 'https://portal.myfatoorah.com/imgs/payment-methods/vm.png',
                'IsDirectPayment' => true,
                'ServiceCharge' => 1.0,
                'TotalAmount' => $amount + 1.0,
                'CurrencyIso' => $currency,
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
}
