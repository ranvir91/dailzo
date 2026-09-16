<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * A bare "YYYY-MM-DD" means midnight-to-midnight IST, not UTC.
 *
 * Deliberately does NOT convert to UTC. This app's DB connection has no
 * `timezone` override (config/database.php), so Carbon writes/reads
 * datetime columns using its own current timezone's wall-clock digits
 * verbatim — and with APP_TIMEZONE=Asia/Kolkata, both `now()` and every
 * auto-set `created_at`/`updated_at` are already IST wall-clock digits.
 * Converting a boundary to UTC before comparing would shift it by 5:30h
 * against what's actually stored — verified against the dev DB while
 * building this. (CouponService::parseDate does convert to UTC; that's a
 * separate, pre-existing inconsistency — see docs/php-migration-plan.md.)
 */
class IstDate
{
    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} [start, end] of the day. */
    public static function dayRange(string $date): array
    {
        $start = CarbonImmutable::parse("{$date} 00:00:00", 'Asia/Kolkata');
        $end = CarbonImmutable::parse("{$date} 23:59:59.999999", 'Asia/Kolkata');

        return [$start, $end];
    }
}
