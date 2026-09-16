<?php

namespace App\Services;

use Carbon\CarbonImmutable;

/**
 * Coupon validation + payload mapping, ported from the NestJS CouponsService.
 *
 * Date handling: the store runs in India, so a bare "YYYY-MM-DD" from the admin's
 * date picker means midnight IST (start) or end-of-day IST (expiry), not UTC.
 * Full datetime strings pass through untouched.
 */
class CouponService
{
    public const TYPES = ['FIXED', 'PERCENTAGE'];

    private const DATE_ONLY = '/^\d{4}-\d{2}-\d{2}$/';

    public function parseDate(string $value, bool $endOfDay): CarbonImmutable
    {
        if (! preg_match(self::DATE_ONLY, $value)) {
            return CarbonImmutable::parse($value);
        }

        $time = $endOfDay ? '23:59:59.999' : '00:00:00';

        return CarbonImmutable::parse("{$value} {$time}", 'Asia/Kolkata')->utc();
    }

    /** Aborts with a 400 + the specific message, matching the old BadRequestException. */
    public function validate(array $input, bool $creating): void
    {
        $fail = fn (string $message) => abort(400, $message);

        if ($creating && empty(trim($input['code'] ?? ''))) {
            $fail('Coupon code is required');
        }

        if (($creating || array_key_exists('type', $input)) && ! in_array($input['type'] ?? null, self::TYPES, true)) {
            $fail('Coupon type must be FIXED or PERCENTAGE');
        }

        if ($creating) {
            $discount = $input['discount'] ?? null;
            if ($discount === null || $discount === '' || ! is_numeric($discount) || (float) $discount <= 0) {
                $fail('Enter a valid discount value');
            }
        }

        if (array_key_exists('discount', $input) && ($input['type'] ?? null) === 'PERCENTAGE' && (float) $input['discount'] > 100) {
            $fail('Percentage discount cannot exceed 100');
        }

        if (! empty($input['startsAt']) && ! empty($input['expiresAt'])
            && $this->parseDate($input['startsAt'], false)->gt($this->parseDate($input['expiresAt'], true))) {
            $fail('Validity start date must be before the end date');
        }
    }

    /** Maps request keys to model columns, applying the same coercions as before. */
    public function toColumns(array $input, bool $creating): array
    {
        $data = [];
        $has = fn (string $k) => array_key_exists($k, $input);
        $blank = fn ($v) => $v === '' || $v === null;

        if ($has('code')) {
            $data['code'] = strtoupper(trim($input['code']));
        }
        if ($has('description')) {
            $data['description'] = $input['description'] ?: null;
        }
        if ($has('type')) {
            $data['type'] = $input['type'];
        }
        if ($has('discount')) {
            $data['discount'] = (float) $input['discount'];
        }
        if ($has('maxDiscountAmount')) {
            $data['max_discount_amount'] = $blank($input['maxDiscountAmount']) ? null : (float) $input['maxDiscountAmount'];
        }
        if ($has('minOrderValue')) {
            $data['min_order_value'] = $blank($input['minOrderValue']) ? 0 : (float) $input['minOrderValue'];
        }
        if ($has('startsAt')) {
            $data['starts_at'] = $input['startsAt'] ? $this->parseDate($input['startsAt'], false) : null;
        }
        if ($has('expiresAt')) {
            $data['expires_at'] = $input['expiresAt'] ? $this->parseDate($input['expiresAt'], true) : null;
        }
        if ($has('usageLimit')) {
            $data['usage_limit'] = $blank($input['usageLimit']) ? null : (int) $input['usageLimit'];
        }
        if ($has('perUserLimit')) {
            $data['per_user_limit'] = $blank($input['perUserLimit']) ? null : (int) $input['perUserLimit'];
        }
        if ($has('firstOrderOnly')) {
            $data['first_order_only'] = (bool) $input['firstOrderOnly'];
        }
        if ($has('isActive')) {
            $data['is_active'] = (bool) $input['isActive'];
        }

        if ($creating) {
            $data['is_active'] ??= true;
            $data['per_user_limit'] ??= 1;
            $data['min_order_value'] ??= 0;
        }

        return $data;
    }
}
