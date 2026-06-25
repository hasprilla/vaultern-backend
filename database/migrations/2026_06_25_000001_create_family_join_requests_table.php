<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('family_join_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('family_id')->constrained('families')->cascadeOnDelete();
            $table->foreignId('invited_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('password');
            $table->string('role', 20);
            $table->string('status', 20)->default('pending');
            $table->timestamps();

            $table->index(['family_id', 'status']);
            $table->index(['invited_by_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_join_requests');
    }
};
