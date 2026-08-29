<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('family_event_guests') && ! Schema::hasColumn('family_event_guests', 'guest_kind')) {
            Schema::table('family_event_guests', function (Blueprint $table) {
                $table->string('guest_kind', 16)->default('adult')->after('phone');
                $table->index(['event_id', 'guest_kind']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('family_event_guests') && Schema::hasColumn('family_event_guests', 'guest_kind')) {
            Schema::table('family_event_guests', function (Blueprint $table) {
                $table->dropIndex(['event_id', 'guest_kind']);
                $table->dropColumn('guest_kind');
            });
        }
    }
};
