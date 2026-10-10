<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExamSchedule extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'school_year_id',
        'program_id',
        'date',
        'time_slot',
        'room',
        'capacity',
    ];

    protected $casts = [
        'date' => 'date',
        'deleted_at' => 'datetime',
    ];

    public function schoolYear()
    {
        return $this->belongsTo(SchoolYear::class, 'school_year_id')->withTrashed();
    }

    public function program()
    {
        return $this->belongsTo(Program::class, 'program_id')->withTrashed();
    }

    public function applicants()
    {
        return $this->hasMany(\App\Models\Applicant\Applicant::class, 'schedule_id');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('deleted_at');
    }

    public function scopeForSchoolYear($query, $schoolYearId)
    {
        return $query->where('school_year_id', $schoolYearId);
    }

    public function scopeUnassigned($query)
    {
        return $query->whereNull('school_year_id');
    }
}