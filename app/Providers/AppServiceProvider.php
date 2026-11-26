<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\Feature;
use App\Events\Auditable;
use App\Listeners\RecordAuditLog;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Pennant\Feature as PennantFeature;
use Livewire\Livewire;

/**
 * Registers the application's service bindings and event listeners.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerEloquentUserProvider();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Re-check password confirmation on Livewire requests to routes that use it.
        Livewire::addPersistentMiddleware(RequirePassword::class);

        // A Livewire update is a new request even though the test harness reuses
        // the underlying request object.
        Livewire::listen('hydrate', fn () => app(CurrentOrganization::class)->forget());

        foreach (Feature::cases() as $feature) {
            PennantFeature::define($feature, fn (): bool => $feature->default());
        }

        Event::listen(Auditable::class, RecordAuditLog::class);

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

    private function registerEloquentUserProvider(): void
    {
        Auth::provider('eloquent', function (Application $app, array $config): EloquentUserProvider {
            return new class($app->make(Hasher::class), $config['model']) extends EloquentUserProvider
            {
                /**
                 * {@inheritdoc}
                 */
                protected function newModelQuery($model = null)
                {
                    return parent::newModelQuery($model)->with('person');
                }
            };
        });
    }
}
