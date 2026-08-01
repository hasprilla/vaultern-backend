<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Support\SubscriptionPlanCatalog;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        SubscriptionPlanCatalog::ensureSeeded();
    }
}
