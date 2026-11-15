<?php

return [

    // Kill switch — off by default so local/dev/test never burns real SMS
    // credits. When false, SmsService logs what it would have sent instead
    // of calling the gateway, and still returns true (callers don't need to
    // branch on this).
    'enabled' => (bool) env('SMS_ENABLED', false),

    // --- SMS Gateway Hub (smsgatewayhub.com) "SendSMS" API ---
    'gateway_url' => env('SMS_GATEWAY_URL', 'https://www.smsgatewayhub.com/api/mt/SendSMS'),
    'api_key' => env('SMS_API_KEY'),
    'sender_id' => env('SMS_SENDER_ID'),
    'route' => env('SMS_ROUTE', '47'),
    'entity_id' => env('SMS_ENTITY_ID'),
    'channel' => env('SMS_CHANNEL', '2'),
    'dcs' => env('SMS_DCS', '0'),
    'flash_sms' => env('SMS_FLASH_SMS', '0'),

    /**
     * One entry per SMS use case — this is the single place to edit wording.
     *
     * `text` must stay an exact character-for-character match of what's
     * approved/registered against `dlt_template_id` on your DLT entity, or
     * the gateway will reject the send (Indian TRAI/DLT regulation) — so
     * editing the wording here almost always means re-registering the
     * template with your DLT operator first and updating the id in .env.
     *
     * `{placeholders}` are substituted by SmsService::send()'s $params.
     * `dlt_template_id` is per-template and account-specific, so it's kept
     * in .env rather than hardcoded here.
     */
    'templates' => [

        'otp' => [
            'text' => 'Your Dailzo login OTP is {otp}. Valid for {minutes} minutes. Do not share this OTP with anyone. \n Team Dailzo',
            'dlt_template_id' => env('SMS_DLT_TEMPLATE_OTP'),
        ],

        'order_placed' => [
            'text' => 'Your Dailzo order #{orderId} has been placed successfully. Order total: Rs.{total}. Track it in the app.',
            'dlt_template_id' => env('SMS_DLT_TEMPLATE_ORDER_PLACED'),
        ],

        // {otp} here is the delivery-confirmation OTP (same mechanism as
        // login OTP, different use) — the customer reads it out to the
        // delivery partner on handover; see PartnerOrderController::updateStatus().
        'order_out_for_delivery' => [
            'text' => 'Your Dailzo order #{orderId} is out for delivery. Share OTP {otp} with the delivery partner to confirm receipt.',
            'dlt_template_id' => env('SMS_DLT_TEMPLATE_ORDER_OUT_FOR_DELIVERY'),
        ],

        'order_delivered' => [
            'text' => 'Your Dailzo order #{orderId} has been delivered. Thank you for shopping with Dailzo!',
            'dlt_template_id' => env('SMS_DLT_TEMPLATE_ORDER_DELIVERED'),
        ],

    ],

];
