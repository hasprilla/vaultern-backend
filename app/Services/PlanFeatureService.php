<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Family;
use App\Models\SubscriptionPlan;

class PlanFeatureService
{
    /** @return array<string, mixed> */
    public function featuresForFamily(Family $family): array
    {
        $plan = SubscriptionPlan::query()
            ->where('code', $family->activePlanCode())
            ->where('is_active', true)
            ->first();

        return $plan?->features ?? [
            'max_children' => 2,
            'ocr_scans_monthly' => 5,
            'ads' => true,
        ];
    }

    public function familyHasFeature(Family $family, string $feature): bool
    {
        $features = $this->featuresForFamily($family);

        return (bool) ($features[$feature] ?? false);
    }

    public function familyFeatureLimit(Family $family, string $feature, int $default = 0): int
    {
        $features = $this->featuresForFamily($family);
        $value = $features[$feature] ?? $default;

        return is_numeric($value) ? (int) $value : $default;
    }
}
