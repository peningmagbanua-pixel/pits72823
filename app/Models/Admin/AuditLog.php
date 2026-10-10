<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasUuids;
    protected $table = 'audit_logs';
    protected $fillable = [
        'user_id',
        'action',
        'module',
        'description',
        'old_values',
        'new_values'
    ];

    // uuid
    // public $incrementing = false;
    // protected $keyType = 'string';
    // protected static function boot()
    // {
    //     parent::boot();

    //     static::creating(function ($model) {
    //         if (empty($model->{$model->getKeyName()})) {
    //             $model->{$model->getKeyName()} = (string) Str::uuid();
    //         }
    //     });
    // }

    public function scopeSearch($query, $term)
    {
        $term = "%$term%";

        return $query->where(function ($query) use ($term) {
            $query->where('action', 'like', $term)
                ->orWhere('module', 'like', $term)
                ->orWhere('description', 'like', $term)
                ->orWhereHas('user', function ($userQuery) use ($term) {
                    $userQuery->where('firstname', 'like', $term)
                        ->orWhere('lastname', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id')->withTrashed();
    }
}