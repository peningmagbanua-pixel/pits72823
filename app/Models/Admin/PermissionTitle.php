<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PermissionTitle extends Model
{
    use HasUuids;
    protected $table = 'permission_titles';
    protected $fillable = [
        'name',
    ];

    public function scopeSearch($query, $term)
    {
        $term = "%$term%";

        return $query->where(function ($query) use ($term) {
            $query->where('name', 'like', $term);
        });
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class, 'permission_title_id', 'id');
    }
}
