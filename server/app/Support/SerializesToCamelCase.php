<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Emits model JSON with camelCase keys regardless of the snake_case column names,
 * so the existing Flutter app and React admin keep receiving the exact shapes they
 * did from the old NestJS/Prisma API (discountedPrice, isDefault, orderNumber, ...).
 *
 * Applied to every model that is serialized into an API response.
 */
trait SerializesToCamelCase
{
    public function toArray()
    {
        return static::camelizeKeys(parent::toArray());
    }

    protected static function camelizeKeys(array $input): array
    {
        $output = [];

        foreach ($input as $key => $value) {
            $newKey = is_string($key) ? Str::camel($key) : $key;
            $output[$newKey] = is_array($value) ? static::camelizeKeys($value) : $value;
        }

        return $output;
    }
}
