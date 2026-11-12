<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Registration;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRegistrationEnabled
{
    /**
     * Hide the registration routes unless the root tenant enabled them.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('register', 'register.store') && ! Registration::enabled()) {
            abort(404);
        }

        return $next($request);
    }
}
