<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Stripe\Exception\ApiErrorException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustHosts(at: fn () => ['^'.preg_quote((string) parse_url((string) config('app.url'), PHP_URL_HOST), '/').'$'], subdomains: false);
        $middleware->validateCsrfTokens(except: ['stripe/webhook']);
        $middleware->prepend(SecurityHeaders::class);
        $middleware->append(ThrottleRequests::using('server'));
        $middleware->web(append: [
            'throttle:application',
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
        $middleware->api(append: ['throttle:application']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ApiErrorException $error, Request $request) {
            if ($request->is('api/billing/*', 'stripe/webhook')) {
                return response()->json(['message' => 'The payment provider is temporarily unavailable. Please try again shortly.'], 503);
            }

            return null;
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
