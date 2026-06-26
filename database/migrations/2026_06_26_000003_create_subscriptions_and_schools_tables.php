<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->unsignedInteger('price_monthly_cents')->default(0);
            $table->unsignedInteger('price_yearly_cents')->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('family_id')->constrained('families')->cascadeOnDelete();
            $table->string('plan_code', 40);
            $table->string('status', 20)->default('active');
            $table->string('provider', 20)->default('manual');
            $table->string('provider_subscription_id')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamps();
            $table->index(['family_id', 'status']);
        });

        Schema::create('schools', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('code', 12)->unique();
            $table->string('plan', 30)->default('school');
            $table->string('city')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('school_classes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('name');
            $table->string('grade', 20)->nullable();
            $table->string('section', 20)->nullable();
            $table->string('school_year', 20)->nullable();
            $table->foreignId('teacher_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['school_id', 'name']);
        });

        Schema::create('teacher_memberships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 20)->default('teacher');
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['school_id', 'user_id']);
        });

        Schema::create('class_enrollments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->foreignId('student_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('family_id')->constrained('families')->cascadeOnDelete();
            $table->foreignId('enrolled_by')->constrained('users');
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['school_class_id', 'student_user_id']);
        });

        Schema::create('school_task_broadcasts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignUuid('school_class_id')->nullable()->constrained('school_classes')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('subject')->nullable();
            $table->string('priority', 20)->default('media');
            $table->date('due_date')->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('tasks_total')->default(0);
            $table->unsignedInteger('tasks_created')->default(0);
            $table->timestamps();
            $table->index(['school_id', 'created_at']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignUuid('source_broadcast_id')->nullable()->after('family_id')
                ->constrained('school_task_broadcasts')->nullOnDelete();
            $table->foreignUuid('school_id')->nullable()->after('source_broadcast_id')
                ->constrained('schools')->nullOnDelete();
            $table->string('created_by_role', 20)->default('padre')->after('created_by');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_broadcast_id');
            $table->dropConstrainedForeignId('school_id');
            $table->dropColumn('created_by_role');
        });

        Schema::dropIfExists('school_task_broadcasts');
        Schema::dropIfExists('class_enrollments');
        Schema::dropIfExists('teacher_memberships');
        Schema::dropIfExists('school_classes');
        Schema::dropIfExists('schools');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('subscription_plans');
    }
};
