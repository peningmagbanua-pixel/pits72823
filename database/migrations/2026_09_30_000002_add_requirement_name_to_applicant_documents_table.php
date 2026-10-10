<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('applicant_documents', function (Blueprint $table) {
            $table->string('requirement_name')->nullable()->after('requirement_id');
        });

        $this->backfillRequirementName();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applicant_documents', function (Blueprint $table) {
            $table->dropColumn('requirement_name');
        });
    }

    /**
     * Snapshot the requirement name so uploads keep a meaningful label after
     * the requirement is deleted (soft or hard).
     */
    private function backfillRequirementName(): void
    {
        $names = DB::table('requirements')->pluck('name', 'id');

        DB::table('applicant_documents')
            ->whereNull('requirement_name')
            ->orderBy('id')
            ->chunkById(200, function ($documents) use ($names) {
                foreach ($documents as $document) {
                    $name = $names->get($document->requirement_id);

                    if ($name !== null) {
                        DB::table('applicant_documents')
                            ->where('id', $document->id)
                            ->update(['requirement_name' => $name]);
                    }
                }
            });
    }
};
