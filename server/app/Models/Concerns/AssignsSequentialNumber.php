<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Assigns a human-facing sequential number (user_number, order_number) on create.
 *
 * Prisma modelled these as `@default(autoincrement())` unique columns alongside a
 * UUID primary key. MySQL allows only one AUTO_INCREMENT column and it must be a
 * key, so the number is allocated here instead: max(column) + 1, starting at
 * sequentialNumberBase(). At MVP scale on shared hosting inserts are effectively
 * serialized; the unique index is the backstop if two ever collide.
 */
trait AssignsSequentialNumber
{
    protected static function bootAssignsSequentialNumber(): void
    {
        static::creating(function ($model) {
            $column = $model->sequentialNumberColumn();

            if (! empty($model->{$column})) {
                return;
            }

            $callback = function () use ($model, $column) {
                $current = static::query()->withoutGlobalScopes()->lockForUpdate()->max($column);
                $model->{$column} = ($current ?? $model->sequentialNumberBase()) + 1;
            };

            DB::transactionLevel() > 0
                ? $callback()
                : DB::transaction($callback);
        });
    }

    abstract public function sequentialNumberColumn(): string;

    public function sequentialNumberBase(): int
    {
        return 100000;
    }
}
