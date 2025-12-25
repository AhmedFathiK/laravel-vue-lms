<?php

namespace Tests\Unit\Payment;

use App\Services\Payment\Currency;
use PHPUnit\Framework\TestCase;

class CurrencyTest extends TestCase
{
    public function test_normalize_converts_le_to_egp(): void
    {
        $this->assertSame('EGP', Currency::normalize('LE'));
        $this->assertSame('EGP', Currency::normalize(' le '));
    }

    public function test_normalize_uppercases_three_letter_currency(): void
    {
        $this->assertSame('USD', Currency::normalize('usd'));
        $this->assertSame('EUR', Currency::normalize(' eur '));
    }
}
