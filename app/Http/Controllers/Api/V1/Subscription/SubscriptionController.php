<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Subscription;

use App\Http\Controllers\Controller;
use App\Models\Family;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Services\PlanFeatureService;
use App\Services\SubscriptionBillingService;
use App\Services\SubscriptionCancelService;
use App\Services\SubscriptionCheckoutService;
use App\Services\SubscriptionRenewalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly PlanFeatureService $planFeatures,
        private readonly SubscriptionCheckoutService $checkoutService,
        private readonly SubscriptionCancelService $cancelService,
        private readonly SubscriptionRenewalService $renewalService,
        private readonly SubscriptionBillingService $billingService,
    ) {}

    public function plans(): JsonResponse
    {
        $plans = SubscriptionPlan::query()
            ->where('is_active', true)
            ->whereNotIn('code', ['school'])
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
        if ($subscription?->isDueForRenewal()) {
            $this->renewalService->renew($subscription);
            $family->refresh()->load('subscription');
            $subscription = $family->subscription;
        }

        $family->reconcileSubscriptionPlan();
        $family->refresh()->load('subscription');

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
                'current_period_end' => $subscription?->accessUntilDate(),
                'cancelled_at' => $subscription?->cancelled_at?->toDateString(),
                'pending_cancellation' => $subscription?->isPendingCancellation() ?? false,
                'access_until' => $subscription?->accessUntilDate(),
                'free_from' => $subscription?->freeFromDate()?->toDateString(),
                'auto_renew' => $subscription?->canAutoRenew() ?? false,
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
            'plan_code' => [
                'required',
                'string',
                Rule::exists('subscription_plans', 'code')
                    ->where('is_active', true)
                    ->whereNot('code', 'free'),
            ],
            'billing' => ['nullable', 'string', 'in:monthly,yearly'],
            'simulated' => ['nullable', 'boolean'],
            'card_number' => ['required', 'string', 'min:13', 'max:23'],
            'exp_month' => ['required', 'integer', 'min:1', 'max:12'],
            'exp_year' => ['required', 'integer', 'min:'.(int) date('y'), 'max:'.((int) date('Y') + 20)],
            'cvc' => ['required', 'string', 'min:3', 'max:4'],
            'cardholder_name' => ['required', 'string', 'min:3', 'max:120'],
        ]);

        $result = $this->checkoutService->checkout($family, $request->user(), $validated);

        if (($result['success'] ?? true) === false) {
            return response()->json([
                'message' => $result['message'] ?? 'Pago rechazado',
                'code'    => 'payment_declined',
                'data'    => $result,
            ], 422);
        }

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
            'message' => 'Cancelación programada. Mantendrás tu plan hasta el '
                .$result['access_until']
                .'. El plan gratuito inicia al día siguiente.',
            'data'    => $result,
        ]);
    }

    public function resume(Request $request): JsonResponse
    {
        $family = $this->resolveFamily($request);
        if ($family === null) {
            return response()->json(['message' => 'Familia no encontrada'], 404);
        }

        $result = $this->billingService->resumeScheduledCancellation($family, $request->user());

        return response()->json([
            'message' => 'Cancelación revertida. Tu suscripción continúa activa y se renovará automáticamente.',
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

        return Family::query()->with(['subscription.renewalUser'])->find($user->family_id);
    }
}
