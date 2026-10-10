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
        // The stored month/day needs the school year's year to become a full
        // date again (e.g. 06-01 + start 2026 -> 2026-06-01).
        $dates = DB::table('school_years')
            ->get(['id', 'start', 'application_start', 'application_deadline'])
            ->map(fn ($row) => [
                'id' => $row->id,
                'application_start' => $this->toFullDate($row->start, $row->application_start),
                'application_deadline' => $this->toFullDate($row->start, $row->application_deadline),
            ]);

        // Clear the values first: strict mode rejects the ALTER when the
        // 5-character month/day values no longer fit the growing column.
        DB::table('school_years')->update([
            'application_start' => null,
            'application_deadline' => null,
        ]);

        Schema::table('school_years', function (Blueprint $table) {
            $table->date('application_start')->nullable()->change();
            $table->date('application_deadline')->nullable()->change();
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
     */
    public function down(): void
    {
        $dates = DB::table('school_years')
            ->get(['id', 'application_start', 'application_deadline'])
            ->map(fn ($row) => [
                'id' => $row->id,
                'application_start' => $this->toMonthDay($row->application_start),
                'application_deadline' => $this->toMonthDay($row->application_deadline),
            ]);

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
     * Combine the school year's year with a stored month/day (MM-DD) so the
     * column can hold the full date (e.g. 2026 + 06-01 -> 2026-06-01).
     * Values that are already full dates are kept as-is.
     */
    private function toFullDate(?string $year, ?string $monthDay): ?string
    {
        if (! $monthDay) {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $monthDay)) {
            return $monthDay;
        }

        if (! $year || ! preg_match('/^(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])$/', $monthDay)) {
            return null;
        }

        $full = "{$year}-{$monthDay}";

        return checkdate((int) substr($full, 5, 2), (int) substr($full, 8, 2), (int) substr($full, 0, 4))
            ? $full
            : null;
    }

    /**
     * Convert a stored Y-m-d date to its month/day part (e.g. 2026-06-01 -> 06-01).
     */
    private function toMonthDay(?string $date): ?string
    {
        if (! $date) {
            return null;
        }

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? substr($date, 5, 5) : null;
    }
};
