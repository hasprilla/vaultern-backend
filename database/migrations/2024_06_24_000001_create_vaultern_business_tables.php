<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('family_id')->nullable()->after('id');
            $table->string('role', 20)->default('padre')->after('password');
            $table->string('avatar')->nullable()->after('role');
            $table->boolean('mfa_enabled')->default(false)->after('avatar');
            $table->string('mfa_secret')->nullable()->after('mfa_enabled');
            $table->string('device_fingerprint')->nullable()->after('mfa_secret');
        });

        Schema::create('families', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('plan', 30)->default('free');
            $table->string('invite_code', 12)->unique();
            $table->string('timezone', 50)->default('America/Bogota');
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('family_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('family_id')->constrained('families')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20);
            $table->string('status', 20)->default('active');
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamps();
            $table->unique(['family_id', 'user_id']);
        });

        Schema::create('api_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name')->default('mobile');
            $table->string('token', 64)->unique();
            $table->string('refresh_token', 64)->unique()->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('family_id')->constrained('families')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('priority', 20)->default('media');
            $table->boolean('is_school')->default(false);
            $table->string('subject')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['family_id', 'status', 'due_date']);
        });

        Schema::create('ocr_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('family_id')->constrained('families')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->string('type', 30);
            $table->string('status', 20)->default('queued');
            $table->string('file_path')->nullable();
            $table->string('mime_type')->nullable();
            $table->json('raw_text')->nullable();
            $table->json('structured_data')->nullable();
            $table->float('confidence')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('family_id')->constrained('families')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('COP');
            $table->string('type', 20);
            $table->string('category', 50)->nullable();
            $table->string('description')->nullable();
            $table->date('transaction_date');
            $table->uuid('ocr_job_id')->nullable();
            $table->timestamps();
            $table->index(['family_id', 'transaction_date']);
        });

        Schema::create('budgets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('family_id')->constrained('families')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('COP');
            $table->string('period', 20)->default('monthly');
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('family_id')->constrained('families')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->string('type', 50);
            $table->string('title');
            $table->text('body');
            $table->json('data')->nullable();
            $table->boolean('read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('parent_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('family_id')->constrained('families')->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users');
            $table->text('message');
            $table->string('priority', 20)->default('normal');
            $table->boolean('read')->default(false);
            $table->timestamps();
        });

        Schema::create('devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('device_fingerprint');
            $table->string('fcm_token')->nullable();
            $table->string('platform', 20)->nullable();
            $table->boolean('is_trusted')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'device_fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
        Schema::dropIfExists('parent_messages');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('budgets');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('ocr_jobs');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('api_tokens');
        Schema::dropIfExists('family_members');
        Schema::dropIfExists('families');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['family_id', 'role', 'avatar', 'mfa_enabled', 'mfa_secret', 'device_fingerprint']);
        });
    }
};
