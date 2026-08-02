<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->index(['provider', 'status'], 'subscription_payments_provider_status_idx');
            $table->index(['family_id', 'provider', 'created_at'], 'subscription_payments_family_provider_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->dropIndex('subscription_payments_provider_status_idx');
            $table->dropIndex('subscription_payments_family_provider_created_idx');
        });
    }
};
