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
        // Keep the stored month/day before the column shrinks (the year comes
        // from the school year itself, so it does not need to be stored).
        $dates = DB::table('school_years')
            ->get(['id', 'application_start', 'application_deadline'])
            ->map(fn ($row) => [
                'id' => $row->id,
                'application_start' => $this->toMonthDay($row->application_start),
                'application_deadline' => $this->toMonthDay($row->application_deadline),
            ]);

        // Clear the values first: strict mode rejects the ALTER when the
        // 10-character dates no longer fit the 5-character column.
        DB::table('school_years')->update([
            'application_start' => null,
            'application_deadline' => null,
        ]);

        Schema::table('school_years', function (Blueprint $table) {
            $table->string('application_start', 5)->nullable()->change();
            $table->string('application_deadline', 5)->nullable()->change();
        });

        foreach ($dates as $row) {
            DB::table('school_years')->where('id', $row['id'])->update([
                'application_start' => $row['application_start'],
                'application_deadline' => $row['application_deadline'],
            ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * The original year is intentionally not stored, so the values are cleared
     * before the columns become dates again (they can be re-entered by admin).
     */
    public function down(): void
    {
        DB::table('school_years')->update([
            'application_start' => null,
            'application_deadline' => null,
        ]);

        Schema::table('school_years', function (Blueprint $table) {
            $table->date('application_start')->nullable()->change();
            $table->date('application_deadline')->nullable()->change();
        });
    }

    /**
     * Convert a stored Y-m-d date to its month/day part (e.g. 2026-06-01 -> 06-01).
     * Values that are already stored as MM-DD are kept as-is.
     */
    private function toMonthDay(?string $date): ?string
    {
        if (! $date) {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return substr($date, 5, 5);
        }

        return preg_match('/^(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])$/', $date) ? $date : null;
    }
};
