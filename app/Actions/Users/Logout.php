<?php

declare(strict_types=1);

namespace App\Actions\Users;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Logs the current user out and redirects to the login screen.
 */
final class Logout
{
    /**
     * Log the current user out of the application.
     */
    public function __invoke(): RedirectResponse
    {
        Auth::guard('web')->logout();

        Session::invalidate();
        Session::regenerateToken();

        return redirect()->route('login');
    }
}
