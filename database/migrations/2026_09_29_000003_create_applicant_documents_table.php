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
        Schema::create('applicant_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('applicant_id');
            $table->uuid('requirement_id');
            $table->string('file_path');
            $table->timestamps();

            $table->foreign('applicant_id')->references('id')->on('applicants')->cascadeOnDelete();
            $table->foreign('requirement_id')->references('id')->on('requirements')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applicant_documents');
    }
};
