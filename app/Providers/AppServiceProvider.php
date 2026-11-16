<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\Approvals\ApprovalAccepted;
use App\Events\Approvals\ApprovalCancelled;
use App\Events\Approvals\ApprovalDeclined;
use App\Events\Approvals\ApprovalRequested;
use App\Events\Contacts\ContactCreated;
use App\Events\Contacts\ContactDeleted;
use App\Events\Contacts\ContactRenewed;
use App\Events\Contacts\ContactUpdated;
use App\Events\FeatureToggled;
use App\Events\Memberships\MemberAdded;
use App\Events\Memberships\MemberLeft;
use App\Events\Memberships\MemberRemoved;
use App\Events\Memberships\MemberRoleChanged;
use App\Events\Organizations\OrganizationCreated;
use App\Events\Organizations\OrganizationUpdated;
use App\Listeners\RecordAuditLog;
use App\Support\Feature\ContactDeletion;
use App\Support\Feature\MemberDeletion;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
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

        Event::listen([
            ApprovalAccepted::class,
            ApprovalCancelled::class,
            ApprovalDeclined::class,
            ApprovalRequested::class,
            ContactCreated::class,
            ContactDeleted::class,
            ContactRenewed::class,
            ContactUpdated::class,
            FeatureToggled::class,
            MemberAdded::class,
            MemberLeft::class,
            MemberRemoved::class,
            MemberRoleChanged::class,
            OrganizationCreated::class,
            OrganizationUpdated::class,
        ], RecordAuditLog::class);

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
