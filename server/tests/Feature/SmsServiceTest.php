<?php

namespace Tests\Feature;

use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsServiceTest extends TestCase
{
    use RefreshDatabase;

    private function enableSms(): void
    {
        config([
            'sms.enabled' => true,
            'sms.gateway_url' => 'https://gateway.example.test/SendSMS',
            'sms.api_key' => 'test-api-key',
            'sms.sender_id' => 'TESTIN',
            'sms.route' => '47',
            'sms.entity_id' => 'test-entity',
            'sms.templates.order_placed.dlt_template_id' => 'dlt-order-placed',
        ]);
    }

    public function test_it_does_not_call_the_gateway_when_disabled(): void
    {
        config(['sms.enabled' => false]);
        Http::fake();

        $sent = app(SmsService::class)->send('9818663272', 'order_placed', ['orderId' => 1, 'total' => '199.00']);

        $this->assertTrue($sent);
        Http::assertNothingSent();
    }

    public function test_it_renders_the_template_and_calls_the_gateway_with_expected_params(): void
    {
        $this->enableSms();
        Http::fake(['gateway.example.test/*' => Http::response('000|Success', 200)]);

        $sent = app(SmsService::class)->send('9818663272', 'order_placed', [
            'orderId' => 42,
            'total' => '1,229.00',
        ]);

        $this->assertTrue($sent);
        Http::assertSent(function ($request) {
            return str_starts_with($request->url(), 'https://gateway.example.test/SendSMS?')
                && $request['APIKey'] === 'test-api-key'
                && $request['senderid'] === 'TESTIN'
                && $request['channel'] === '2'
                && $request['DCS'] === '0'
                && $request['flashsms'] === '0'
                && $request['number'] === '919818663272'
                && $request['text'] === 'Your Dailzo order #42 has been placed successfully. Order total: Rs.1,229.00. Track it in the app.'
                && $request['route'] === '47'
                && $request['EntityId'] === 'test-entity'
                && $request['dlttemplateid'] === 'dlt-order-placed';
        });
    }

    public function test_it_normalizes_a_plain_10_digit_number_to_the_91_prefixed_format(): void
    {
        $this->enableSms();
        Http::fake(['gateway.example.test/*' => Http::response('000', 200)]);

        app(SmsService::class)->send('9818663272', 'order_placed', ['orderId' => 1, 'total' => '1']);

        Http::assertSent(fn ($request) => $request['number'] === '919818663272');
    }

    public function test_it_returns_false_for_an_unrecognizable_phone_number(): void
    {
        $this->enableSms();
        Http::fake();

        $sent = app(SmsService::class)->send('123', 'order_placed', ['orderId' => 1, 'total' => '1']);

        $this->assertFalse($sent);
        Http::assertNothingSent();
    }

    public function test_it_returns_false_when_the_gateway_request_fails(): void
    {
        $this->enableSms();
        Http::fake(['gateway.example.test/*' => Http::response('error', 500)]);

        $sent = app(SmsService::class)->send('9818663272', 'order_placed', ['orderId' => 1, 'total' => '1']);

        $this->assertFalse($sent);
    }

    public function test_it_returns_false_for_an_unconfigured_template(): void
    {
        $this->enableSms();
        Http::fake();

        $sent = app(SmsService::class)->send('9818663272', 'not_a_real_template', []);

        $this->assertFalse($sent);
        Http::assertNothingSent();
    }
}
