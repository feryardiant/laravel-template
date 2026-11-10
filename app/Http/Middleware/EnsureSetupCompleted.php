<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Setup;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSetupCompleted
{
    /**
     * Redirect every web request to the setup page until the root tenant exists.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Setup::required()) {
            return $next($request);
        }

        if ($request->routeIs('setup') || $request->is('livewire*') || $request->is('up')) {
            return $next($request);
        }

        return redirect()->route('setup');
    }
}
