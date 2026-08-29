<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_memberships', function (Blueprint $table) {
            $table->json('subjects')->nullable()->after('status');
            $table->string('primary_subject')->nullable()->after('subjects');
        });
    }

    public function down(): void
    {
        Schema::table('teacher_memberships', function (Blueprint $table) {
            $table->dropColumn(['subjects', 'primary_subject']);
        });
    }
};
