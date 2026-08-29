<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('family_reward_items')) {
            Schema::create('family_reward_items', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('family_id')->index();
                $table->string('title', 120);
                $table->unsignedInteger('cost_points');
                $table->boolean('active')->default(true);
                $table->timestamps();
                $table->index(['family_id', 'active']);
            });
        }

        if (! Schema::hasTable('family_reward_settings')) {
            Schema::create('family_reward_settings', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('family_id')->unique();
                $table->unsignedInteger('points_per_task')->default(10);
                $table->decimal('allowance_per_task', 12, 2)->default(500);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('family_reward_settings');
        Schema::dropIfExists('family_reward_items');
    }
};
