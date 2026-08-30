<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('family_events')) {
            return;
        }
        Schema::table('family_events', function (Blueprint $table) {
            if (! Schema::hasColumn('family_events', 'kind')) {
                $table->string('kind', 32)->default('general')->after('status');
                $table->index(['family_id', 'kind']);
            }
            if (! Schema::hasColumn('family_events', 'child_user_id')) {
                $table->unsignedBigInteger('child_user_id')->nullable()->after('kind');
            }
            if (! Schema::hasColumn('family_events', 'budget_amount')) {
                $table->decimal('budget_amount', 12, 2)->nullable()->after('child_user_id');
            }
            if (! Schema::hasColumn('family_events', 'currency')) {
                $table->string('currency', 8)->default('COP')->after('budget_amount');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('family_events')) {
            return;
        }
        Schema::table('family_events', function (Blueprint $table) {
            foreach (['currency', 'budget_amount', 'child_user_id', 'kind'] as $col) {
                if (Schema::hasColumn('family_events', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
