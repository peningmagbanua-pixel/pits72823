<?php

namespace App\Models\Applicant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Applicant extends Authenticatable
{
    use HasFactory, HasUuids, Notifiable, SoftDeletes;

    protected $fillable = [
        'lastname',
        'firstname',
        'middlename',
        'extname',
        'email',
        'google_id',
        'password',
        'must_change_password',
        'is_active',
        'profile',
        'last_login_at',
        'last_login_ip',

        // Application Fields
        'program_id',
        'date_of_birth',
        'place_of_birth',
        'citizenship',
        'sex',
        'complete_address',
        'parent_guardian_name',
        'contact_number',
        'elementary_school',
        'elementary_school_address',

        // Uploaded Documents
        'sf9_file',
        'good_moral_file',
        'health_declaration_file',
        'ste_declaration_file',

        // Online STE Declaration
        'ste_declaration',

        'application_status',
        'rejection_reason',
        'submitted_at',
        'schedule_id',
        'exam_status',
        'school_year_id',
    ];

    public function scopeSearch($query, $term)
    {
        $term = "%$term%";

        return $query->where(function ($query) use ($term) {
            $query->where('email', 'like', $term)
                ->orWhere('firstname', 'like', $term)
                ->orWhere('middlename', 'like', $term)
                ->orWhere('lastname', 'like', $term)
                ->orWhere('extname', 'like', $term);
        });
    }

    public function schedule()
    {
        return $this->belongsTo(\App\Models\Admin\ExamSchedule::class, 'schedule_id');
    }

    public function schoolYear()
    {
        return $this->belongsTo(\App\Models\Admin\SchoolYear::class, 'school_year_id')->withTrashed();
    }

    public function program()
    {
        return $this->belongsTo(\App\Models\Admin\Program::class, 'program_id')->withTrashed();
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'applicant_id');
    }

    public function applicantDocuments(): HasMany
    {
        return $this->hasMany(ApplicantDocument::class, 'applicant_id');
    }

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'last_login_at' => 'datetime',
            'submitted_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'ste_declaration' => 'array',
        ];
    }
}
