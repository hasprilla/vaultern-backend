<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('family_event_expenses')) {
            return;
        }
        Schema::create('family_event_expenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('event_id')->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('title', 160);
            $table->decimal('amount', 12, 2);
            $table->string('currency', 8)->default('COP');
            $table->string('category', 40)->nullable();
            $table->boolean('paid')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_event_expenses');
    }
};
