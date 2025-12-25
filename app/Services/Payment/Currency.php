<?php

namespace App\Services\Payment;

/**
 * Currency normalization and validation helpers for payments.
 */
final class Currency
{
    /**
     * @return array<int, string>
     */
    public static function supported(): array
    {
        $raw = (string) config('services.payment.supported_currencies', '');
        $items = array_filter(array_map('trim', explode(',', strtoupper($raw))));

        return array_values(array_unique($items));
    }

    public static function default(): string
    {
        return self::normalize((string) config('services.payment.default_currency', 'EGP'));
    }

    public static function normalize(string $currency): string
    {
        $currency = strtoupper(trim($currency));

        if ($currency === 'LE') {
            return 'EGP';
        }

        return $currency;
    }

    /**
     * @return array<int, mixed>
     */
    public static function validationRules(bool $required = true): array
    {
        $rules = [
            $required ? 'required' : 'nullable',
            'string',
            'min:2',
            'max:3',
            'regex:/^(?:[A-Za-z]{3}|[Ll][Ee])$/',
        ];

        $supported = self::supported();
        if ($supported !== []) {
            $rules[] = function (string $attribute, mixed $value, \Closure $fail) use ($supported): void {
                $normalized = self::normalize((string) $value);

                if (! in_array($normalized, $supported, true)) {
                    $fail('The selected '.$attribute.' is invalid.');
                }
            };
        }

        return $rules;
    }
}
