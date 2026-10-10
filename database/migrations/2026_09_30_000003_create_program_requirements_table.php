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
        Schema::create('program_requirements', function (Blueprint $table) {
            $table->uuid('program_id');
            $table->uuid('requirement_id');
            $table->timestamps();

            $table->primary(['program_id', 'requirement_id']);
            $table->foreign('program_id')->references('id')->on('programs')->cascadeOnDelete();
            $table->foreign('requirement_id')->references('id')->on('requirements')->cascadeOnDelete();
        });

        $this->backfillFromAppliesTo();

        Schema::table('requirements', function (Blueprint $table) {
            $table->dropColumn('applies_to');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('requirements', function (Blueprint $table) {
            $table->string('applies_to', 20)->nullable()->after('notice');
        });

        $this->restoreAppliesTo();
        Schema::dropIfExists('program_requirements');
    }

    /**
     * requirements.applies_to held a single program initials (NULL = every
     * program). Resolve each initials against the programs table — never from
     * a hardcoded list — and write the pivot row.
     */
    private function backfillFromAppliesTo(): void
    {
        $programIds = DB::table('programs')->pluck('id', 'initials');

        DB::table('requirements')
            ->whereNotNull('applies_to')
            ->orderBy('id')
            ->chunkById(200, function ($requirements) use ($programIds) {
                foreach ($requirements as $requirement) {
                    $programId = $programIds->get($requirement->applies_to);

                    if ($programId === null) {
                        continue;
                    }

                    DB::table('program_requirements')->insertOrIgnore([
                        'program_id' => $programId,
                        'requirement_id' => $requirement->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    /**
     * The column can only hold one initials, so a requirement assigned to
     * several programs falls back to NULL (all programs) — the closest
     * lossless-enough inverse for the single value it can store.
     */
    private function restoreAppliesTo(): void
    {
        $assignments = [];

        DB::table('program_requirements as pr')
            ->join('programs as p', 'p.id', 'pr.program_id')
            ->orderBy('pr.requirement_id')
            ->orderBy('p.initials')
            ->select('pr.requirement_id', 'p.initials')
            ->get()
            ->each(function ($row) use (&$assignments) {
                $assignments[$row->requirement_id][] = $row->initials;
            });

        foreach ($assignments as $requirementId => $initialsList) {
            DB::table('requirements')
                ->where('id', $requirementId)
                ->update([
                    'applies_to' => count($initialsList) === 1 ? $initialsList[0] : null,
                ]);
        }
    }
};
