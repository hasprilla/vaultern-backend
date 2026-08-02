<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('family_payment_methods')) {
            Schema::create('family_payment_methods', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('family_id')->constrained('families')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('provider', 32)->default('simulated');
                $table->string('provider_payment_source_id', 64)->nullable();
                $table->string('brand', 20)->nullable();
                $table->string('last4', 4);
                $table->string('holder_name', 120)->nullable();
                $table->boolean('is_default')->default(false);
                $table->string('status', 20)->default('active');
                $table->timestamps();

                $table->index(['family_id', 'status']);
                $table->index(['family_id', 'is_default']);
            });
        }

        Schema::table('subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('subscriptions', 'renewal_grace_ends_at')) {
                $table->timestamp('renewal_grace_ends_at')->nullable()->after('renewal_user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (Schema::hasColumn('subscriptions', 'renewal_grace_ends_at')) {
                $table->dropColumn('renewal_grace_ends_at');
            }
        });

        Schema::dropIfExists('family_payment_methods');
    }
};
