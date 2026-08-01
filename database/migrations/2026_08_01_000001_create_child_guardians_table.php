<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('child_guardians', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('family_id')->index();
            $table->unsignedBigInteger('child_user_id')->index();
            $table->unsignedBigInteger('parent_user_id')->index();
            $table->string('relation', 20); // padre | madre | tutor
            $table->timestamps();

            $table->unique(['child_user_id', 'parent_user_id']);
            $table->foreign('family_id')->references('id')->on('families')->cascadeOnDelete();
            $table->foreign('child_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('parent_user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        // Compatibilidad: vincular a todos los padres/madres existentes con todos los hijos de su familia.
        $children = DB::table('users')->where('role', 'hijo')->whereNotNull('family_id')->get(['id', 'family_id']);
        foreach ($children as $child) {
            $parents = DB::table('users')
                ->where('family_id', $child->family_id)
                ->whereIn('role', ['padre', 'madre', 'tutor'])
                ->get(['id', 'role']);

            foreach ($parents as $parent) {
                DB::table('child_guardians')->insert([
                    'id'             => (string) Str::uuid(),
                    'family_id'      => $child->family_id,
                    'child_user_id'  => $child->id,
                    'parent_user_id' => $parent->id,
                    'relation'       => $parent->role,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('child_guardians');
    }
};
