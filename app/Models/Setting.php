<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $table = 'settings';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * Read a stored setting, falling back to the given default when the row
     * is missing. Cached because the session timeout middleware reads these
     * values on every protected request.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever('setting:'.$key, function () use ($key, $default) {
            $row = static::query()->find($key);

            return $row->value ?? $default;
        });
    }

    /**
     * Persist a setting and drop its cache entry so the next read is fresh.
     */
    public static function set(string $key, mixed $value): void
    {
        static::query()->upsert(
            [[
                'key' => $key,
                'value' => (string) $value,
                'created_at' => now(),
                'updated_at' => now(),
            ]],
            ['key'],
            ['value', 'updated_at']
        );

        Cache::forget('setting:'.$key);
    }
}
