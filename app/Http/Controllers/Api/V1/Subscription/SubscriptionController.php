<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Subscription;

use App\Http\Controllers\Controller;
use App\Models\Family;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Application\Subscription\Actions\DeleteSavedPaymentMethodAction;
use App\Application\Subscription\Actions\SchedulePlanChangeAction;
use App\Application\Subscription\GetPaymentReceiptAction;
use App\Application\Subscription\StartWompiCheckoutAction;
use App\Application\Subscription\SyncWompiPaymentAction;
use App\Application\Subscription\WompiCheckoutService;
use App\Http\Requests\Api\V1\Subscription\StartWompiCheckoutRequest;
use App\Models\FamilyPaymentMethod;
use App\Services\FamilyPaymentMethodService;
use App\Services\PlanFeatureService;
use App\Services\SubscriptionBillingService;
use App\Services\SubscriptionCancelService;
use App\Services\SubscriptionCheckoutService;
use App\Services\SubscriptionRenewalService;
use App\Support\CardMask;
use App\Support\SubscriptionPlanCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly PlanFeatureService $planFeatures,
        private readonly SubscriptionCheckoutService $checkoutService,
        private readonly WompiCheckoutService $wompiCheckoutService,
        private readonly StartWompiCheckoutAction $startWompiCheckoutAction,
        private readonly SyncWompiPaymentAction $syncWompiPaymentAction,
        private readonly GetPaymentReceiptAction $getPaymentReceiptAction,
        private readonly DeleteSavedPaymentMethodAction $deleteSavedPaymentMethod,
        private readonly SchedulePlanChangeAction $schedulePlanChange,
        private readonly FamilyPaymentMethodService $paymentMethods,
        private readonly SubscriptionCancelService $cancelService,
        private readonly SubscriptionRenewalService $renewalService,
        private readonly SubscriptionBillingService $billingService,
    ) {}

    public function plans(): JsonResponse
    {
        SubscriptionPlanCatalog::ensureSeeded();

        $plans = Cache::remember('subscription_plans.active.v1', 300, static function () {
            return SubscriptionPlan::query()
                ->where('is_active', true)
                ->whereNotIn('code', ['school'])
                ->orderBy('sort_order')
                ->get();
        });

        $wompiEnabled = (bool) config('wompi.enabled');

        return response()->json([
            'data' => $plans,
            'meta' => [
                'checkout' => $wompiEnabled ? 'wompi' : 'simulated',
                'wompi_enabled' => $wompiEnabled,
                'currency' => 'COP',
            ],
        ]);
    }

    public function checkoutConfig(): JsonResponse
    {
        $wompiEnabled = (bool) config('wompi.enabled')
            && filled(config('wompi.public_key'))
            && filled(config('wompi.private_key'))
            && filled(config('wompi.integrity_secret'));

        return response()->json([
            'data' => [
                'checkout' => $wompiEnabled ? 'wompi' : 'simulated',
                'wompi_enabled' => $wompiEnabled,
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

        $methods = $this->paymentMethods->listActiveApi($family);
        $defaultId = collect($methods)->firstWhere('is_default', true)['id'] ?? null;

        $savedMethod = null;
        if ($defaultId !== null) {
            $savedMethod = collect($methods)->firstWhere('id', $defaultId);
        } elseif ($subscription?->renewal_card_last4) {
            $savedMethod = [
                'brand' => $subscription->renewal_card_brand,
                'last4' => $subscription->renewal_card_last4,
                'holder_name' => $subscription->renewal_card_holder_name,
                'masked' => CardMask::display(
                    $subscription->renewal_card_brand,
                    $subscription->renewal_card_last4,
                ),
            ];
        }

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
                'past_due' => $subscription?->isPastDue() ?? false,
                'renewal_grace_ends_at' => $subscription?->renewal_grace_ends_at?->toIso8601String(),
                'pending_plan_code' => $subscription?->pending_plan_code,
                'pending_billing' => $subscription?->pending_billing,
                'pending_change_at' => $subscription?->pending_plan_code
                    ? $subscription->accessUntilDate()
                    : null,
                'saved_payment_method' => $savedMethod,
                'saved_payment_methods' => $methods,
                'default_payment_method_id' => $defaultId,
            ],
        ]);
    }

    public function paymentMethod(Request $request): JsonResponse
    {
        $family = $this->resolveFamily($request);
        if ($family === null) {
            return response()->json(['message' => 'Familia no encontrada'], 404);
        }

        $methods = $this->paymentMethods->listActiveApi($family);
        $default = collect($methods)->firstWhere('is_default', true) ?? ($methods[0] ?? null);

        return response()->json(['data' => $default]);
    }

    public function paymentMethods(Request $request): JsonResponse
    {
        $family = $this->resolveFamily($request);
        if ($family === null) {
            return response()->json(['message' => 'Familia no encontrada'], 404);
        }

        return response()->json([
            'data' => $this->paymentMethods->listActiveApi($family),
        ]);
    }

    public function storePaymentMethod(Request $request): JsonResponse
    {
        $family = $this->resolveFamily($request);
        if ($family === null) {
            return response()->json(['message' => 'Familia no encontrada'], 404);
        }

        $validated = $request->validate([
            'token' => ['nullable', 'string', 'max:120'],
            'last4' => ['required', 'string', 'size:4'],
            'brand' => ['nullable', 'string', 'max:20'],
            'holder_name' => ['nullable', 'string', 'max:120'],
            'customer_email' => ['nullable', 'email'],
            'make_default' => ['nullable', 'boolean'],
            // Simulado: número completo solo para validar y guardar last4 (nunca se persiste PAN).
            'card_number' => ['nullable', 'string', 'min:13', 'max:23'],
            'exp_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'exp_year' => ['nullable', 'integer'],
            'cvc' => ['nullable', 'string', 'min:3', 'max:4'],
            'cardholder_name' => ['nullable', 'string', 'min:3', 'max:120'],
        ]);

        $makeDefault = (bool) ($validated['make_default'] ?? true);

        if (filled($validated['token'] ?? null)) {
            $method = $this->paymentMethods->createFromWompiToken($family, $request->user(), [
                'token' => $validated['token'],
                'last4' => $validated['last4'],
                'brand' => $validated['brand'] ?? null,
                'holder' => $validated['holder_name'] ?? null,
                'customer_email' => $validated['customer_email'] ?? null,
            ], $makeDefault);
        } else {
            if (config('wompi.enabled')) {
                return response()->json([
                    'message' => 'Con Wompi activo debes enviar un token de tarjeta (tok_…).',
                ], 422);
            }

            $cardMeta = app(\App\Services\SimulatedCardPaymentService::class)->validate([
                'card_number' => $validated['card_number'] ?? '',
                'exp_month' => $validated['exp_month'] ?? 0,
                'exp_year' => $validated['exp_year'] ?? 0,
                'cvc' => $validated['cvc'] ?? '',
                'cardholder_name' => $validated['cardholder_name'] ?? ($validated['holder_name'] ?? ''),
            ]);

            $method = $this->paymentMethods->createSimulated($family, $request->user(), $cardMeta, $makeDefault);
        }

        return response()->json(['data' => $method->toApiArray()], 201);
    }

    public function setDefaultPaymentMethod(Request $request, FamilyPaymentMethod $paymentMethod): JsonResponse
    {
        $family = $this->resolveFamily($request);
        if ($family === null) {
            return response()->json(['message' => 'Familia no encontrada'], 404);
        }

        if ($paymentMethod->family_id !== $family->id) {
            return response()->json(['message' => 'Método de pago no encontrado'], 404);
        }

        $method = $this->paymentMethods->setDefault($family, $paymentMethod);

        return response()->json(['data' => $method->toApiArray()]);
    }

    public function destroyPaymentMethod(Request $request, FamilyPaymentMethod $paymentMethod): JsonResponse
    {
        $family = $this->resolveFamily($request);
        if ($family === null) {
            return response()->json(['message' => 'Familia no encontrada'], 404);
        }

        $result = $this->deleteSavedPaymentMethod->executeOne($family, $request->user(), $paymentMethod->id);
        if (($result['ok'] ?? false) !== true) {
            return response()->json(['message' => $result['message']], $result['status']);
        }

        return response()->json(['message' => 'Tarjeta eliminada.']);
    }

    public function deletePaymentMethod(Request $request): JsonResponse
    {
        $family = $this->resolveFamily($request);
        if ($family === null) {
            return response()->json(['message' => 'Familia no encontrada'], 404);
        }

        $result = $this->deleteSavedPaymentMethod->execute($family, $request->user());
        if (($result['ok'] ?? false) !== true) {
            return response()->json(['message' => $result['message']], $result['status']);
        }

        return response()->json(['message' => 'Tarjeta eliminada. No se guardó ningún dato sensible del PAN.']);
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
            'save_card' => ['nullable', 'boolean'],
            'payment_method_id' => ['nullable', 'uuid'],
            'card_number' => ['required_without:payment_method_id', 'string', 'min:13', 'max:23'],
            'exp_month' => ['required_without:payment_method_id', 'integer', 'min:1', 'max:12'],
            'exp_year' => ['required_without:payment_method_id', 'integer', 'min:'.(int) date('Y'), 'max:'.((int) date('Y') + 20)],
            'cvc' => ['required_without:payment_method_id', 'string', 'min:3', 'max:4'],
            'cardholder_name' => ['required_without:payment_method_id', 'string', 'min:3', 'max:120'],
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

    public function checkoutWompi(StartWompiCheckoutRequest $request): JsonResponse
    {
        $family = $this->resolveFamily($request);
        if ($family === null) {
            return response()->json(['message' => 'Familia no encontrada'], 404);
        }

        $validated = $request->validated();

        $result = $this->startWompiCheckoutAction->execute(
            $family,
            $request->user(),
            (string) $validated['plan_code'],
            (string) ($validated['billing'] ?? 'monthly'),
            array_key_exists('save_card', $validated) ? (bool) $validated['save_card'] : true,
            isset($validated['payment_method_id']) ? (string) $validated['payment_method_id'] : null,
        );

        return response()->json([
            'data' => [
                'success' => true,
                'message' => 'Abre Wompi para completar el pago.',
                'plan_code' => $validated['plan_code'],
                'billing' => ($validated['billing'] ?? 'monthly') === 'yearly' ? 'yearly' : 'monthly',
                'mode' => 'wompi',
                'checkout_url' => $result['checkout_url'],
                'reference' => $result['reference'],
                'payment_id' => $result['payment_id'],
                'payment' => $result['payment'],
            ],
        ], 201);
    }

    /** Sincroniza estado del pago con Wompi (fallback si no llega el webhook). */
    public function syncWompiPayment(Request $request, SubscriptionPayment $payment): JsonResponse
    {
        $family = $this->resolveFamily($request);
        if ($family === null || $payment->family_id !== $family->id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        if ($payment->provider !== 'wompi') {
            return response()->json(['message' => 'Pago no es de Wompi'], 422);
        }

        $transactionId = $request->input('transaction_id');
        $result = $this->syncWompiPaymentAction->execute(
            $payment,
            is_string($transactionId) ? $transactionId : null,
        );

        return response()->json([
            'data' => [
                'synced' => $result['synced'],
                'status' => $result['status'],
                'payment' => $result['payment'],
            ],
        ]);
    }

    /** Página HTML que auto-envía el formulario Web Checkout de Wompi (WebView). */
    public function wompiPay(SubscriptionPayment $payment): Response
    {
        try {
            $fields = $this->wompiCheckoutService->checkoutFormFields($payment);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? 'Pago no disponible';

            return response($this->simpleHtml('Checkout no disponible', (string) $message), 422)
                ->header('Content-Type', 'text/html; charset=UTF-8');
        }

        $action = e((string) config('wompi.checkout_url'));
        $inputs = '';
        foreach ($fields as $name => $value) {
            if ($value === '' || $value === null) {
                continue;
            }
            $inputs .= '<input type="hidden" name="'.e((string) $name).'" value="'.e((string) $value).'" />'."\n";
        }

        $html = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Redirigiendo a Wompi…</title>
  <style>
    body { font-family: system-ui, sans-serif; text-align: center; padding: 48px 24px; color: #1a1a1a; }
    p { color: #555; }
  </style>
</head>
<body>
  <p>Abriendo checkout seguro de Wompi…</p>
  <form id="wompi-checkout" action="{$action}" method="GET">
    {$inputs}
    <noscript><button type="submit">Continuar a Wompi</button></noscript>
  </form>
  <script>document.getElementById('wompi-checkout').submit();</script>
</body>
</html>
HTML;

        return response($html, 200)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    /** Página de retorno para WebView tras Web Checkout Wompi (?id=transaction_id). */
    public function wompiReturn(Request $request): Response
    {
        $paymentIdRaw = (string) $request->query('payment_id', '');
        $transactionId = (string) $request->query('id', '');
        $result = 'pending';

        if ($paymentIdRaw !== '') {
            $local = SubscriptionPayment::query()->find($paymentIdRaw);
            if ($local !== null && $local->provider === 'wompi') {
                try {
                    $synced = $this->wompiCheckoutService->syncPayment(
                        $local,
                        $transactionId !== '' ? $transactionId : null,
                    );
                    $result = match ($synced['status'] ?? '') {
                        'succeeded' => 'success',
                        'failed' => 'failure',
                        default => 'pending',
                    };
                } catch (\Throwable) {
                    // La página de retorno no debe romper aunque falle el sync.
                }
            }
        } elseif ($transactionId !== '') {
            // Fallback: solo tenemos id de transacción Wompi.
            try {
                $txPayment = SubscriptionPayment::query()
                    ->where('metadata->wompi_transaction_id', $transactionId)
                    ->first();
                if ($txPayment === null) {
                    // Intentar sync vía reference no disponible; dejar pending.
                    $result = 'pending';
                } else {
                    $synced = $this->wompiCheckoutService->syncPayment($txPayment, $transactionId);
                    $result = match ($synced['status'] ?? '') {
                        'succeeded' => 'success',
                        'failed' => 'failure',
                        default => 'pending',
                    };
                    $paymentIdRaw = $txPayment->id;
                }
            } catch (\Throwable) {
            }
        }

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

        $paymentId = e($paymentIdRaw);
        $txEscaped = e($transactionId);

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
<body data-wompi-return="{$result}" data-payment-id="{$paymentId}" data-transaction-id="{$txEscaped}">
  <h1>{$title}</h1>
  <p>{$hint}</p>
</body>
</html>
HTML;

        return response($html, 200)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    public function scheduleChange(Request $request): JsonResponse
    {
        $family = $this->resolveFamily($request);
        if ($family === null) {
            return response()->json(['message' => 'Familia no encontrada'], 404);
        }

        $validated = $request->validate([
            'plan_code' => ['required', 'string', 'max:40'],
            'billing' => ['nullable', 'string', 'in:monthly,yearly'],
        ]);

        $result = $this->schedulePlanChange->execute(
            $family,
            $request->user(),
            (string) $validated['plan_code'],
            (string) ($validated['billing'] ?? 'monthly'),
        );

        return response()->json([
            'message' => "Cambio a {$result['plan_name']} programado. "
                .'Se cobrará y aplicará el '.($result['pending_change_at'] ?? 'vencimiento').'.',
            'data' => $result,
        ]);
    }

    public function cancelScheduledChange(Request $request): JsonResponse
    {
        $family = $this->resolveFamily($request);
        if ($family === null) {
            return response()->json(['message' => 'Familia no encontrada'], 404);
        }

        $result = $this->schedulePlanChange->cancel($family, $request->user());
        if (($result['ok'] ?? false) !== true) {
            return response()->json(['message' => 'No hay un cambio de plan programado.'], 404);
        }

        return response()->json([
            'message' => 'Cambio de plan cancelado. Se renovará el plan actual.',
            'data' => ['ok' => true],
        ]);
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

        // Pagos pending del mismo plan activo: cerrar como fallidos (evita fantasma al recomprar).
        $activePlan = $family->subscription?->hasPaidAccess()
            ? $family->subscription->plan_code
            : null;
        if ($activePlan !== null) {
            SubscriptionPayment::query()
                ->where('family_id', $family->id)
                ->where('status', 'pending')
                ->where('plan_code', $activePlan)
                ->where('created_at', '<', now()->subMinutes(5))
                ->update([
                    'status' => 'failed',
                    'failure_reason' => 'Plan ya activo; cobro omitido.',
                ]);
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

    public function paymentReceipt(Request $request, SubscriptionPayment $payment): Response|JsonResponse
    {
        $result = $this->getPaymentReceiptAction->execute($request->user(), $payment);

        if (($result['ok'] ?? false) !== true) {
            return response()->json(['message' => $result['message']], $result['status']);
        }

        return response($result['pdf'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$result['filename'].'"',
        ]);
    }

    private function simpleHtml(string $title, string $hint): string
    {
        $t = e($title);
        $h = e($hint);

        return <<<HTML
<!DOCTYPE html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{$t}</title></head>
<body style="font-family:system-ui;text-align:center;padding:48px 24px"><h1>{$t}</h1><p>{$h}</p></body></html>
HTML;
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
