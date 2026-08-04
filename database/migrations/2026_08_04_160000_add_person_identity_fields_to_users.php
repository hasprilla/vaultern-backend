<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (! Schema::hasColumn('users', 'phone')) {
                    $table->string('phone', 32)->nullable()->after('email');
                }
                if (! Schema::hasColumn('users', 'birthdate')) {
                    $table->date('birthdate')->nullable()->after('phone');
                }
                if (! Schema::hasColumn('users', 'address')) {
                    $table->string('address', 255)->nullable()->after('birthdate');
                }
            });
        }

        if (Schema::hasTable('family_join_requests')) {
            Schema::table('family_join_requests', function (Blueprint $table) {
                if (! Schema::hasColumn('family_join_requests', 'document_type')) {
                    $table->string('document_type', 32)->nullable()->after('role');
                }
                if (! Schema::hasColumn('family_join_requests', 'document_number')) {
                    $table->string('document_number', 64)->nullable()->after('document_type');
                }
                if (! Schema::hasColumn('family_join_requests', 'phone')) {
                    $table->string('phone', 32)->nullable()->after('document_number');
                }
                if (! Schema::hasColumn('family_join_requests', 'birthdate')) {
                    $table->date('birthdate')->nullable()->after('phone');
                }
                if (! Schema::hasColumn('family_join_requests', 'address')) {
                    $table->string('address', 255)->nullable()->after('birthdate');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                foreach (['phone', 'birthdate', 'address'] as $col) {
                    if (Schema::hasColumn('users', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('family_join_requests')) {
            Schema::table('family_join_requests', function (Blueprint $table) {
                foreach (['document_type', 'document_number', 'phone', 'birthdate', 'address'] as $col) {
                    if (Schema::hasColumn('family_join_requests', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
