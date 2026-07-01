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
            if (! Schema::hasColumn('subscriptions', 'renewal_card_last4')) {
                $table->string('renewal_card_last4', 4)->nullable()->after('cancelled_at');
            }
            if (! Schema::hasColumn('subscriptions', 'renewal_card_brand')) {
                $table->string('renewal_card_brand', 20)->nullable()->after('renewal_card_last4');
            }
            if (! Schema::hasColumn('subscriptions', 'renewal_card_holder_name')) {
                $table->string('renewal_card_holder_name', 120)->nullable()->after('renewal_card_brand');
            }
            if (! Schema::hasColumn('subscriptions', 'renewal_user_id')) {
                $table->foreignId('renewal_user_id')->nullable()->after('renewal_card_holder_name')
                    ->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (Schema::hasColumn('subscriptions', 'renewal_user_id')) {
                $table->dropConstrainedForeignId('renewal_user_id');
            }
            foreach (['renewal_card_holder_name', 'renewal_card_brand', 'renewal_card_last4'] as $column) {
                if (Schema::hasColumn('subscriptions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
