<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Subscription;

use App\Application\Subscription\HandleWompiWebhookAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class WompiWebhookController extends Controller
{
    public function __construct(
        private readonly HandleWompiWebhookAction $handleWebhook,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $this->handleWebhook->execute(
                $request->all(),
                $request->header('X-Event-Checksum'),
            );
        } catch (Throwable $e) {
            Log::error('wompi.webhook.exception', [
                'message' => $e->getMessage(),
            ]);

            return response()->json(['ok' => false, 'error' => 'logged']);
        }

        return response()->json(['ok' => true]);
    }
}
