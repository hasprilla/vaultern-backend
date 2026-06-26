<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Subscription;

use App\Http\Controllers\Controller;
use App\Models\Family;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\PlanFeatureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SubscriptionController extends Controller
{
    public function __construct(private readonly PlanFeatureService $planFeatures) {}

    public function plans(): JsonResponse
    {
        $plans = SubscriptionPlan::query()
            ->where('is_active', true)
            ->whereIn('code', ['free', 'family_plus', 'family_pro'])
            ->orderBy('sort_order')
            ->get();

        return response()->json(['data' => $plans]);
    }

    public function current(Request $request): JsonResponse
    {
        $family = $this->resolveFamily($request);
        if ($family === null) {
            return response()->json(['message' => 'Familia no encontrada'], 404);
        }

        $subscription = $family->subscription;
        $features = $this->planFeatures->featuresForFamily($family);

        return response()->json([
            'data' => [
                'plan_code' => $family->activePlanCode(),
                'subscription' => $subscription,
                'features' => $features,
            ],
        ]);
    }

    public function checkout(Request $request): JsonResponse
    {
        $family = $this->resolveFamily($request);
        if ($family === null) {
            return response()->json(['message' => 'Familia no encontrada'], 404);
        }

        $validated = $request->validate([
            'plan_code' => ['required', 'string', 'in:family_plus,family_pro'],
            'billing'   => ['nullable', 'string', 'in:monthly,yearly'],
            'simulated' => ['nullable', 'boolean'],
        ]);

        $plan = SubscriptionPlan::query()
            ->where('code', $validated['plan_code'])
            ->where('is_active', true)
            ->firstOrFail();

        $isSimulated = (bool) ($validated['simulated'] ?? true);
        $periodEnd = ($validated['billing'] ?? 'monthly') === 'yearly'
            ? now()->addYear()
            : now()->addMonth();

        Subscription::query()->updateOrCreate(
            ['family_id' => $family->id],
            [
                'id'                  => $family->subscription?->id ?? (string) Str::uuid(),
                'plan_code'           => $plan->code,
                'status'              => 'active',
                'provider'            => $isSimulated ? 'simulated' : 'manual',
                'current_period_end'  => $periodEnd,
            ],
        );

        $family->update(['plan' => $plan->code]);

        return response()->json([
            'data' => [
                'message'      => $isSimulated
                    ? 'Plan activado en modo simulado.'
                    : 'Plan activado correctamente.',
                'plan_code'    => $plan->code,
                'mode'         => $isSimulated ? 'simulated' : 'live',
                'checkout_url' => null,
            ],
        ]);
    }

    private function resolveFamily(Request $request): ?Family
    {
        $user = $request->user();
        if ($user->family_id === null) {
            return null;
        }

        return Family::query()->with('subscription')->find($user->family_id);
    }
}
