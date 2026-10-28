<?php

namespace App\Providers;

use App\Models\User;
use App\Services\TemporaryPosePhotos;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Cache\RateLimiting\Unlimited;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
        $this->app->scoped(TemporaryPosePhotos::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
        if (! $this->app->environment('local', 'testing')) {
            URL::forceRootUrl((string) config('app.url'));
            URL::forceScheme((string) parse_url((string) config('app.url'), PHP_URL_SCHEME));
        }
        ResetPassword::toMailUsing(function (User $user, string $token): MailMessage {
            $url = rtrim((string) config('app.url'), '/').'/reset-password/'.$token.'?email='.rawurlencode($user->email);

            return (new MailMessage)->subject('Reset your Stage-Ready password')
                ->greeting('Reset your password')
                ->line('Use the link below to choose a new password for Stage-Ready.')
                ->action('Reset password', $url)
                ->line('This link expires in '.config('auth.passwords.users.expire').' minutes. If you did not request a reset, you can ignore this email.');
        });
    }

    private function configureRateLimiting(): void
    {
        // This outer limit runs before sessions, authentication and route binding.
        // Stripe deliveries have a separate bucket so browser traffic cannot consume it.
        RateLimiter::for('server', fn (Request $request): Limit => Limit::perMinute(600)
            ->by(($request->is('stripe/webhook') ? 'stripe:' : 'browser:').$request->ip()));

        RateLimiter::for('application', function (Request $request): array|Unlimited {
            if ($request->is('stripe/webhook')) {
                return Limit::none();
            }
            $actor = $this->requestActor($request);
            $limits = [Limit::perMinute(180)->by('requests:'.$actor)];
            if (! $request->isMethodSafe()) {
                $limits[] = Limit::perMinute(60)->by('writes:'.$actor);
            }

            return $limits;
        });

        RateLimiter::for('login', function (Request $request): array {
            return [
                Limit::perMinute(20)->by('ip:'.$request->ip()),
                Limit::perMinute(5)->by('login:'.$this->emailKey($request).'|'.$request->ip()),
            ];
        });
        RateLimiter::for('contact', fn (Request $request): Limit => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('password-email', fn (Request $request): array => [
            Limit::perMinute(5)->by('minute:'.$request->ip()),
            Limit::perHour(20)->by('hour:'.$request->ip()),
            Limit::perHour(5)->by('email:'.$this->emailKey($request)),
        ]);
        RateLimiter::for('password-reset', fn (Request $request): Limit => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('registration', fn (Request $request): array => [
            Limit::perMinute(5)->by('minute:'.$request->ip()),
            Limit::perHour(20)->by('hour:'.$request->ip()),
        ]);
        RateLimiter::for('verification', fn (Request $request): array => [
            Limit::perMinute(6)->by('minute:'.$this->requestActor($request)),
            Limit::perHour(20)->by('hour:'.$this->requestActor($request)),
        ]);
        RateLimiter::for('invitations', fn (Request $request): array => [
            Limit::perMinute(10)->by('minute:'.$this->requestActor($request)),
            Limit::perHour(60)->by('hour:'.$this->requestActor($request)),
        ]);
        RateLimiter::for('invitation-accept', fn (Request $request): Limit => Limit::perMinute(20)->by($request->ip()));
        foreach (['billing' => 10, 'uploads' => 20, 'photos' => 60, 'reports' => 20, 'analytics' => 30] as $name => $attempts) {
            RateLimiter::for($name, fn (Request $request): Limit => Limit::perMinute($attempts)->by($this->requestActor($request)));
        }
    }

    private function requestActor(Request $request): string
    {
        $id = $request->user()?->getAuthIdentifier();

        return $id === null ? 'ip:'.$request->ip() : 'user:'.$id;
    }

    private function emailKey(Request $request): string
    {
        $email = $request->input('email');

        return hash('sha256', is_string($email) ? mb_strtolower(trim($email)) : '');
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
