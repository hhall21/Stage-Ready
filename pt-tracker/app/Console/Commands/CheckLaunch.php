<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\StripeGateway;
use App\Support\CoachPricing;
use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Throwable;

class CheckLaunch extends Command
{
    protected $signature = 'stage-ready:check-launch {--staging : Require staging and Stripe test mode} {--verify-stripe : Read both configured Prices from Stripe without creating a customer or charge}';

    protected $description = 'Check deployment settings without changing application data or sending email';

    /** @var list<array{string, string, string}> */
    private array $checks = [];

    public function handle(StripeGateway $stripe, Migrator $migrator): int
    {
        $this->checks = [];
        $staging = (bool) $this->option('staging');
        $this->check(app()->environment($staging ? 'staging' : 'production'), 'Environment', 'Use APP_ENV='.($staging ? 'staging' : 'production').'.');
        $this->check(config('app.debug') === false, 'Debug disabled', 'Set APP_DEBUG=false.');
        $key = config('app.key');
        $decoded = is_string($key) && str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;
        $this->check(is_string($decoded) && Encrypter::supported($decoded, (string) config('app.cipher')), 'Application key', 'Generate APP_KEY once; preserve the existing key on updates.');
        $this->check($this->publicHttpsUrl(config('app.url')), 'Canonical HTTPS URL', 'Set APP_URL to the real public HTTPS address.');
        $this->check(config('session.secure') === true && config('session.http_only') === true && config('session.same_site') === 'lax', 'Session cookies', 'Use secure, HTTP-only cookies and SESSION_SAME_SITE=lax.');
        $this->check(config('session.encrypt') === true, 'Encrypted sessions', 'Set SESSION_ENCRYPT=true.');
        $this->check(in_array(config('cache.default'), ['database', 'redis'], true), 'Persistent shared cache', 'Use CACHE_STORE=database or redis for rate limits and locks.');
        $this->check(in_array(config('queue.default'), ['database', 'redis', 'sqs', 'beanstalkd'], true), 'Persistent queue', 'Use a persistent queue and supervise queue:work.');
        $this->check(in_array(config('session.driver'), ['database', 'redis'], true), 'Persistent sessions', 'Use SESSION_DRIVER=database or redis.');
        $proxies = config('trustedproxy.proxies', []);
        $this->check(is_array($proxies) && ! array_intersect($proxies, ['*', '**', '0.0.0.0/0', '::/0']), 'Restricted proxy trust', 'Leave TRUSTED_PROXIES empty, or list only the actual proxy IPs/CIDRs.');

        $this->check($this->publicEmail(config('stage-ready.support_email')), 'Public support inbox', 'Set SUPPORT_EMAIL to a monitored public inbox.');
        $this->check($this->publicHttpsUrl(config('stage-ready.privacy_url')), 'Privacy policy link', 'Publish the reviewed policy and set PRIVACY_POLICY_URL.');
        $this->check($this->publicHttpsUrl(config('stage-ready.terms_url')), 'Terms and cancellation link', 'Publish reviewed business terms and set TERMS_OF_SERVICE_URL.');
        foreach (['privacy' => 'privacy_url', 'terms' => 'terms_url'] as $document => $setting) {
            $url = config('stage-ready.'.$setting);
            if (is_string($url) && parse_url($url, PHP_URL_HOST) === parse_url((string) config('app.url'), PHP_URL_HOST)
                && parse_url($url, PHP_URL_PATH) === '/'.$document) {
                $path = rtrim((string) config('stage-ready.policies_path'), '/\\').DIRECTORY_SEPARATOR.$document.'.md';
                $this->check(is_file($path) && is_readable($path) && trim((string) file_get_contents($path)) !== '',
                    'Published '.$document.' document', 'Put the reviewed '.$document.'.md text in storage/app/policies.');
            }
        }
        $smtp = config('mail.mailers.smtp', []);
        $mailerReady = config('mail.default') === 'smtp' && is_array($smtp)
            && $this->publicHost($smtp['host'] ?? null) && filled($smtp['username'] ?? null) && filled($smtp['password'] ?? null)
            && in_array((int) ($smtp['port'] ?? 0), [465, 587, 2465, 2587], true);
        $this->check($mailerReady && $this->publicEmail(config('mail.from.address')), 'Transactional SMTP', 'Configure authenticated SMTP, a verified sender, and test actual inbox delivery.');

        $manifest = public_path('build/manifest.json');
        $assets = is_file($manifest) ? json_decode((string) file_get_contents($manifest), true) : null;
        $entryData = is_array($assets) ? ($assets['resources/js/app.ts'] ?? null) : null;
        $entry = is_array($entryData) ? ($entryData['file'] ?? null) : null;
        $this->check(is_string($entry) && is_file(public_path('build/'.$entry)) && ! is_file(public_path('hot')), 'Production frontend assets', 'Run npm ci and npm run build; remove a stale public/hot file.');
        $this->check(is_writable(storage_path()) && is_writable(base_path('bootstrap/cache')), 'Writable runtime directories', 'Allow the web, scheduler and queue user to write storage and bootstrap/cache.');
        foreach (['checkin_photos', 'temporary_checkin_photos'] as $disk) {
            $settings = config('filesystems.disks.'.$disk, []);
            $root = is_array($settings) ? ($settings['root'] ?? null) : null;
            $resolved = is_string($root) ? realpath($root) : false;
            $public = realpath(public_path());
            $private = $resolved !== false && $public !== false && ! $this->inside($resolved, $public);
            $this->check(is_array($settings) && ($settings['driver'] ?? null) === 'local' && ($settings['visibility'] ?? null) === 'private'
                && ($settings['serve'] ?? true) === false && $private && is_writable($resolved),
                'Private writable '.$disk, 'Create the persistent private directory outside public/ and grant the application user access.');
        }

        try {
            DB::connection()->getPdo();
            $this->check(true, 'Database connection', '');
            $pending = ! $migrator->repositoryExists() || array_diff(array_keys($migrator->getMigrationFiles(database_path('migrations'))), $migrator->getRepository()->getRan()) !== [];
            $this->check(! $pending, 'Database migrations', 'Run php artisan migrate --force.');
            if (! $pending) {
                $this->check(User::where('is_admin', true)->where('is_active', true)->where('role', 'coach')->exists(), 'Owner administrator', 'Run stage-ready:make-admin from the trusted server terminal.');
                $this->check(! User::whereIn('email', ['james@pttracker.test', 'adam@example.com'])->exists()
                    && ! User::where('billing_exempt', true)->where('is_admin', false)->exists(), 'No demo access', 'Use a clean hosted database without fictional/demo or exempt coach fixtures.');
            }
        } catch (Throwable) {
            $this->check(false, 'Database/schema access', 'Check DB settings, server availability, privileges and migrations.');
        }

        $modeMatches = (bool) config('services.stripe.live_mode') === ! $staging;
        $this->check($modeMatches && $stripe->configured(), 'Stripe credentials and mode', 'Use matching secret key, webhook secret and STRIPE_LIVE_MODE for this environment.');
        foreach (CoachPricing::plans() as $discipline => $plan) {
            $coach = (new User)->forceFill(['role' => 'coach', 'is_bodybuilding_coach' => $discipline === 'bodybuilding']);
            $ready = $modeMatches && $stripe->configured($coach);
            $this->check($ready, $plan['label'].' monthly Price ID', 'Set the matching STRIPE_'.strtoupper($discipline).'_PRICE_ID.');
            if ($ready && $this->option('verify-stripe')) {
                try {
                    $stripe->verifiedPriceId($coach);
                    $this->check(true, $plan['label'].' '.$plan['price_label'].'/month verified with Stripe', '');
                } catch (Throwable) {
                    $this->check(false, $plan['label'].' Price verification', 'Check connectivity and the active GBP monthly Price amount; no charge was created.');
                }
            }
        }

        $this->table(['Check', 'Status', 'Action if blocked'], $this->checks);
        $failed = count(array_filter($this->checks, fn ($check) => $check[1] === 'BLOCKED'));
        $this->line('This checks settings, schema and files. Verify real email delivery, webhook delivery, running workers/scheduler, policy content and backup restores separately.');
        if ($failed > 0) {
            $this->error($failed.' launch check(s) need attention. No application data was changed.');

            return self::FAILURE;
        }
        $this->info('Configuration checks passed. Complete the external acceptance checks before opening signup.');

        return self::SUCCESS;
    }

