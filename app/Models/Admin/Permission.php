<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Permission extends Model
{
    use HasUuids;
    protected $table = 'permissions';
    protected $fillable = [
        'name',
        'permission_title_id',
    ];

    public function role_permissions(): HasMany
    {
        return $this->hasMany(RolePermission::class);
    }

    public function permission_title()
    {
        return $this->belongsTo(PermissionTitle::class, 'permission_title_id', 'id');
    }
}
