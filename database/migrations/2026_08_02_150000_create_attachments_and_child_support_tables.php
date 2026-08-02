<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('family_id')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('attachable_type');
            $table->uuid('attachable_id');
            $table->string('disk', 40)->default('public');
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('kind', 40)->default('image'); // image|document|receipt|agreement
            $table->timestamps();

            $table->index(['attachable_type', 'attachable_id']);
        });

        Schema::create('child_support_agreements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('family_id')->index();
            $table->unsignedBigInteger('child_id')->index();
            $table->unsignedBigInteger('payer_user_id')->index();
            $table->unsignedBigInteger('beneficiary_user_id')->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->decimal('initial_amount', 12, 2);
            $table->string('currency', 3)->default('COP');
            $table->decimal('default_annual_increase_pct', 8, 4)->default(0);
            $table->date('starts_on');
            $table->string('status', 20)->default('active'); // active|ended
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['family_id', 'child_id', 'status']);
        });

        Schema::create('child_support_adjustments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('agreement_id')->index();
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->decimal('increase_pct', 8, 4);
            $table->decimal('amount_after', 12, 2);
            $table->date('effective_on');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('child_support_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('agreement_id')->index();
            $table->uuid('family_id')->index();
            $table->unsignedBigInteger('child_id')->index();
            $table->unsignedBigInteger('paid_by')->nullable();
            $table->uuid('transaction_id')->nullable()->index();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('COP');
            $table->date('period_month'); // first day of month
            $table->date('paid_on');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['agreement_id', 'period_month'], 'child_support_payment_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('child_support_payments');
        Schema::dropIfExists('child_support_adjustments');
        Schema::dropIfExists('child_support_agreements');
        Schema::dropIfExists('attachments');
    }
};
