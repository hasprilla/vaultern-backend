<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'document_number')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('document_type', 32)->nullable()->after('email');
                $table->string('document_number', 64)->nullable()->after('document_type');
                $table->index(['document_type', 'document_number']);
            });
        }

        if (! Schema::hasTable('school_campuses')) {
            Schema::create('school_campuses', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('school_id')->constrained('schools')->cascadeOnDelete();
                $table->string('name');
                $table->string('code', 32)->nullable();
                $table->string('city')->nullable();
                $table->string('address')->nullable();
                $table->boolean('is_main')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['school_id', 'code']);
            });
        }

        if (Schema::hasTable('schools') && ! Schema::hasColumn('schools', 'main_campus_id')) {
            Schema::table('schools', function (Blueprint $table) {
                $table->uuid('main_campus_id')->nullable()->after('city');
                $table->foreignId('created_by')->nullable()->after('main_campus_id')->constrained('users')->nullOnDelete();
            });
        }

        if (Schema::hasTable('school_classes') && ! Schema::hasColumn('school_classes', 'campus_id')) {
            Schema::table('school_classes', function (Blueprint $table) {
                $table->uuid('campus_id')->nullable()->after('school_id');
            });
        }

        if (! Schema::hasTable('school_staff_invites')) {
            Schema::create('school_staff_invites', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('school_id')->constrained('schools')->cascadeOnDelete();
                $table->uuid('campus_id')->nullable();
                $table->string('email');
                $table->string('role', 32)->default('docente');
                $table->string('invite_code', 16);
                $table->string('status', 24)->default('pending');
                $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
                $table->timestamp('accepted_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
                $table->unique(['school_id', 'email', 'status']);
                $table->unique('invite_code');
            });
        }

        if (! Schema::hasTable('school_groups')) {
            Schema::create('school_groups', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('school_id')->constrained('schools')->cascadeOnDelete();
                $table->uuid('campus_id')->nullable();
                $table->string('name');
                $table->string('type', 32)->default('students'); // students|teachers|mixed
                $table->text('description')->nullable();
                $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('school_group_members')) {
            Schema::create('school_group_members', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('school_group_id')->constrained('school_groups')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('member_role', 32)->default('member');
                $table->timestamps();
                $table->unique(['school_group_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('school_announcements')) {
            Schema::create('school_announcements', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('school_id')->constrained('schools')->cascadeOnDelete();
                $table->uuid('campus_id')->nullable();
                $table->uuid('school_class_id')->nullable();
                $table->uuid('school_group_id')->nullable();
                $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
                $table->string('type', 40); // announcement|no_class|activity|citation|sick|health|meeting
                $table->string('title');
                $table->text('body')->nullable();
                $table->json('data')->nullable();
                $table->timestamp('scheduled_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('school_meetings')) {
            Schema::create('school_meetings', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('school_id')->constrained('schools')->cascadeOnDelete();
                $table->uuid('campus_id')->nullable();
                $table->uuid('school_class_id')->nullable();
                $table->uuid('school_group_id')->nullable();
                $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
                $table->string('title');
                $table->text('description')->nullable();
                $table->timestamp('starts_at');
                $table->timestamp('ends_at')->nullable();
                $table->string('location')->nullable();
                $table->string('status', 24)->default('scheduled');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('school_meeting_rsvps')) {
            Schema::create('school_meeting_rsvps', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('school_meeting_id')->constrained('school_meetings')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('status', 24); // attending|not_attending|pending
                $table->text('observation')->nullable();
                $table->timestamp('responded_at')->nullable();
                $table->timestamps();
                $table->unique(['school_meeting_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('school_schedules')) {
            Schema::create('school_schedules', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('school_id')->constrained('schools')->cascadeOnDelete();
                $table->uuid('campus_id')->nullable();
                $table->uuid('school_class_id')->nullable();
                $table->string('title');
                $table->json('slots'); // [{day, start, end, subject}]
                $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('school_schedule_shares')) {
            Schema::create('school_schedule_shares', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('school_schedule_id')->constrained('school_schedules')->cascadeOnDelete();
                $table->uuid('school_group_id')->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('permission', 16)->default('view');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('school_teacher_tasks')) {
            Schema::create('school_teacher_tasks', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('school_id')->constrained('schools')->cascadeOnDelete();
                $table->foreignUuid('school_group_id')->nullable()->constrained('school_groups')->nullOnDelete();
                $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
                $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('status', 24)->default('pending');
                $table->date('due_date')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('school_psych_cases')) {
            Schema::create('school_psych_cases', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('school_id')->constrained('schools')->cascadeOnDelete();
                $table->foreignId('student_user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
                $table->string('title');
                $table->text('summary')->nullable();
                $table->string('status', 24)->default('open');
                $table->string('visibility', 24)->default('staff'); // staff|guardians
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('school_psych_notes')) {
            Schema::create('school_psych_notes', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('school_psych_case_id')->constrained('school_psych_cases')->cascadeOnDelete();
                $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
                $table->text('body');
                $table->boolean('notify_guardians')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('school_health_alerts')) {
            Schema::create('school_health_alerts', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('school_id')->constrained('schools')->cascadeOnDelete();
                $table->foreignId('student_user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
                $table->string('type', 32)->default('health'); // health|sick|weekend
                $table->string('title');
                $table->text('body')->nullable();
                $table->boolean('phone_contact_failed')->default(false);
                $table->timestamp('occurred_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('school_subscriptions')) {
            Schema::create('school_subscriptions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('school_id')->constrained('schools')->cascadeOnDelete()->unique();
                $table->string('plan_code', 40)->default('school');
                $table->string('status', 24)->default('active');
                $table->string('billing', 16)->default('monthly');
                $table->timestamp('current_period_end')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('school_subscriptions');
        Schema::dropIfExists('school_health_alerts');
        Schema::dropIfExists('school_psych_notes');
        Schema::dropIfExists('school_psych_cases');
        Schema::dropIfExists('school_teacher_tasks');
        Schema::dropIfExists('school_schedule_shares');
        Schema::dropIfExists('school_schedules');
        Schema::dropIfExists('school_meeting_rsvps');
        Schema::dropIfExists('school_meetings');
        Schema::dropIfExists('school_announcements');
        Schema::dropIfExists('school_group_members');
        Schema::dropIfExists('school_groups');
        Schema::dropIfExists('school_staff_invites');

        if (Schema::hasTable('school_classes') && Schema::hasColumn('school_classes', 'campus_id')) {
            Schema::table('school_classes', function (Blueprint $table) {
                $table->dropColumn('campus_id');
            });
        }

        if (Schema::hasTable('schools') && Schema::hasColumn('schools', 'main_campus_id')) {
            Schema::table('schools', function (Blueprint $table) {
                $table->dropConstrainedForeignId('created_by');
                $table->dropColumn('main_campus_id');
            });
        }

        Schema::dropIfExists('school_campuses');

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'document_number')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex(['document_type', 'document_number']);
                $table->dropColumn(['document_type', 'document_number']);
            });
        }
    }
};
