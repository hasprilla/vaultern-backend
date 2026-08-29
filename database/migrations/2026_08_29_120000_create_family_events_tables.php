<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('family_events')) {
            Schema::create('family_events', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('family_id')->constrained('families')->cascadeOnDelete();
                $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
                $table->string('title');
                $table->text('description')->nullable();
                $table->timestamp('starts_at');
                $table->timestamp('ends_at')->nullable();
                $table->string('location')->nullable();
                $table->string('status', 32)->default('scheduled');
                $table->timestamps();

                $table->index(['family_id', 'starts_at']);
                $table->index(['family_id', 'status']);
            });
        }

        if (! Schema::hasTable('family_event_guests')) {
            Schema::create('family_event_guests', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('event_id')->constrained('family_events')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('name');
                $table->string('email')->nullable();
                $table->string('phone', 40)->nullable();
                $table->string('status', 32)->default('pending');
                $table->text('note')->nullable();
                $table->timestamp('invited_at')->nullable();
                $table->timestamp('responded_at')->nullable();
                $table->timestamps();

                $table->index(['event_id', 'status']);
                $table->index(['user_id']);
                $table->unique(['event_id', 'email']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('family_event_guests');
        Schema::dropIfExists('family_events');
    }
};
