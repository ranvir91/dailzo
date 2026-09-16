<?php

namespace App\Support;

/**
 * Normalises an image reference to the relative `uploads/<file>` form the API
 * stores and returns (ported from the old backend's `toUploadPath`). Absolute
 * URLs and data: URIs are left untouched — the clients resolve those as-is.
 */
class UploadPath
{
    public static function normalize(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $pos = strpos($value, '/uploads/');
        if ($pos !== false) {
            return substr($value, $pos + 1);
        }

        return $value;
    }

    /** @param  array<int, string|null>|null  $values */
    public static function normalizeMany(?array $values): array
    {
        return collect($values ?? [])
            ->map(fn ($v) => self::normalize($v))
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->values()
            ->all();
    }
}
