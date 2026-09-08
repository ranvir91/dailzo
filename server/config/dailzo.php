<?php

return [

    // While true, /auth/send-otp returns the generated OTP in the response body
    // (no SMS gateway is wired yet). Matches the old NestJS backend's dev behaviour.
    'otp_debug' => (bool) env('OTP_DEBUG', false),

    // How long a generated OTP stays valid, in seconds.
    'otp_ttl' => (int) env('OTP_TTL_SECONDS', 300),

    // Refresh-token lifetime, in days.
    'refresh_token_ttl_days' => (int) env('REFRESH_TOKEN_TTL_DAYS', 7),

    // Max upload size for the /uploads endpoint, in kilobytes (8 MB, as before).
    'upload_max_kb' => (int) env('UPLOAD_MAX_KB', 8192),

];
