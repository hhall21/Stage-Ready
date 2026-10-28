<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\StripeGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class LaunchReadinessTest extends TestCase
{
    use RefreshDatabase;

    private string $fixtureRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtureRoot = storage_path('framework/testing/launch-'.Str::uuid());
        foreach (['public/build', 'saved', 'temporary', 'policies'] as $directory) {
            File::ensureDirectoryExists($this->fixtureRoot.'/'.$directory);
        }
        $this->app->usePublicPath($this->fixtureRoot.'/public');
        File::put(public_path('build/app.js'), '// Fixture frontend');
        File::put(public_path('build/manifest.json'), json_encode(['resources/js/app.ts' => ['file' => 'app.js']], JSON_THROW_ON_ERROR));
        File::put($this->fixtureRoot.'/policies/privacy.md', 'Reviewed test privacy text.');
        File::put($this->fixtureRoot.'/policies/terms.md', 'Reviewed test terms text.');
        config(['app.debug' => false, 'app.url' => 'https://stage-ready.co.uk', 'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'session.secure' => true, 'session.http_only' => true, 'session.same_site' => 'lax', 'session.encrypt' => true,
            'session.driver' => 'database', 'cache.default' => 'database', 'queue.default' => 'database', 'trustedproxy.proxies' => [],
            'stage-ready.support_email' => 'support@stage-ready.co.uk', 'stage-ready.privacy_url' => 'https://stage-ready.co.uk/privacy',
            'stage-ready.terms_url' => 'https://stage-ready.co.uk/terms', 'stage-ready.policies_path' => $this->fixtureRoot.'/policies',
            'mail.default' => 'smtp', 'mail.from.address' => 'accounts@stage-ready.co.uk', 'mail.mailers.smtp.host' => 'smtp.resend.com',
            'mail.mailers.smtp.username' => 'resend', 'mail.mailers.smtp.password' => 'private-smtp-test-secret', 'mail.mailers.smtp.port' => 465,
            'filesystems.disks.checkin_photos.root' => $this->fixtureRoot.'/saved', 'filesystems.disks.temporary_checkin_photos.root' => $this->fixtureRoot.'/temporary',
            'services.stripe.secret' => 'sk_live_private-test-secret', 'services.stripe.webhook_secret' => 'whsec_private-test-secret',
            'services.stripe.prices.powerlifting' => 'price_powerlifting', 'services.stripe.prices.bodybuilding' => 'price_bodybuilding', 'services.stripe.live_mode' => true]);
        User::factory()->create(['is_admin' => true, 'role' => 'coach']);
        $this->app['env'] = 'production';
    }

    protected function tearDown(): void
    {
        $root = realpath($this->fixtureRoot);
        $parent = realpath(storage_path('framework/testing'));
        if ($root !== false && $parent !== false && str_starts_with($root, $parent.DIRECTORY_SEPARATOR)) {
            File::deleteDirectory($root);
        }
        parent::tearDown();
    }

    public function test_production_settings_can_be_checked_without_sending_mail_charging_or_changing_accounts(): void
    {
        $this->artisan('stage-ready:check-launch')->expectsOutputToContain('Configuration checks passed.')->assertExitCode(0);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('coach_memberships', 0);
        $this->assertDatabaseCount('coach_payments', 0);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_debug_local_mail_demo_accounts_and_public_photo_paths_block_launch(): void
    {
        config(['app.debug' => true, 'mail.default' => 'log', 'filesystems.disks.checkin_photos.root' => public_path('build'),
            'trustedproxy.proxies' => ['*']]);
        User::factory()->create(['email' => 'james@pttracker.test', 'role' => 'coach']);
        $this->artisan('stage-ready:check-launch')->expectsOutputToContain('BLOCKED')
            ->expectsOutputToContain('No application data was changed.')->assertExitCode(1);
        $this->assertDatabaseCount('users', 2);
    }

    public function test_staging_requires_test_mode_and_has_a_separate_explicit_check(): void
    {
        $this->app['env'] = 'staging';
        config(['services.stripe.secret' => 'sk_test_private-test-secret', 'services.stripe.live_mode' => false]);
        $this->artisan('stage-ready:check-launch', ['--staging' => true])->assertExitCode(0);
        $this->artisan('stage-ready:check-launch')->assertExitCode(1);
    }

    public function test_missing_policies_and_a_stale_vite_server_file_block_launch(): void
    {
        File::delete($this->fixtureRoot.'/policies/privacy.md');
        File::put(public_path('hot'), 'http://localhost:5173');
        $this->artisan('stage-ready:check-launch')->expectsOutputToContain('BLOCKED')->assertExitCode(1);
    }

    public function test_optional_price_verification_only_reads_both_plans(): void
    {
        $stripe = Mockery::mock(StripeGateway::class);
        $stripe->allows('configured')->andReturn(true);
        $stripe->expects('verifiedPriceId')->with(Mockery::on(fn (User $coach) => ! $coach->is_bodybuilding_coach))->once()->andReturn('price_powerlifting');
        $stripe->expects('verifiedPriceId')->with(Mockery::on(fn (User $coach) => $coach->is_bodybuilding_coach))->once()->andReturn('price_bodybuilding');
        $this->app->instance(StripeGateway::class, $stripe);
        $this->artisan('stage-ready:check-launch', ['--verify-stripe' => true])->assertExitCode(0);
        $this->assertDatabaseCount('coach_payments', 0);
        $this->assertDatabaseCount('coach_memberships', 0);
    }
}
