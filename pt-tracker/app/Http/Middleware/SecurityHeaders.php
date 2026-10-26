<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        if (! $response->headers->has('X-Frame-Options')) {
            $response->headers->set('X-Frame-Options', 'DENY');
        }
        $response->headers->set('Referrer-Policy', 'no-referrer');
        // Laravel responses can contain account data, CSRF tokens or invite links.
        // Static assets served directly by the web server keep their own caching.
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
