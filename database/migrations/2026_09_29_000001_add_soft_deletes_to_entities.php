<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('school_years', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('permission_titles', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('applicants', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('school_years', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('permission_titles', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('applicants', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
