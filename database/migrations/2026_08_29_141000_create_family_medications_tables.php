<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('family_medications')) {
            Schema::create('family_medications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('family_id')->constrained('families')->cascadeOnDelete();
                $table->foreignId('patient_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
                $table->string('name');
                $table->string('dose_text')->nullable();
                $table->json('schedule_times')->nullable();
                $table->boolean('active')->default(true);
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index(['family_id', 'active']);
                $table->index(['family_id', 'patient_user_id']);
            });
        }

        if (! Schema::hasTable('family_medication_logs')) {
            Schema::create('family_medication_logs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('medication_id')->constrained('family_medications')->cascadeOnDelete();
                $table->foreignId('taken_by')->constrained('users')->cascadeOnDelete();
                $table->timestamp('taken_at');
                $table->string('note')->nullable();
                $table->timestamps();
                $table->index(['medication_id', 'taken_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('family_medication_logs');
        Schema::dropIfExists('family_medications');
    }
};
