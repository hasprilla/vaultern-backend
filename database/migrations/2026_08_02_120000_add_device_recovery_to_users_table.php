<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'device_secret_hash')) {
                $table->string('device_secret_hash')->nullable()->after('device_fingerprint');
            }
            if (! Schema::hasColumn('users', 'security_question_key')) {
                $table->string('security_question_key', 64)->nullable()->after('device_secret_hash');
            }
            if (! Schema::hasColumn('users', 'security_answer_hash')) {
                $table->string('security_answer_hash')->nullable()->after('security_question_key');
            }
            if (! Schema::hasColumn('users', 'device_secret_must_rotate')) {
                $table->boolean('device_secret_must_rotate')->default(false)->after('security_answer_hash');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            foreach (['device_secret_must_rotate', 'security_answer_hash', 'security_question_key', 'device_secret_hash'] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
