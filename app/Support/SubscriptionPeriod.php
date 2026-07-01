<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Carbon;

final class SubscriptionPeriod
{
    /** Último día con acceso de pago (mismo día calendario +1 mes o +1 año). */
    public static function periodEndFrom(Carbon $start, string $billing): Carbon
    {
        $day = $start->copy()->startOfDay();
        $end = $billing === 'yearly' ? $day->copy()->addYear() : $day->copy()->addMonth();

        return $end->endOfDay();
    }

    /** Primer día en plan gratuito (día calendario siguiente al vencimiento). */
    public static function freeFromAfter(Carbon $periodEnd): Carbon
    {
        return $periodEnd->copy()->startOfDay()->addDay();
    }

    public static function accessUntilDate(Carbon $periodEnd): string
    {
        return $periodEnd->copy()->startOfDay()->toDateString();
    }
}
