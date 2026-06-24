<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Finance;

use App\Domains\Finance\Entities\FinanceReportPeriod;
use PHPUnit\Framework\TestCase;

class FinanceReportPeriodTest extends TestCase
{
    public function test_has_four_periods(): void
    {
        $this->assertCount(4, FinanceReportPeriod::cases());
    }

    public function test_weekly_is_7_days(): void
    {
        $this->assertSame(7, FinanceReportPeriod::Weekly->days());
    }

    public function test_monthly_is_30_days(): void
    {
        $this->assertSame(30, FinanceReportPeriod::Monthly->days());
    }

    public function test_quarterly_is_90_days(): void
    {
        $this->assertSame(90, FinanceReportPeriod::Quarterly->days());
    }

    public function test_annual_is_365_days(): void
    {
        $this->assertSame(365, FinanceReportPeriod::Annual->days());
    }

    public function test_labels_are_in_spanish(): void
    {
        $this->assertSame('Semanal', FinanceReportPeriod::Weekly->label());
        $this->assertSame('Mensual', FinanceReportPeriod::Monthly->label());
        $this->assertSame('Trimestral', FinanceReportPeriod::Quarterly->label());
        $this->assertSame('Anual', FinanceReportPeriod::Annual->label());
    }

    public function test_weekly_is_not_long_term(): void
    {
        $this->assertFalse(FinanceReportPeriod::Weekly->isLongTerm());
    }

    public function test_quarterly_is_long_term(): void
    {
        $this->assertTrue(FinanceReportPeriod::Quarterly->isLongTerm());
    }
}
