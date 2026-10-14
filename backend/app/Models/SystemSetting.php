<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class SystemSetting extends Model
{
    protected $table = 'system_settings';

    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember("nis:setting:{$key}", 300, function () use ($key, $default) {
            try {
                if (! Schema::hasTable('system_settings')) {
                    return $default;
                }
                $record = static::where('key', $key)->first();
                return $record ? ($record->value ?? $default) : $default;
            } catch (\Throwable) {
                return $default;
            }
        });
    }

    public static function set(string $key, mixed $value): void
    {
        try {
            if (Schema::hasTable('system_settings')) {
                static::updateOrCreate(['key' => $key], ['value' => $value]);
            }
        } catch (\Throwable) {}
        Cache::forget("nis:setting:{$key}");
    }
}
