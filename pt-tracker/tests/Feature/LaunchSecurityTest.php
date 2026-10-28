<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\StripeGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Mockery;
use Stripe\Exception\ApiConnectionException;
use Tests\TestCase;

class LaunchSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Request::setTrustedHosts([]);
    }

    protected function tearDown(): void
    {
        Request::setTrustedHosts([]);
        parent::tearDown();
    }

    public function test_staging_rejects_unexpected_hosts_and_accepts_only_the_canonical_host(): void
    {
        config(['app.url' => 'https://stage-ready.co.uk', 'app.debug' => false]);
        $this->app['env'] = 'staging';
        $this->getJson('https://attacker.invalid/up')->assertStatus(400);
        $this->getJson('https://stage-ready.co.uk/up')->assertOk();
        $this->getJson('https://unconfigured.stage-ready.co.uk/up')->assertStatus(400);
    }

    public function test_forwarded_ips_and_https_are_used_only_for_configured_proxies(): void
    {
        config(['app.url' => 'https://stage-ready.co.uk', 'trustedproxy.proxies' => ['203.0.113.10']]);
        $this->app['env'] = 'staging';
        Route::get('/_launch-proxy-test', fn (Request $request) => response()->json(['ip' => $request->ip(), 'secure' => $request->isSecure()]));
        $forwarded = ['X-Forwarded-For' => '198.51.100.9', 'X-Forwarded-Proto' => 'https'];
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->getJson('http://stage-ready.co.uk/_launch-proxy-test', $forwarded)->assertOk()->assertJsonPath('ip', '198.51.100.9')->assertJsonPath('secure', true);
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.20'])
            ->getJson('http://stage-ready.co.uk/_launch-proxy-test', $forwarded)->assertOk()->assertJsonPath('ip', '203.0.113.20')->assertJsonPath('secure', false);
    }

    public function test_stripe_connection_failures_do_not_leak_provider_details_or_grant_access(): void
    {
        config(['app.debug' => false, 'app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $coach = User::factory()->create(['role' => 'coach', 'billing_exempt' => false]);
        $stripe = Mockery::mock(StripeGateway::class);
        $stripe->allows('configured')->andReturn(true);
        $stripe->expects('createCustomer')->andThrow(ApiConnectionException::factory('private-provider-diagnostic'));
        $this->app->instance(StripeGateway::class, $stripe);
        $this->actingAs($coach)->postJson('/api/billing/checkout')->assertStatus(503)
            ->assertJsonPath('message', 'The payment provider is temporarily unavailable. Please try again shortly.')
            ->assertDontSee('private-provider-diagnostic');
        $this->assertDatabaseCount('coach_memberships', 0);
        $this->assertDatabaseCount('coach_payments', 0);
        $this->assertFalse($coach->hasPaidCoachAccess());
    }
}
