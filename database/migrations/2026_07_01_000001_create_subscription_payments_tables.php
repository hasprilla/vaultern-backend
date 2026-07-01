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
            if (! Schema::hasColumn('subscriptions', 'billing')) {
                $table->string('billing', 20)->nullable()->after('plan_code');
            }
        });

        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('family_id')->constrained('families')->cascadeOnDelete();
            $table->foreignUuid('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users');
            $table->string('plan_code', 40);
            $table->string('billing', 20)->default('monthly');
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3)->default('EUR');
            $table->string('status', 20)->default('pending');
            $table->string('provider', 20)->default('simulated');
            $table->string('card_brand', 20)->nullable();
            $table->string('card_last4', 4)->nullable();
            $table->string('card_holder_name')->nullable();
            $table->string('payment_reference', 64)->unique();
            $table->string('failure_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['family_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('subscription_payment_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('payment_id')->constrained('subscription_payments')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->string('event_type', 40);
            $table->string('message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payment_events');
        Schema::dropIfExists('subscription_payments');

        Schema::table('subscriptions', function (Blueprint $table) {
            if (Schema::hasColumn('subscriptions', 'billing')) {
                $table->dropColumn('billing');
            }
        });
    }
};
