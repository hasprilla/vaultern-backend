<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\SubscriptionPlan;

/**
 * Catálogo canónico en COP (cuenta Mercado Pago Colombia).
 * amount_cents = pesos × 100 (p. ej. 19.900 COP → 1_990_000).
 */
final class SubscriptionPlanCatalog
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function definitions(): array
    {
        return [
            [
                'code' => 'free',
                'name' => 'Free',
                'price_monthly_cents' => 0,
                'price_yearly_cents' => 0,
                'sort_order' => 0,
                'features' => [
                    'max_children' => 2,
                    'ocr_scans_monthly' => 5,
                    'ads' => true,
                    'reports' => false,
                    'school_broadcast' => false,
                ],
            ],
            [
                'code' => 'family_plus',
                'name' => 'Familia Plus',
                'price_monthly_cents' => 1_990_000,
                'price_yearly_cents' => 19_900_000,
                'sort_order' => 1,
                'features' => [
                    'max_children' => 99,
                    'ocr_scans_monthly' => 999,
                    'ads' => false,
                    'reports' => true,
                    'school_broadcast' => false,
                ],
            ],
            [
                'code' => 'family_pro',
                'name' => 'Familia Pro',
                'price_monthly_cents' => 2_990_000,
                'price_yearly_cents' => 29_900_000,
                'sort_order' => 2,
                'features' => [
                    'max_children' => 99,
                    'ocr_scans_monthly' => 999,
                    'ads' => false,
                    'reports' => true,
                    'school_broadcast' => false,
                ],
            ],
            [
                'code' => 'school',
                'name' => 'Escuela',
                'price_monthly_cents' => 990_000,
                'price_yearly_cents' => null,
                'sort_order' => 3,
                'features' => [
                    'max_students' => 999,
                    'school_broadcast' => true,
                    'teacher_panel' => true,
                ],
            ],
        ];
    }

    /** Upsert planes activos (idempotente). Fuerza COP para MP Colombia. */
    public static function ensureSeeded(): void
    {
        foreach (self::definitions() as $plan) {
            SubscriptionPlan::query()->updateOrCreate(
                ['code' => $plan['code']],
                array_merge($plan, [
                    'currency' => 'COP',
                    'is_active' => true,
                ]),
            );
        }
    }
}
