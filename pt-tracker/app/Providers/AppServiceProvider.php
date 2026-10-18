<?php

namespace App\Providers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        RateLimiter::for('login', fn (Request $request): Limit => Limit::perMinute(5)
            ->by(strtolower((string) $request->input('email')).'|'.$request->ip()));

        RateLimiter::for('contact', function (Request $request): Limit {
            return Limit::perMinute(5)->by($request->ip());
        });
        RateLimiter::for('password-email', fn (Request $request): Limit => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('invitations', fn (Request $request): Limit => Limit::perMinute(10)->by((string) $request->user()?->id));
        RateLimiter::for('invitation-accept', fn (Request $request): Limit => Limit::perMinute(20)->by($request->ip()));
        ResetPassword::toMailUsing(function (User $user, string $token): MailMessage {
            $url = rtrim((string) config('app.url'), '/').'/reset-password/'.$token.'?email='.rawurlencode($user->email);

            return (new MailMessage)->subject('Reset your Stage-Ready password')
                ->greeting('Reset your password')
                ->line('Use the link below to choose a new password for Stage-Ready.')
                ->action('Reset password', $url)
                ->line('This link expires in '.config('auth.passwords.users.expire').' minutes. If you did not request a reset, you can ignore this email.');
        });
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
