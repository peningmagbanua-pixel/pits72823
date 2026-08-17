<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SchoolYear extends Model
{
    use HasUuids;
    protected $table = 'school_years';

    protected $fillable = [
        'start',
        'end',
        'name',
        'is_active',
        'application_start',
        'application_deadline',
        'exam_date',
        'guidelines',
    ];

    public function scopeSearch($query, $term)
    {
        $term = "%$term%";

        return $query->where(function ($query) use ($term) {
            $query->where('start', 'like', $term)
                ->orWhere('end', 'like', $term)
                ->orWhere('name', 'like', $term);
        });
    }
}
