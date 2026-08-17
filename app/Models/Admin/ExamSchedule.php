<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ExamSchedule extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'program',
        'date',
        'time_slot',
        'room',
        'capacity',
    ];

    public function applicants()
    {
        return $this->hasMany(\App\Models\Applicant\Applicant::class, 'schedule_id');
    }
}
