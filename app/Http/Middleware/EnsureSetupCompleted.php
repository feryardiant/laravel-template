<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Actions\ProvisionTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSetupCompleted
{
    public function __construct(private ProvisionTenant $provisioning)
    {
        // .
    }

    /**
     * Redirect every web request to the setup page until the root tenant exists.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->provisioning->isRequired()) {
            return $next($request);
        }

        if ($request->routeIs('setup') || $request->is('livewire*') || $request->is('up')) {
            return $next($request);
        }

        return redirect()->route('setup');
    }
}
