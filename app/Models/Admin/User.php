<?php

namespace App\Models\Admin;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Traits\UserRolesTrait;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, HasUuids, Notifiable, SoftDeletes, UserRolesTrait;

    protected $table = 'users';

    protected $fillable = [
        'lastname',
        'firstname',
        'middlename',
        'extname',
        'email',
        'email_verified_at',
        'google_id',
        'password',
        'must_change_password',
        'is_active',
        'avatar',
        'last_login_at',
        'last_login_ip',
        'remember_token',
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

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
        ];
    }

    public function user_roles(): HasMany
    {
        return $this->hasMany(RoleUser::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_users', 'user_id', 'role_id');
    }

    public function hasRole(array $role): bool
    {
        return $this->roles()->whereIn('name', $role)->exists();
    }

    public function program_assignments(): HasMany
    {
        return $this->hasMany(UserProgram::class, 'user_id', 'id');
    }

    /**
     * The program codes assigned to this user (e.g. SPICT, STE).
     */
    public function programCodes(): array
    {
        return $this->program_assignments()
            ->pluck('program')
            ->map(fn ($program) => strtoupper(trim((string) $program)))
            ->unique()
            ->values()
            ->all();
    }

    public function hasProgram(string $program): bool
    {
        return in_array(strtoupper(trim($program)), $this->programCodes(), true);
    }
}
