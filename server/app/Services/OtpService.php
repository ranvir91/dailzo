<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * OTP generation/verification. Codes live in the cache keyed by phone number,
 * exactly like the in-memory Map the old NestJS AuthService used — just shared
 * across processes now. No SMS gateway yet; see config/dailzo.php `otp_debug`.
 */
class OtpService
{
    public function generate(string $phone): string
    {
        $otp = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);

        Cache::put($this->key($phone), $otp, config('dailzo.otp_ttl'));

        return $otp;
    }

    public function verify(string $phone, string $otp): bool
    {
        $expected = Cache::get($this->key($phone));

        return $expected !== null && hash_equals($expected, $otp);
    }

    private function key(string $phone): string
    {
        return 'otp:'.$phone;
    }
}
