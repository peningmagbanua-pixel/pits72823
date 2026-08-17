<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('school_years', function (Blueprint $table) {
            $table->date('application_start')->nullable()->after('name');
            $table->date('application_deadline')->nullable()->after('application_start');
            $table->date('exam_date')->nullable()->after('application_deadline');
            $table->text('guidelines')->nullable()->after('exam_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('school_years', function (Blueprint $table) {
            $table->dropColumn(['application_start', 'application_deadline', 'exam_date', 'guidelines']);
        });
    }
};
