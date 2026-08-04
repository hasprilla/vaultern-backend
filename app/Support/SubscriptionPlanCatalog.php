<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\SubscriptionPlan;

/**
 * Catálogo canónico en COP.
 *
 * Tres audiencias:
 * - family: núcleos de papá/mamá (compra en /plans)
 * - tutor: tutores / cuidadores profesionales o legales
 * - school: instituciones (plan del colegio)
 *
 * El plan es techo de funciones; el dueño define accesos por miembro.
 */
final class SubscriptionPlanCatalog
{
    public const AUDIENCE_FAMILY = 'family';

    public const AUDIENCE_TUTOR = 'tutor';

    public const AUDIENCE_SCHOOL = 'school';

    /**
     * @return list<array<string, mixed>>
     */
    public static function definitions(): array
    {
        return [
            // ── Familias ──────────────────────────────────────────────────
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
                    'highlights' => [
                        'Núcleo familiar básico',
                        'Hasta 2 hijos y 2 adultos',
                        '5 escaneos OCR al mes',
                        'Sin reportes · con anuncios',
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
                    'highlights' => [
                        'Hasta 5 hijos y 4 adultos',
                        '60 escaneos OCR al mes',
                        'Reportes y presupuestos',
                        'Sin anuncios',
                        'Dueño define módulos por miembro',
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
                    'highlights' => [
                        'Hasta 20 hijos y 10 adultos',
                        'OCR prácticamente ilimitado',
                        'Reportes avanzados',
                        'Soporte prioritario',
                    ],
                ],
            ],
            // ── Tutores ───────────────────────────────────────────────────
            [
                'code' => 'tutor_free',
                'name' => 'Tutor Free',
                'price_monthly_cents' => 0,
                'price_yearly_cents' => 0,
                'sort_order' => 5,
                'features' => [
                    'audience' => self::AUDIENCE_TUTOR,
                    'max_children' => 3,
                    'max_adults' => 1,
                    'ocr_scans_monthly' => 8,
                    'ads' => true,
                    'reports' => false,
                    'tasks' => true,
                    'finances' => false,
                    'invite_members' => false,
                    'school_link' => true,
                    'priority_support' => false,
                    'highlights' => [
                        'Para tutores / cuidadores',
                        'Hasta 3 alumnos a cargo',
                        'Tareas escolares, sin finanzas del núcleo',
                        '8 OCR/mes · con anuncios',
                    ],
                ],
            ],
            [
                'code' => 'tutor_plus',
                'name' => 'Tutor Plus',
                'price_monthly_cents' => 1_490_000,
                'price_yearly_cents' => 14_900_000,
                'sort_order' => 6,
                'features' => [
                    'audience' => self::AUDIENCE_TUTOR,
                    'max_children' => 8,
                    'max_adults' => 2,
                    'ocr_scans_monthly' => 80,
                    'ads' => false,
                    'reports' => true,
                    'tasks' => true,
                    'finances' => true,
                    'invite_members' => true,
                    'school_link' => true,
                    'priority_support' => false,
                    'highlights' => [
                        'Hasta 8 alumnos a cargo',
                        'Tareas + finanzas de seguimiento',
                        '80 OCR/mes · sin anuncios',
                        'Reportes de avance',
                    ],
                ],
            ],
            [
                'code' => 'tutor_pro',
                'name' => 'Tutor Pro',
                'price_monthly_cents' => 2_490_000,
                'price_yearly_cents' => 24_900_000,
                'sort_order' => 7,
                'features' => [
                    'audience' => self::AUDIENCE_TUTOR,
                    'max_children' => 30,
                    'max_adults' => 4,
                    'ocr_scans_monthly' => 999,
                    'ads' => false,
                    'reports' => true,
                    'tasks' => true,
                    'finances' => true,
                    'invite_members' => true,
                    'school_link' => true,
                    'priority_support' => true,
                    'highlights' => [
                        'Hasta 30 alumnos a cargo',
                        'OCR ilimitado y reportes',
                        'Soporte prioritario',
                        'Ideal para tutores multi-familia',
                    ],
                ],
            ],
            // ── Instituciones ─────────────────────────────────────────────
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
                        'Operación para redes escolares',
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

    /** Planes que se compran desde la app (familia + tutor). */
    /** @return list<string> */
    public static function consumerPlanCodes(): array
    {
        return array_merge(
            self::codesForAudience(self::AUDIENCE_FAMILY),
            self::codesForAudience(self::AUDIENCE_TUTOR),
        );
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
