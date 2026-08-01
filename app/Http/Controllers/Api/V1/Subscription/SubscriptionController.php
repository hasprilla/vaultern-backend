<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Subscription;

use App\Http\Controllers\Controller;
use App\Models\Family;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Services\MercadoPago\MercadoPagoCheckoutService;
use App\Services\PlanFeatureService;
use App\Services\SubscriptionBillingService;
use App\Services\SubscriptionCancelService;
use App\Services\SubscriptionCheckoutService;
use App\Services\SubscriptionRenewalService;
use App\Support\SubscriptionPlanCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly PlanFeatureService $planFeatures,
        private readonly SubscriptionCheckoutService $checkoutService,
        private readonly MercadoPagoCheckoutService $mpCheckoutService,
        private readonly SubscriptionCancelService $cancelService,
        private readonly SubscriptionRenewalService $renewalService,
        private readonly SubscriptionBillingService $billingService,
    ) {}

    public function plans(): JsonResponse
    {
        SubscriptionPlanCatalog::ensureSeeded();

        $plans = SubscriptionPlan::query()
            ->where('is_active', true)
            ->whereNotIn('code', ['school'])
            ->orderBy('sort_order')
            ->get();

        $mpEnabled = (bool) config('mercadopago.enabled');

        return response()->json([
            'data' => $plans,
            'meta' => [
                'checkout' => $mpEnabled ? 'mercadopago' : 'simulated',
                'mercadopago_enabled' => $mpEnabled,
                'currency' => 'COP',
            ],
        ]);
    }

    public function checkoutConfig(): JsonResponse
    {
        $mpEnabled = (bool) config('mercadopago.enabled')
            && filled(config('mercadopago.access_token'));

        return response()->json([
            'data' => [
                'checkout' => $mpEnabled ? 'mercadopago' : 'simulated',
                'mercadopago_enabled' => $mpEnabled,
                'currency' => 'COP',
            ],
        ]);
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

        // Normalizar año 2 dígitos → 4 (Flutter envía 2030; algunos clientes envían 30).
        $expYear = (int) $request->input('exp_year', 0);
        if ($expYear > 0 && $expYear < 100) {
            $request->merge(['exp_year' => 2000 + $expYear]);
        }

        $validated = $request->validate([
            'plan_code' => ['required', 'string', 'max:40'],
            'billing' => ['nullable', 'string', 'in:monthly,yearly'],
            'simulated' => ['nullable', 'boolean'],
            'card_number' => ['required', 'string', 'min:13', 'max:23'],
            'exp_month' => ['required', 'integer', 'min:1', 'max:12'],
            'exp_year' => ['required', 'integer', 'min:'.(int) date('Y'), 'max:'.((int) date('Y') + 20)],
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

    public function checkoutMercadoPago(Request $request): JsonResponse
    {
        $family = $this->resolveFamily($request);
        if ($family === null) {
            return response()->json(['message' => 'Familia no encontrada'], 404);
        }

        $validated = $request->validate([
            'plan_code' => ['required', 'string', 'max:40'],
            'billing' => ['nullable', 'string', 'in:monthly,yearly'],
        ]);

        $result = $this->mpCheckoutService->startCheckout(
            $family,
            $request->user(),
            (string) $validated['plan_code'],
            (string) ($validated['billing'] ?? 'monthly'),
        );

        return response()->json([
            'data' => [
                'success' => true,
                'message' => 'Abre Mercado Pago para completar el pago.',
                'plan_code' => $validated['plan_code'],
                'billing' => ($validated['billing'] ?? 'monthly') === 'yearly' ? 'yearly' : 'monthly',
                'mode' => 'mercadopago',
                'checkout_url' => $result['checkout_url'],
                'preference_id' => $result['preference_id'],
                'payment_id' => $result['payment_id'],
                'payment' => $result['payment'],
            ],
        ], 201);
    }

    /** Página de retorno para WebView tras Checkout Pro. */
    public function mercadoPagoReturn(Request $request): Response
    {
        $resultRaw = (string) $request->query('result', 'pending');
        $result = in_array($resultRaw, ['success', 'failure', 'pending'], true) ? $resultRaw : 'pending';
        $paymentId = e((string) $request->query('payment_id', ''));
        $title = match ($result) {
            'success' => 'Pago aprobado',
            'failure' => 'Pago no completado',
            default => 'Pago pendiente',
        };
        $hint = match ($result) {
            'success' => 'Ya puedes cerrar esta ventana. Tu plan se activará en unos segundos.',
            'failure' => 'Puedes cerrar esta ventana e intentar de nuevo desde la app.',
            default => 'Puedes cerrar esta ventana. Revisaremos el estado del pago.',
        };

        $html = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{$title}</title>
  <style>
    body { font-family: system-ui, sans-serif; text-align: center; padding: 48px 24px; color: #1a1a1a; }
    h1 { font-size: 1.4rem; margin-bottom: 8px; }
    p { color: #555; }
  </style>
</head>
<body data-mp-return="{$result}" data-payment-id="{$paymentId}">
  <h1>{$title}</h1>
  <p>{$hint}</p>
</body>
</html>
HTML;

        return response($html, 200)->header('Content-Type', 'text/html; charset=UTF-8');
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
