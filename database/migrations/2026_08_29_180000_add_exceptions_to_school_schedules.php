<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('school_schedules')) {
            return;
        }

        Schema::table('school_schedules', function (Blueprint $table) {
            if (! Schema::hasColumn('school_schedules', 'exceptions')) {
                $table->json('exceptions')->nullable()->after('slots');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('school_schedules')) {
            return;
        }

        Schema::table('school_schedules', function (Blueprint $table) {
            if (Schema::hasColumn('school_schedules', 'exceptions')) {
                $table->dropColumn('exceptions');
            }
        });
    }
};
