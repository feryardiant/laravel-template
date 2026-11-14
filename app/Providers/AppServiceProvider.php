<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Feature\ContactDeletion;
use App\Support\Feature\MemberDeletion;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Pennant\Feature;
use Livewire\Livewire;

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
        // Re-check password confirmation on Livewire requests to routes that use it.
        Livewire::addPersistentMiddleware(RequirePassword::class);

        // Deletion flags default to on; a tenant only stores a value when
        // a manager turns one off.
        Feature::define(MemberDeletion::FEATURE, fn (): bool => true);
        Feature::define(ContactDeletion::FEATURE, fn (): bool => true);

        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(app()->isProduction());

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
