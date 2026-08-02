<?php

declare(strict_types=1);

namespace App\Application\Subscription\Actions;

use App\Models\Family;
use App\Models\User;
use App\Services\FamilyNotificationService;
use App\Services\SubscriptionBillingService;

/**
 * @phpstan-type DeleteSuccess array{ok: true}
 * @phpstan-type DeleteFailure array{ok: false, status: int, message: string}
 */
final class DeleteSavedPaymentMethodAction
{
    public function __construct(
        private readonly SubscriptionBillingService $billing,
        private readonly FamilyNotificationService $notifications,
    ) {}

    /**
     * @return DeleteSuccess|DeleteFailure
     */
    public function execute(Family $family, User $user): array
    {
        $subscription = $family->subscription;
        if ($subscription === null || $subscription->renewal_card_last4 === null) {
            return ['ok' => false, 'status' => 404, 'message' => 'No hay una tarjeta guardada.'];
        }

        $this->billing->clearPaymentMethod($subscription);

        $this->notifications->notifyFamilyById(
            (string) $family->id,
            null,
            'payment_method_removed',
            'Tarjeta eliminada',
            "{$user->name} eliminó la tarjeta guardada para renovaciones. No se realizarán cobros automáticos hasta que se guarde otra.",
            ['entity_type' => 'subscription', 'entity_id' => $subscription->id],
        );

        return ['ok' => true];
    }
}
