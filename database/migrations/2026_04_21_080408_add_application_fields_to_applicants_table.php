<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->string('program', 10)->nullable()->after('extname');          // 'STE' or 'SPICT'
            $table->date('date_of_birth')->nullable()->after('program');
            $table->string('place_of_birth')->nullable()->after('date_of_birth');
            $table->string('citizenship')->nullable()->after('place_of_birth');
            $table->string('sex', 10)->nullable()->after('citizenship');          // 'Male' or 'Female'
            $table->text('complete_address')->nullable()->after('sex');
            $table->string('parent_guardian_name')->nullable()->after('complete_address');
            $table->string('contact_number', 20)->nullable()->after('parent_guardian_name');
            $table->string('elementary_school')->nullable()->after('contact_number');
            $table->string('elementary_school_address')->nullable()->after('elementary_school');
            $table->string('sf9_file')->nullable()->after('elementary_school_address');
            $table->string('good_moral_file')->nullable()->after('sf9_file');
            $table->string('health_declaration_file')->nullable()->after('good_moral_file');
            $table->string('application_status', 20)->default('pending')->after('health_declaration_file');
        });
    }

    public function down(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->dropColumn([
                'program',
                'date_of_birth',
                'place_of_birth',
                'citizenship',
                'sex',
                'complete_address',
                'parent_guardian_name',
                'contact_number',
                'elementary_school',
                'elementary_school_address',
                'sf9_file',
                'good_moral_file',
                'health_declaration_file',
                'application_status',
            ]);
        });
    }
};
