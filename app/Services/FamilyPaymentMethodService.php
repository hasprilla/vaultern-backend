<?php

declare(strict_types=1);

namespace App\Services;

use App\Infrastructure\Wompi\WompiHttpClient;
use App\Models\Family;
use App\Models\FamilyPaymentMethod;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class FamilyPaymentMethodService
{
    public function __construct(
        private readonly WompiHttpClient $wompi,
        private readonly FamilyNotificationService $notifications,
    ) {}

    /**
     * @return Collection<int, FamilyPaymentMethod>
     */
    public function listActive(Family $family): Collection
    {
        return FamilyPaymentMethod::query()
            ->where('family_id', $family->id)
            ->where('status', 'active')
            ->orderByDesc('is_default')
            ->orderBy('created_at')
            ->get();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listActiveApi(Family $family): array
    {
        return $this->listActive($family)->map(fn (FamilyPaymentMethod $m) => $m->toApiArray())->all();
    }

    /**
     * Guarda método simulado (solo last4/brand/holder).
     *
     * @param  array{last4: string, brand?: string|null, holder?: string|null}  $cardMeta
     */
    public function createSimulated(
        Family $family,
        User $user,
        array $cardMeta,
        bool $makeDefault = true,
    ): FamilyPaymentMethod {
        $last4 = substr((string) $cardMeta['last4'], -4);

        return DB::transaction(function () use ($family, $user, $cardMeta, $last4, $makeDefault) {
            $method = FamilyPaymentMethod::query()->create([
                'id' => (string) Str::uuid(),
                'family_id' => $family->id,
                'user_id' => $user->id,
                'provider' => 'simulated',
                'provider_payment_source_id' => null,
                'brand' => $cardMeta['brand'] ?? null,
                'last4' => $last4,
                'holder_name' => $cardMeta['holder'] ?? null,
                'is_default' => false,
                'status' => 'active',
            ]);

            if ($makeDefault || $this->listActive($family)->count() === 1) {
                $this->setDefault($family, $method);
            }

            return $method->fresh();
        });
    }

    /**
     * Crea Payment Source en Wompi y lo persiste.
     *
     * @param  array{token: string, last4: string, brand?: string|null, holder?: string|null, customer_email?: string|null}  $input
     */
    public function createFromWompiToken(
        Family $family,
        User $user,
        array $input,
        bool $makeDefault = true,
    ): FamilyPaymentMethod {
        if (! $this->wompi->isConfigured()) {
            throw ValidationException::withMessages([
                'token' => 'Wompi no está configurado.',
            ]);
        }

        $token = trim((string) ($input['token'] ?? ''));
        $last4 = substr((string) ($input['last4'] ?? ''), -4);
        if ($token === '' || strlen($last4) !== 4) {
            throw ValidationException::withMessages([
                'token' => 'Token y últimos 4 dígitos son obligatorios.',
            ]);
        }

        $email = (string) ($input['customer_email'] ?? $user->email);
        try {
            $source = $this->wompi->createPaymentSource($token, $email);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages([
                'token' => $e->getMessage(),
            ]);
        }

        $sourceId = (string) ($source['id'] ?? '');
        if ($sourceId === '') {
            throw ValidationException::withMessages([
                'token' => 'Wompi no devolvió un payment_source_id.',
            ]);
        }

        return DB::transaction(function () use ($family, $user, $input, $last4, $sourceId, $makeDefault) {
            $method = FamilyPaymentMethod::query()->create([
                'id' => (string) Str::uuid(),
                'family_id' => $family->id,
                'user_id' => $user->id,
                'provider' => 'wompi',
                'provider_payment_source_id' => $sourceId,
                'brand' => $input['brand'] ?? null,
                'last4' => $last4,
                'holder_name' => $input['holder'] ?? null,
                'is_default' => false,
                'status' => 'active',
            ]);

            if ($makeDefault || $this->listActive($family)->count() === 1) {
                $this->setDefault($family, $method);
            }

            $this->notifications->notifyFamilyById(
                (string) $family->id,
                null,
                'payment_method_added',
                'Tarjeta guardada',
                "{$user->name} guardó una tarjeta •••• {$last4} para renovación automática.",
                ['entity_type' => 'family_payment_method', 'entity_id' => $method->id],
            );

            return $method->fresh();
        });
    }

    /**
     * Tras un pago Wompi con opt-in: guarda metadata si aún no hay source cobrable.
     *
     * @param  array{last4?: string, brand?: string|null, holder?: string|null}  $cardMeta
     */
    public function upsertFromCheckoutMeta(
        Family $family,
        User $user,
        array $cardMeta,
        string $provider,
        bool $makeDefault = true,
    ): ?FamilyPaymentMethod {
        if (! filled($cardMeta['last4'] ?? null)) {
            return null;
        }

        $last4 = substr((string) $cardMeta['last4'], -4);

        if ($provider === 'wompi') {
            // Sin payment_source_id no es cobrable; se guarda solo para UI hasta tokenizar.
            $method = FamilyPaymentMethod::query()->create([
                'id' => (string) Str::uuid(),
                'family_id' => $family->id,
                'user_id' => $user->id,
                'provider' => 'wompi',
                'provider_payment_source_id' => null,
                'brand' => $cardMeta['brand'] ?? null,
                'last4' => $last4,
                'holder_name' => $cardMeta['holder'] ?? null,
                'is_default' => false,
                'status' => 'active',
            ]);
        } else {
            $method = $this->createSimulated($family, $user, [
                'last4' => $last4,
                'brand' => $cardMeta['brand'] ?? null,
                'holder' => $cardMeta['holder'] ?? null,
            ], false);
        }

        if ($makeDefault) {
            $this->setDefault($family, $method);
        } else {
            $this->syncSubscriptionMirror($family);
        }

        return $method->fresh();
    }

    public function setDefault(Family $family, FamilyPaymentMethod $method): FamilyPaymentMethod
    {
        if ($method->family_id !== $family->id || ! $method->isActive()) {
            throw ValidationException::withMessages([
                'payment_method' => 'Método de pago no válido.',
            ]);
        }

        return DB::transaction(function () use ($family, $method) {
            FamilyPaymentMethod::query()
                ->where('family_id', $family->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);

            $method->update(['is_default' => true]);
            $this->syncSubscriptionMirror($family);

            return $method->fresh();
        });
    }

    public function delete(Family $family, FamilyPaymentMethod $method, User $user): void
    {
        if ($method->family_id !== $family->id) {
            throw ValidationException::withMessages([
                'payment_method' => 'Método de pago no válido.',
            ]);
        }

        DB::transaction(function () use ($family, $method, $user) {
            $wasDefault = (bool) $method->is_default;
            $method->delete();

            if ($wasDefault) {
                $next = $this->listActive($family)->first();
                if ($next !== null) {
                    $this->setDefault($family, $next);
                } else {
                    $this->clearSubscriptionMirror($family->subscription);
                }
            } else {
                $this->syncSubscriptionMirror($family);
            }

            $this->notifications->notifyFamilyById(
                (string) $family->id,
                null,
                'payment_method_removed',
                'Tarjeta eliminada',
                "{$user->name} eliminó la tarjeta •••• {$method->last4}.",
                ['entity_type' => 'family_payment_method', 'entity_id' => $method->id],
            );
        });
    }

    public function findForFamily(Family $family, string $id): ?FamilyPaymentMethod
    {
        return FamilyPaymentMethod::query()
            ->where('family_id', $family->id)
            ->where('id', $id)
            ->where('status', 'active')
            ->first();
    }

    /**
     * Métodos cobrables en orden: default primero.
     *
     * @return Collection<int, FamilyPaymentMethod>
     */
    public function chargeableOrdered(Family $family): Collection
    {
        return $this->listActive($family)->filter(fn (FamilyPaymentMethod $m) => $m->isChargeable())->values();
    }

    public function syncSubscriptionMirror(Family $family): void
    {
        $subscription = $family->subscription;
        if ($subscription === null) {
            return;
        }

        $default = FamilyPaymentMethod::query()
            ->where('family_id', $family->id)
            ->where('status', 'active')
            ->where('is_default', true)
            ->first()
            ?? $this->listActive($family)->first();

        if ($default === null) {
            $this->clearSubscriptionMirror($subscription);

            return;
        }

        $subscription->update([
            'renewal_card_last4' => $default->last4,
            'renewal_card_brand' => $default->brand,
            'renewal_card_holder_name' => $default->holder_name,
            'renewal_user_id' => $default->user_id,
        ]);
    }

    public function clearSubscriptionMirror(?Subscription $subscription): void
    {
        if ($subscription === null) {
            return;
        }

        $subscription->update([
            'renewal_card_last4' => null,
            'renewal_card_brand' => null,
            'renewal_card_holder_name' => null,
            'renewal_user_id' => null,
        ]);
    }
}
