<?php

declare(strict_types=1);

namespace App\Domains\Finance\Entities;

enum FinanceReportPeriod: string
{
    case Weekly    = 'weekly';
    case Monthly   = 'monthly';
    case Quarterly = 'quarterly';
    case Annual    = 'annual';

    public function label(): string
    {
        return match ($this) {
            self::Weekly    => 'Semanal',
            self::Monthly   => 'Mensual',
            self::Quarterly => 'Trimestral',
            self::Annual    => 'Anual',
        };
    }

    public function days(): int
    {
        return match ($this) {
            self::Weekly    => 7,
            self::Monthly   => 30,
            self::Quarterly => 90,
            self::Annual    => 365,
        };
    }

    public function isLongTerm(): bool
    {
        return $this->days() >= 90;
    }
}
