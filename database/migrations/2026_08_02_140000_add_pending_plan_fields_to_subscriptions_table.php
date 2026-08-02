<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('subscriptions', 'pending_plan_code')) {
                $table->string('pending_plan_code', 40)->nullable()->after('renewal_grace_ends_at');
            }
            if (! Schema::hasColumn('subscriptions', 'pending_billing')) {
                $table->string('pending_billing', 20)->nullable()->after('pending_plan_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            foreach (['pending_billing', 'pending_plan_code'] as $column) {
                if (Schema::hasColumn('subscriptions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
