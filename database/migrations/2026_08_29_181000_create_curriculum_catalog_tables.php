<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('curriculum_profiles')) {
            Schema::create('curriculum_profiles', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('country_code', 2)->index();
                $table->string('level', 32); // primaria|secundaria|preescolar
                $table->string('shift', 16); // manana|tarde
                $table->string('label');
                $table->unsignedSmallInteger('weekly_hours');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['country_code', 'level', 'shift']);
            });
        }

        if (! Schema::hasTable('curriculum_blocks')) {
            Schema::create('curriculum_blocks', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('curriculum_profile_id')
                    ->constrained('curriculum_profiles')
                    ->cascadeOnDelete();
                $table->unsignedSmallInteger('sort_order');
                $table->string('start_time', 5);
                $table->string('end_time', 5);
                $table->string('kind', 16); // lesson|break
                $table->string('label')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('curriculum_subjects')) {
            Schema::create('curriculum_subjects', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('country_code', 2)->index();
                $table->string('level', 32);
                $table->string('name');
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index(['country_code', 'level']);
            });
        }

        if (Schema::hasTable('school_schedules')
            && ! Schema::hasColumn('school_schedules', 'exceptions')) {
            Schema::table('school_schedules', function (Blueprint $table) {
                $table->json('exceptions')->nullable()->after('slots');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('curriculum_blocks');
        Schema::dropIfExists('curriculum_subjects');
        Schema::dropIfExists('curriculum_profiles');
        if (Schema::hasTable('school_schedules')
            && Schema::hasColumn('school_schedules', 'exceptions')) {
            Schema::table('school_schedules', function (Blueprint $table) {
                $table->dropColumn('exceptions');
            });
        }
    }
};
