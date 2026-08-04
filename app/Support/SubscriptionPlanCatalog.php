<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\SubscriptionPlan;

/**
 * Catálogo canónico en COP (cuenta Mercado Pago Colombia).
 * amount_cents = pesos × 100 (p. ej. 19.900 COP → 1_990_000).
 *
 * Audiencias:
 * - family: tutores / núcleos familiares (compra en /plans)
 * - school: instituciones (plan del colegio, no un “módulo”)
 *
 * El plan habilita techos y features. El dueño de la membresía
 * asigna qué puede hacer cada miembro dentro de ese techo.
 */
final class SubscriptionPlanCatalog
{
    public const AUDIENCE_FAMILY = 'family';

    public const AUDIENCE_SCHOOL = 'school';

    /**
     * @return list<array<string, mixed>>
     */
    public static function definitions(): array
    {
        return [
            // ── Tutores / familia ──────────────────────────────────────────
            [
                'code' => 'free',
                'name' => 'Familia Free',
                'price_monthly_cents' => 0,
                'price_yearly_cents' => 0,
                'sort_order' => 0,
                'features' => [
                    'audience' => self::AUDIENCE_FAMILY,
                    'max_children' => 2,
                    'max_adults' => 2,
                    'ocr_scans_monthly' => 5,
                    'ads' => true,
                    'reports' => false,
                    'tasks' => true,
                    'finances' => true,
                    'invite_members' => true,
                    'school_link' => true,
                    'priority_support' => false,
                    'school_broadcast' => false,
                    'highlights' => [
                        'Hasta 2 hijos y 2 adultos',
                        '5 escaneos OCR al mes',
                        'Tareas y finanzas básicas',
                        'Sin reportes avanzados · con anuncios',
                    ],
                ],
            ],
            [
                'code' => 'family_plus',
                'name' => 'Familia Plus',
                'price_monthly_cents' => 1_990_000,
                'price_yearly_cents' => 19_900_000,
                'sort_order' => 1,
                'features' => [
                    'audience' => self::AUDIENCE_FAMILY,
                    'max_children' => 5,
                    'max_adults' => 4,
                    'ocr_scans_monthly' => 60,
                    'ads' => false,
                    'reports' => true,
                    'tasks' => true,
                    'finances' => true,
                    'invite_members' => true,
                    'school_link' => true,
                    'priority_support' => false,
                    'school_broadcast' => false,
                    'highlights' => [
                        'Hasta 5 hijos y 4 adultos',
                        '60 escaneos OCR al mes',
                        'Reportes y presupuestos',
                        'Sin anuncios',
                        'El dueño define módulos por miembro',
                    ],
                ],
            ],
            [
                'code' => 'family_pro',
                'name' => 'Familia Pro',
                'price_monthly_cents' => 2_990_000,
                'price_yearly_cents' => 29_900_000,
                'sort_order' => 2,
                'features' => [
                    'audience' => self::AUDIENCE_FAMILY,
                    'max_children' => 20,
                    'max_adults' => 10,
                    'ocr_scans_monthly' => 999,
                    'ads' => false,
                    'reports' => true,
                    'tasks' => true,
                    'finances' => true,
                    'invite_members' => true,
                    'school_link' => true,
                    'priority_support' => true,
                    'school_broadcast' => false,
                    'highlights' => [
                        'Hasta 20 hijos y 10 adultos',
                        'OCR prácticamente ilimitado',
                        'Reportes avanzados y seguimiento',
                        'Soporte prioritario',
                        'Ideal para tutores y familias grandes',
                    ],
                ],
            ],
            // ── Instituciones ──────────────────────────────────────────────
            [
                'code' => 'school_trial',
                'name' => 'Institución Prueba',
                'price_monthly_cents' => 0,
                'price_yearly_cents' => 0,
                'sort_order' => 10,
                'features' => [
                    'audience' => self::AUDIENCE_SCHOOL,
                    'max_students' => 80,
                    'max_campuses' => 1,
                    'max_staff' => 8,
                    'teacher_panel' => true,
                    'school_broadcast' => true,
                    'groups' => true,
                    'meetings' => true,
                    'schedules' => true,
                    'announcements' => true,
                    'psych_health' => false,
                    'priority_ops' => false,
                    'trial_days' => 14,
                    'highlights' => [
                        '14 días de prueba',
                        'Hasta 80 alumnos y 1 sede',
                        'Panel docentes y comunicación básica',
                        'Sin psicología/salud prioritaria',
                    ],
                ],
            ],
            [
                'code' => 'school',
                'name' => 'Institución',
                'price_monthly_cents' => 990_000,
                'price_yearly_cents' => 9_900_000,
                'sort_order' => 11,
                'features' => [
                    'audience' => self::AUDIENCE_SCHOOL,
                    'max_students' => 500,
                    'max_campuses' => 3,
                    'max_staff' => 40,
                    'teacher_panel' => true,
                    'school_broadcast' => true,
                    'groups' => true,
                    'meetings' => true,
                    'schedules' => true,
                    'announcements' => true,
                    'psych_health' => false,
                    'priority_ops' => false,
                    'highlights' => [
                        'Hasta 500 alumnos y 3 sedes',
                        'Staff y roles del colegio',
                        'Anuncios, grupos, reuniones y horarios',
                        'Panel docente incluido',
                    ],
                ],
            ],
            [
                'code' => 'school_pro',
                'name' => 'Institución Pro',
                'price_monthly_cents' => 2_490_000,
                'price_yearly_cents' => 24_900_000,
                'sort_order' => 12,
                'features' => [
                    'audience' => self::AUDIENCE_SCHOOL,
                    'max_students' => 5000,
                    'max_campuses' => 20,
                    'max_staff' => 250,
                    'teacher_panel' => true,
                    'school_broadcast' => true,
                    'groups' => true,
                    'meetings' => true,
                    'schedules' => true,
                    'announcements' => true,
                    'psych_health' => true,
                    'priority_ops' => true,
                    'highlights' => [
                        'Hasta 5.000 alumnos y 20 sedes',
                        'Psicología y salud prioritaria',
                        'Operación ampliada para redes escolares',
                        'Cupos y staff premium',
                    ],
                ],
            ],
        ];
    }

    /** @return list<string> */
    public static function codesForAudience(string $audience): array
    {
        $codes = [];
        foreach (self::definitions() as $plan) {
            $aud = $plan['features']['audience'] ?? self::AUDIENCE_FAMILY;
            if ($aud === $audience) {
                $codes[] = $plan['code'];
            }
        }

        return $codes;
    }

    /** @return array<string, mixed>|null */
    public static function definitionFor(string $code): ?array
    {
        foreach (self::definitions() as $plan) {
            if ($plan['code'] === $code) {
                return $plan;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    public static function featuresFor(string $code): array
    {
        return self::definitionFor($code)['features'] ?? [];
    }

    public static function labelFor(string $code): string
    {
        return (string) (self::definitionFor($code)['name'] ?? $code);
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
