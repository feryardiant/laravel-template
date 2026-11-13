<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureRegistrationEnabled;
use App\Http\Middleware\EnsureSetupCompleted;
use App\Http\Middleware\SetUserLocale;
use App\Http\Middleware\ThrottleRegistration;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: \dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(
            prepend: [EnsureSetupCompleted::class],
            append: [EnsureRegistrationEnabled::class, ThrottleRegistration::class, SetUserLocale::class],
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
