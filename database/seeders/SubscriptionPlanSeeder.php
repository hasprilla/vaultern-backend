<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
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
                    'school_broadcast' => false,
                ],
            ],
            [
                'code' => 'family_plus',
                'name' => 'Familia Plus',
                'price_monthly_cents' => 499,
                'price_yearly_cents' => 3900,
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
                'price_monthly_cents' => 799,
                'price_yearly_cents' => 6900,
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
                'price_monthly_cents' => 199,
                'price_yearly_cents' => null,
                'sort_order' => 3,
                'features' => [
                    'max_students' => 999,
                    'school_broadcast' => true,
                    'teacher_panel' => true,
                ],
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::query()->updateOrCreate(
                ['code' => $plan['code']],
                array_merge($plan, [
                    'currency' => 'EUR',
                    'is_active' => true,
                ]),
            );
        }
    }
}
