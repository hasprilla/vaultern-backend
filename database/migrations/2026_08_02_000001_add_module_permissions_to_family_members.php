<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('family_members')) {
            return;
        }

        Schema::table('family_members', function (Blueprint $table) {
            if (! Schema::hasColumn('family_members', 'can_tasks')) {
                $table->boolean('can_tasks')->nullable()->after('role');
            }
            if (! Schema::hasColumn('family_members', 'can_finances')) {
                $table->boolean('can_finances')->nullable()->after('can_tasks');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('family_members')) {
            return;
        }

        Schema::table('family_members', function (Blueprint $table) {
            if (Schema::hasColumn('family_members', 'can_finances')) {
                $table->dropColumn('can_finances');
            }
            if (Schema::hasColumn('family_members', 'can_tasks')) {
                $table->dropColumn('can_tasks');
            }
        });
    }
};
