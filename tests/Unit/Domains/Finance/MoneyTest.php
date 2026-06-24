<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Finance;

use App\Domains\Finance\ValueObjects\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    // ── Construction ──────────────────────────────────────
    public function test_can_create_money_with_positive_amount(): void
    {
        $money = new Money(100.0, 'COP');
        $this->assertSame(100.0, $money->amount);
        $this->assertSame('COP', $money->currency);
    }

    public function test_default_currency_is_cop(): void
    {
        $money = new Money(50.0);
        $this->assertSame('COP', $money->currency);
    }

    public function test_can_create_money_with_zero_amount(): void
    {
        $money = new Money(0.0);
        $this->assertSame(0.0, $money->amount);
    }

    public function test_throws_exception_for_negative_amount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount cannot be negative');
        new Money(-10.0);
    }

    // ── Add ───────────────────────────────────────────────
    public function test_add_two_money_values(): void
    {
        $a = new Money(100.0, 'COP');
        $b = new Money(50.0,  'COP');
        $result = $a->add($b);
        $this->assertSame(150.0, $result->amount);
        $this->assertSame('COP', $result->currency);
    }

    public function test_add_returns_new_instance(): void
    {
        $a = new Money(100.0);
        $b = new Money(50.0);
        $result = $a->add($b);
        $this->assertNotSame($a, $result);
        $this->assertNotSame($b, $result);
    }

    public function test_add_throws_on_currency_mismatch(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Currency mismatch');
        (new Money(100.0, 'COP'))->add(new Money(50.0, 'USD'));
    }

    // ── Subtract ──────────────────────────────────────────
    public function test_subtract_two_money_values(): void
    {
        $a = new Money(200.0, 'COP');
        $b = new Money(75.0,  'COP');
        $result = $a->subtract($b);
        $this->assertSame(125.0, $result->amount);
    }

    public function test_subtract_throws_on_currency_mismatch(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new Money(100.0, 'USD'))->subtract(new Money(50.0, 'COP'));
    }

    // ── isGreaterThan ─────────────────────────────────────
    public function test_is_greater_than_returns_true_when_larger(): void
    {
        $big   = new Money(500.0);
        $small = new Money(100.0);
        $this->assertTrue($big->isGreaterThan($small));
    }

    public function test_is_greater_than_returns_false_when_smaller(): void
    {
        $small = new Money(10.0);
        $big   = new Money(100.0);
        $this->assertFalse($small->isGreaterThan($big));
    }

    public function test_is_greater_than_returns_false_for_equal_amounts(): void
    {
        $a = new Money(100.0);
        $b = new Money(100.0);
        $this->assertFalse($a->isGreaterThan($b));
    }

    public function test_is_greater_than_throws_on_currency_mismatch(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new Money(100.0, 'COP'))->isGreaterThan(new Money(50.0, 'USD'));
    }

    // ── Format ────────────────────────────────────────────
    public function test_format_returns_string_with_currency(): void
    {
        $money = new Money(1500.50, 'COP');
        $formatted = $money->format();
        $this->assertStringContainsString('COP', $formatted);
        $this->assertStringContainsString('1,500.50', $formatted);
    }

    public function test_format_with_usd(): void
    {
        $money = new Money(99.99, 'USD');
        $this->assertStringContainsString('USD', $money->format());
    }

    // ── Immutability ──────────────────────────────────────
    public function test_original_is_unchanged_after_add(): void
    {
        $original = new Money(100.0);
        $original->add(new Money(50.0));
        $this->assertSame(100.0, $original->amount);
    }
}
