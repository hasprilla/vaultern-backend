<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Finance;

use PHPUnit\Framework\TestCase;

enum FinanceReportPeriod: string
{
    case WEEKLY    = 'weekly';
    case MONTHLY   = 'monthly';
    case QUARTERLY = 'quarterly';
    case ANNUAL    = 'annual';

    public function days(): int
    {
        return match($this) {
            self::WEEKLY    => 7,
            self::MONTHLY   => 30,
            self::QUARTERLY => 90,
            self::ANNUAL    => 365,
        };
    }

    public function label(): string
    {
        return match($this) {
            self::WEEKLY    => 'Semanal',
            self::MONTHLY   => 'Mensual',
            self::QUARTERLY => 'Trimestral',
            self::ANNUAL    => 'Anual',
        };
    }

    public function isLongTerm(): bool
    {
        return $this->days() >= 90;
    }
}

class FinanceReportPeriodTest extends TestCase
{
    public function test_has_four_periods(): void
    {
        $this->assertCount(4, FinanceReportPeriod::cases());
    }

    public function test_weekly_is_7_days(): void
    {
        $this->assertSame(7, FinanceReportPeriod::WEEKLY->days());
    }

    public function test_monthly_is_30_days(): void
    {
        $this->assertSame(30, FinanceReportPeriod::MONTHLY->days());
    }

    public function test_quarterly_is_90_days(): void
    {
        $this->assertSame(90, FinanceReportPeriod::QUARTERLY->days());
    }

    public function test_annual_is_365_days(): void
    {
        $this->assertSame(365, FinanceReportPeriod::ANNUAL->days());
    }

    public function test_labels_are_in_spanish(): void
    {
        $this->assertSame('Semanal',    FinanceReportPeriod::WEEKLY->label());
        $this->assertSame('Mensual',    FinanceReportPeriod::MONTHLY->label());
        $this->assertSame('Trimestral', FinanceReportPeriod::QUARTERLY->label());
        $this->assertSame('Anual',      FinanceReportPeriod::ANNUAL->label());
    }

    public function test_weekly_is_not_long_term(): void
    {
        $this->assertFalse(FinanceReportPeriod::WEEKLY->isLongTerm());
    }

    public function test_monthly_is_not_long_term(): void
    {
        $this->assertFalse(FinanceReportPeriod::MONTHLY->isLongTerm());
    }

    public function test_quarterly_is_long_term(): void
    {
        $this->assertTrue(FinanceReportPeriod::QUARTERLY->isLongTerm());
    }

    public function test_annual_is_long_term(): void
    {
        $this->assertTrue(FinanceReportPeriod::ANNUAL->isLongTerm());
    }

    public function test_days_increase_from_short_to_long(): void
    {
        $periods = FinanceReportPeriod::cases();
        for ($i = 0; $i < count($periods) - 1; $i++) {
            $this->assertLessThan(
                $periods[$i + 1]->days(),
                $periods[$i]->days(),
                "{$periods[$i]->name} should have fewer days than {$periods[$i+1]->name}"
            );
        }
    }

    public function test_can_create_from_string(): void
    {
        $this->assertSame(FinanceReportPeriod::WEEKLY,    FinanceReportPeriod::from('weekly'));
        $this->assertSame(FinanceReportPeriod::MONTHLY,   FinanceReportPeriod::from('monthly'));
        $this->assertSame(FinanceReportPeriod::QUARTERLY, FinanceReportPeriod::from('quarterly'));
        $this->assertSame(FinanceReportPeriod::ANNUAL,    FinanceReportPeriod::from('annual'));
    }

    public function test_invalid_value_throws(): void
    {
        $this->expectException(\ValueError::class);
        FinanceReportPeriod::from('daily');
    }
}
