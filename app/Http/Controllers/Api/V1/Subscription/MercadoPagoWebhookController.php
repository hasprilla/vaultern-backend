<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Subscription;

use App\Http\Controllers\Controller;
use App\Services\MercadoPago\MercadoPagoCheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class MercadoPagoWebhookController extends Controller
{
    public function __construct(
        private readonly MercadoPagoCheckoutService $checkoutService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $this->checkoutService->handleWebhook(
                $request->all(),
                $request->query('topic') ?? $request->query('type'),
                $request->query('id') ?? $request->query('data_id'),
            );
        } catch (Throwable $e) {
            Log::error('mp.webhook.exception', [
                'message' => $e->getMessage(),
            ]);

            // 200 para no saturar reintentos de MP ante ids inválidos / ruido.
            return response()->json(['ok' => false, 'error' => 'logged']);
        }

        return response()->json(['ok' => true]);
    }
}
