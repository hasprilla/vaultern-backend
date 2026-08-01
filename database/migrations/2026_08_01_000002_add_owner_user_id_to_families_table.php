<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('families', function (Blueprint $table) {
            $table->unsignedBigInteger('owner_user_id')->nullable()->after('invite_code');
            $table->foreign('owner_user_id')->references('id')->on('users')->nullOnDelete();
            $table->index('owner_user_id');
        });

        $families = DB::table('families')->select('id')->get();
        foreach ($families as $family) {
            $ownerId = DB::table('subscriptions')
                ->where('family_id', $family->id)
                ->whereNotNull('renewal_user_id')
                ->orderByDesc('updated_at')
                ->value('renewal_user_id');

            if ($ownerId === null) {
                $ownerId = DB::table('users')
                    ->where('family_id', $family->id)
                    ->whereIn('role', ['padre', 'madre'])
                    ->orderBy('id')
                    ->value('id');
            }

            if ($ownerId === null) {
                $ownerId = DB::table('users')
                    ->where('family_id', $family->id)
                    ->orderBy('id')
                    ->value('id');
            }

            if ($ownerId !== null) {
                DB::table('families')
                    ->where('id', $family->id)
                    ->update(['owner_user_id' => $ownerId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('families', function (Blueprint $table) {
            $table->dropForeign(['owner_user_id']);
            $table->dropIndex(['owner_user_id']);
            $table->dropColumn('owner_user_id');
        });
    }
};
