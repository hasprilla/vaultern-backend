<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('school_attendance_logs')) {
            Schema::create('school_attendance_logs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('school_id')->index();
                $table->uuid('family_id')->index();
                $table->unsignedBigInteger('student_user_id')->index();
                $table->unsignedBigInteger('reported_by')->index();
                $table->date('attendance_date');
                $table->string('status', 16); // present|absent|late|sick
                $table->string('note', 255)->nullable();
                $table->timestamps();
                $table->unique(['student_user_id', 'attendance_date'], 'attendance_student_date_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('school_attendance_logs');
    }
};
