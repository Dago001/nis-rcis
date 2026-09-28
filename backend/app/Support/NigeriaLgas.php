<?php

namespace App\Support;

/**
 * Nigeria's 36 states + FCT and their 774 local government areas
 * (resources/data/nigeria-lgas.json, shared with the frontend dropdowns).
 */
class NigeriaLgas
{
    private static ?array $data = null;

    /** @return array<string, list<string>> */
    public static function all(): array
    {
        return self::$data ??= json_decode((string) file_get_contents(resource_path('data/nigeria-lgas.json')), true);
    }

    /** @return list<string> */
    public static function states(): array
    {
        return array_keys(self::all());
    }

    /** @return list<string> */
    public static function lgas(string $state): array
    {
        return self::all()[$state] ?? [];
    }
}
