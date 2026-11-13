<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ThrottleRegistration
{
    /**
     * Limit public registration attempts per IP.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('register.store')) {
            $allowed = RateLimiter::attempt(
                'registration:'.$request->ip(),
                3,
                fn (): bool => true,
                60,
            );

            if (! $allowed) {
                abort(429);
            }
        }

        return $next($request);
    }
}