    private function check(bool $passed, string $label, string $fix): void
    {
        $this->checks[] = [$label, $passed ? 'PASS' : 'BLOCKED', $passed ? '' : $fix];
    }

    private function publicHttpsUrl(mixed $value): bool
    {
        return is_string($value) && filter_var($value, FILTER_VALIDATE_URL) !== false
            && parse_url($value, PHP_URL_SCHEME) === 'https' && parse_url($value, PHP_URL_USER) === null
            && parse_url($value, PHP_URL_PASS) === null && $this->publicHost(parse_url($value, PHP_URL_HOST));
    }

    private function publicEmail(mixed $value): bool
    {
        return is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false
            && $this->publicHost(substr($value, (int) strrpos($value, '@') + 1));
    }

    private function publicHost(mixed $host): bool
    {
        if (! is_string($host) || ! str_contains($host, '.') || filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return false;
        }
        $host = strtolower($host);
        foreach (['localhost', 'test', 'example', 'invalid', 'example.com', 'example.net', 'example.org', 'your_domain'] as $reserved) {
            if ($host === $reserved || str_ends_with($host, '.'.$reserved)) {
                return false;
            }
        }

        return true;
    }

    private function inside(string $path, string $directory): bool
    {
        $path = strtolower(str_replace('\\', '/', $path));
        $directory = rtrim(strtolower(str_replace('\\', '/', $directory)), '/');

        return $path === $directory || str_starts_with($path, $directory.'/');
    }
}
