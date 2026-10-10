<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Program extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'programs';

    protected $fillable = [
        'initials',
        'name',
        'description',
        'is_active',
    ];

    /**
     * Requirements assigned to this program (a requirement with no programs
     * at all applies to every program, including this one).
     */
    public function requirements(): BelongsToMany
    {
        return $this->belongsToMany(Requirement::class, 'program_requirements')
            ->withTimestamps();
    }

    public function scopeSearch($query, $term)
    {
        $term = "%$term%";

        return $query->where(function ($query) use ($term) {
            $query->where('initials', 'like', $term)
                ->orWhere('name', 'like', $term)
                ->orWhere('description', 'like', $term);
        });
    }
}
