<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('device_login_challenges')) {
            return;
        }

        Schema::create('device_login_challenges', function (Blueprint $table) {
            $table->id();
            $table->string('token_hash', 64)->unique();
            $table->unsignedBigInteger('user_id');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['user_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_login_challenges');
    }
};
