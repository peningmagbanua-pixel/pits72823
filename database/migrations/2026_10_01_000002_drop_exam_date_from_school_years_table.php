<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Exam dates are owned by the Exam Scheduling module (exam_schedules),
     * so the duplicate on school_years is removed.
     */
    public function up(): void
    {
        Schema::table('school_years', function (Blueprint $table) {
            $table->dropColumn('exam_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('school_years', function (Blueprint $table) {
            $table->date('exam_date')->nullable()->after('application_deadline');
        });
    }
};
