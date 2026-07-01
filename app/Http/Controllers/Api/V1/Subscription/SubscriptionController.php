<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Subscription;

use App\Http\Controllers\Controller;
use App\Models\Family;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Services\PlanFeatureService;
use App\Services\SubscriptionCancelService;
use App\Services\SubscriptionCheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly PlanFeatureService $planFeatures,
        private readonly SubscriptionCheckoutService $checkoutService,
        private readonly SubscriptionCancelService $cancelService,
    ) {}

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
                'billing' => $subscription?->billing,
                'provider' => $subscription?->provider,
                'mode' => $subscription?->provider === 'simulated' ? 'simulated' : 'live',
                'subscription' => $subscription,
                'features' => $features,
                'current_period_end' => $subscription?->current_period_end,
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
            'billing' => ['nullable', 'string', 'in:monthly,yearly'],
            'simulated' => ['nullable', 'boolean'],
            'card_number' => ['required', 'string', 'min:13', 'max:23'],
            'exp_month' => ['required', 'integer', 'min:1', 'max:12'],
            'exp_year' => ['required', 'integer', 'min:'.(int) date('y'), 'max:'.((int) date('Y') + 20)],
            'cvc' => ['required', 'string', 'min:3', 'max:4'],
            'cardholder_name' => ['required', 'string', 'min:3', 'max:120'],
        ]);

        $result = $this->checkoutService->checkout($family, $request->user(), $validated);

        return response()->json(['data' => $result], 201);
    }

    public function cancel(Request $request): JsonResponse
    {
        $family = $this->resolveFamily($request);
        if ($family === null) {
            return response()->json(['message' => 'Familia no encontrada'], 404);
        }

        $result = $this->cancelService->cancel($family, $request->user());

        return response()->json([
            'message' => 'Suscripción cancelada. Volviste al plan gratuito.',
            'data'    => $result,
        ]);
    }

    public function payments(Request $request): JsonResponse
    {
        $family = $this->resolveFamily($request);
        if ($family === null) {
            return response()->json(['message' => 'Familia no encontrada'], 404);
        }

        $payments = SubscriptionPayment::query()
            ->with(['events' => fn ($q) => $q->orderBy('created_at')])
            ->where('family_id', $family->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($payments);
    }

    public function showPayment(Request $request, SubscriptionPayment $payment): JsonResponse
    {
        $family = $this->resolveFamily($request);
        if ($family === null || $payment->family_id !== $family->id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $payment->load(['events' => fn ($q) => $q->orderBy('created_at'), 'subscription']);

        return response()->json(['data' => $payment]);
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
