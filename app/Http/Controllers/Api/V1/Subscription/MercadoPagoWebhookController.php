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

            // MP reintenta si no es 2xx; devolvemos 200 tras log para evitar loops
            // solo si fue error de parseo; si fue error de API MP, 500 para reintento.
            return response()->json(['ok' => false], 500);
        }

        return response()->json(['ok' => true]);
    }
}
