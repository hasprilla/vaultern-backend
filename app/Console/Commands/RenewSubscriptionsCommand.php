<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SubscriptionRenewalService;
use Illuminate\Console\Command;

class RenewSubscriptionsCommand extends Command
{
    protected $signature = 'subscriptions:renew';

    protected $description = 'Renueva suscripciones activas cuyo periodo de pago ha vencido';

    public function handle(SubscriptionRenewalService $renewals): int
    {
        $stats = $renewals->renewDueSubscriptions();

        $this->info(sprintf(
            'Suscripciones: %d procesadas, %d renovadas, %d fallidas, %d vencidas.',
            $stats['processed'],
            $stats['renewed'],
            $stats['failed'],
            $stats['expired'] ?? 0,
        ));

        return self::SUCCESS;
    }
}
