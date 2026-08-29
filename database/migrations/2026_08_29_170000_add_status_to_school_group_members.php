<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('school_group_members')) {
            return;
        }

        Schema::table('school_group_members', function (Blueprint $table) {
            if (! Schema::hasColumn('school_group_members', 'status')) {
                $table->string('status', 16)->default('active')->after('member_role');
                $table->index(['school_group_id', 'status']);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('school_group_members')) {
            return;
        }

        Schema::table('school_group_members', function (Blueprint $table) {
            if (Schema::hasColumn('school_group_members', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
