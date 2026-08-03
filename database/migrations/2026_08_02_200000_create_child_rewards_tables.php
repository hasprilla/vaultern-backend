<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('child_reward_balances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('family_id')->index();
            $table->unsignedBigInteger('child_user_id')->index();
            $table->unsignedInteger('points')->default(0);
            $table->decimal('allowance_balance', 12, 2)->default(0);
            $table->string('currency', 3)->default('COP');
            $table->timestamps();
            $table->unique(['family_id', 'child_user_id']);
        });

        Schema::create('child_reward_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('family_id')->index();
            $table->unsignedBigInteger('child_user_id')->index();
            $table->string('source_type', 32); // task_completed | adjustment
            $table->string('source_id')->nullable();
            $table->integer('points_delta')->default(0);
            $table->decimal('allowance_delta', 12, 2)->default(0);
            $table->string('note')->nullable();
            $table->timestamps();
            $table->unique(['family_id', 'source_type', 'source_id'], 'reward_events_source_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('child_reward_events');
        Schema::dropIfExists('child_reward_balances');
    }
};
