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
            'Renovaciones: %d procesadas, %d exitosas, %d fallidas.',
            $stats['processed'],
            $stats['renewed'],
            $stats['failed'],
        ));

        return self::SUCCESS;
    }
}
