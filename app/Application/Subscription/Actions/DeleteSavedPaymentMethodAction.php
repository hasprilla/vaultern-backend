<?php

declare(strict_types=1);

namespace App\Application\Subscription\Actions;

use App\Models\Family;
use App\Models\FamilyPaymentMethod;
use App\Models\User;
use App\Services\FamilyPaymentMethodService;
use App\Services\SubscriptionBillingService;

/**
 * Compat: elimina todas las tarjetas activas de la familia (endpoint singular antiguo).
 */
final class DeleteSavedPaymentMethodAction
{
    public function __construct(
        private readonly FamilyPaymentMethodService $paymentMethods,
        private readonly SubscriptionBillingService $billing,
    ) {}

    /**
     * @return array{ok: bool, status?: int, message?: string}
     */
    public function execute(Family $family, User $user): array
    {
        $methods = $this->paymentMethods->listActive($family);
        $hasMirror = $family->subscription?->renewal_card_last4 !== null;

        if ($methods->isEmpty() && ! $hasMirror) {
            return ['ok' => false, 'status' => 404, 'message' => 'No hay una tarjeta guardada.'];
        }

        foreach ($methods as $method) {
            $this->paymentMethods->delete($family, $method, $user);
        }

        if ($family->subscription !== null) {
            $this->billing->clearPaymentMethod($family->subscription);
        }

        return ['ok' => true];
    }

    /**
     * @return array{ok: bool, status?: int, message?: string}
     */
    public function executeOne(Family $family, User $user, string $methodId): array
    {
        $method = FamilyPaymentMethod::query()
            ->where('family_id', $family->id)
            ->where('id', $methodId)
            ->first();

        if ($method === null) {
            return ['ok' => false, 'status' => 404, 'message' => 'Método de pago no encontrado.'];
        }

        $this->paymentMethods->delete($family, $method, $user);

        return ['ok' => true];
    }
}
