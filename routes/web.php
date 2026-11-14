<?php

declare(strict_types=1);

use App\Http\Controllers\ContactLookupController;
use App\Http\Controllers\NotificationPollerController;
use App\Http\Controllers\StoredFileController;
use App\Http\Controllers\UpdatePreferencesController;
use App\Http\Controllers\VerifyNewUserEmailController;
use App\Livewire\Contacts\Index as ContactIndex;
use App\Livewire\Contacts\View as ContactView;
use App\Livewire\Members\Index as MemberIndex;
use App\Livewire\Members\Show as MemberShow;
use App\Livewire\Notifications\Index as NotificationIndex;
use App\Livewire\OrgSettings;
use App\Livewire\Settings\Organizations;
use App\Livewire\Settings\Preferences;
use App\Livewire\Settings\Profile;
use App\Livewire\Settings\Security;
use App\Livewire\Setup;
use Illuminate\Support\Facades\Route;

Route::livewire('setup', Setup::class)->name('setup');

Route::redirect('/', 'dashboard');

Route::get('email/verify-new/{id}/{hash}', VerifyNewUserEmailController::class)
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify.new');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::livewire('members', MemberIndex::class)->name('members.index');
    Route::livewire('members/{person}', MemberShow::class)->name('members.show');

    Route::livewire('contacts', ContactIndex::class)->name('contacts.index');
    Route::get('contacts/lookup', ContactLookupController::class)
        ->middleware('throttle:60,1')
        ->name('contacts.lookup');
    Route::livewire('contacts/{stakeholder}', ContactView::class)->name('contacts.view');

    Route::prefix('org-settings')->group(function () {
        Route::redirect('', 'org-settings/general');

        Route::livewire('general', OrgSettings\General::class)->name('org-settings.general');
        Route::livewire('features', OrgSettings\Features::class)->name('org-settings.features');
        Route::livewire('subsidiaries', OrgSettings\Subsidiaries::class)->name('org-settings.subsidiaries');
    });

    Route::livewire('notifications', NotificationIndex::class)->name('notifications.index');
    Route::get('notifications/latest', NotificationPollerController::class)->name('notifications.latest');
    Route::post('notifications/latest/ack', [NotificationPollerController::class, 'ack'])->name('notifications.latest.ack');
});

Route::middleware(['auth'])->group(function () {
    Route::get('uploads/{path}', StoredFileController::class)->where('path', '.*')->name('uploads');

    Route::redirect('settings', 'settings/profile');

    Route::livewire('settings/profile', Profile::class)->name('profile.edit');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('settings/preferences', Preferences::class)->name('preferences.edit');
    Route::patch('settings/preferences', UpdatePreferencesController::class)->name('preferences.update');

    Route::livewire('settings/organizations', Organizations::class)
        ->middleware(['password.confirm'])
        ->name('organizations.index');

    Route::livewire('settings/security', Security::class)
        ->middleware(['password.confirm'])
        ->name('security.edit');
});

if (app()->environment(['local', 'testing']) && is_dir(base_path('routes/ui'))) {
    Route::prefix('ui')->group(base_path('routes/ui/index.php'));
}
