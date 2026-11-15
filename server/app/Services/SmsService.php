<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends transactional SMS via SMS Gateway Hub (smsgatewayhub.com)'s SendSMS
 * API. Gateway config and message wording live in config/sms.php — edit that
 * file (and the SMS_* vars in .env for credentials) to change templates or
 * switch providers' credentials; this class shouldn't need touching for that.
 */
class SmsService
{
    /**
     * @param  array<string, string|int>  $params  Values substituted into the
     *                                              template's {placeholders}.
     * @return bool Whether the gateway accepted the request. Never throws —
     *              a failed SMS must not break the order/auth flow that
     *              triggered it; failures are logged instead.
     */
    public function send(string $phone, string $templateKey, array $params = []): bool
    {
        $text = $this->render($templateKey, $params);

        if (! config('sms.enabled')) {
            Log::info("SMS (disabled, not sent) [{$templateKey}] to {$phone}: {$text}");

            return true;
        }

        $template = config("sms.templates.{$templateKey}");
        if (! $template) {
            Log::warning("SMS not sent: template [{$templateKey}] is not configured.");

            return false;
        }

        $number = $this->normalizePhone($phone);
        if (! $number) {
            Log::warning("SMS not sent: [{$phone}] doesn't look like a valid 10-digit Indian mobile number.");

            return false;
        }

        try {
            $response = Http::timeout(10)->get(config('sms.gateway_url'), [
                'APIKey' => config('sms.api_key'),
                'senderid' => config('sms.sender_id'),
                'channel' => config('sms.channel'),
                'DCS' => config('sms.dcs'),
                'flashsms' => config('sms.flash_sms'),
                'number' => $number,
                'text' => $text,
                'route' => config('sms.route'),
                'EntityId' => config('sms.entity_id'),
                'dlttemplateid' => $template['dlt_template_id'],
            ]);

            if ($response->failed()) {
                Log::warning("SMS gateway returned an error for [{$templateKey}] to {$number}: {$response->body()}");

                return false;
            }

            return true;
        } catch (Throwable $e) {
            Log::warning("SMS send failed for [{$templateKey}] to {$number}: {$e->getMessage()}");

            return false;
        }
    }

    private function render(string $templateKey, array $params): string
    {
        $text = config("sms.templates.{$templateKey}.text", '');
        foreach ($params as $key => $value) {
            $text = str_replace('{'.$key.'}', (string) $value, $text);
        }

        return $text;
    }

    /** Normalizes to the gateway's expected 91XXXXXXXXXX format, or null if it isn't a recognizable Indian mobile number. */
    private function normalizePhone(string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if (strlen($digits) === 10) {
            return '91'.$digits;
        }
        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            return $digits;
        }
        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            return '91'.substr($digits, 1);
        }

        return null;
    }
}
