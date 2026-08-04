<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Family;
use App\Models\School;
use App\Models\SchoolSubscription;
use App\Models\SubscriptionPlan;
use App\Support\SubscriptionPlanCatalog;

class PlanFeatureService
{
    /** @return array<string, mixed> */
    public function featuresForPlanCode(string $planCode): array
    {
        $plan = SubscriptionPlan::query()
            ->where('code', $planCode)
            ->where('is_active', true)
            ->first();

        if ($plan !== null && is_array($plan->features)) {
            return $plan->features;
        }

        $fromCatalog = SubscriptionPlanCatalog::featuresFor($planCode);
        if ($fromCatalog !== []) {
            return $fromCatalog;
        }

        return SubscriptionPlanCatalog::featuresFor('free');
    }

    /** @return array<string, mixed> */
    public function featuresForFamily(Family $family): array
    {
        return $this->featuresForPlanCode($family->activePlanCode());
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

    /**
     * Módulo familiar habilitado por el plan (techo).
     * El dueño retiene el derecho a restringir miembros por debajo de este techo.
     */
    public function familyAllowsModule(Family $family, string $module): bool
    {
        $features = $this->featuresForFamily($family);

        return match ($module) {
            'tasks' => (bool) ($features['tasks'] ?? true),
            'finances', 'finance' => (bool) ($features['finances'] ?? true),
            'invite_members' => (bool) ($features['invite_members'] ?? true),
            'reports' => (bool) ($features['reports'] ?? false),
            default => (bool) ($features[$module] ?? false),
        };
    }

    public function activeSchoolPlanCode(School $school): string
    {
        $sub = $school->relationLoaded('subscription')
            ? $school->subscription
            : SchoolSubscription::query()->where('school_id', $school->id)->first();

        if ($sub !== null && filled($sub->plan_code)) {
            return (string) $sub->plan_code;
        }

        return filled($school->plan) ? (string) $school->plan : 'school_trial';
    }

    /** @return array<string, mixed> */
    public function featuresForSchool(School $school): array
    {
        return $this->featuresForPlanCode($this->activeSchoolPlanCode($school));
    }

    public function schoolHasFeature(School $school, string $feature): bool
    {
        $features = $this->featuresForSchool($school);

        return (bool) ($features[$feature] ?? false);
    }

    public function schoolFeatureLimit(School $school, string $feature, int $default = 0): int
    {
        $features = $this->featuresForSchool($school);
        $value = $features[$feature] ?? $default;

        return is_numeric($value) ? (int) $value : $default;
    }

    /** @return list<string> */
    public function highlightBullets(string $planCode): array
    {
        $features = $this->featuresForPlanCode($planCode);
        $highlights = $features['highlights'] ?? [];

        if (! is_array($highlights)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($item) => is_string($item) ? $item : null,
            $highlights,
        )));
    }
}
