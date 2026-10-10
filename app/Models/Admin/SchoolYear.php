<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class SchoolYear extends Model
{
    use HasUuids, SoftDeletes;
    protected $table = 'school_years';

    protected $fillable = [
        'start',
        'end',
        'name',
        'is_active',
        'application_start',
        'application_deadline',
        'guidelines',
    ];

    public function applicants()
    {
        return $this->hasMany(\App\Models\Applicant\Applicant::class, 'school_year_id');
    }

    public function scopeSearch($query, $term)
    {
        $term = "%$term%";

        return $query->where(function ($query) use ($term) {
            $query->where('start', 'like', $term)
                ->orWhere('end', 'like', $term)
                ->orWhere('name', 'like', $term);
        });
    }

    /**
     * The online application period of the active school year.
     *
     * Status is one of: "not_open" (before the opening date), "open"
     * (between the opening and closing dates, inclusive) or "closed"
     * (after the closing date). When there is no active school year or the
     * dates are not configured, the period is treated as closed.
     */
    public static function applicationPeriod(): array
    {
        $school_year = self::where('is_active', true)->first();

        $period = [
            'status' => 'closed',
            'application_start' => $school_year?->application_start,
            'application_deadline' => $school_year?->application_deadline,
        ];

        if (! $period['application_start'] || ! $period['application_deadline']) {
            return $period;
        }

        $today = today()->toDateString();

        if ($today < $period['application_start']) {
            $period['status'] = 'not_open';
        } elseif ($today <= $period['application_deadline']) {
            $period['status'] = 'open';
        }

        return $period;
    }
}
