<?php

declare(strict_types=1);

use App\Enums\Locale;
use App\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

describe('accessibilities', function () {
    it('has no serious accessibility issues on the preferences page', function (string $device) {
        $owner = browserMember(Role::Owner);

        actingAs($owner);

        visit('/settings/preferences')->on()->{$device}()
            ->assertNoAccessibilityIssues()
            ->assertNoJavaScriptErrors();
    })->with(['desktop', 'mobile']);
});

describe('usabilities', function () {
    it('switches to light mode and remembers it after navigation', function () {
        $member = browserMember(Role::Member);

        actingAs($member);

        $page = visit('/settings/preferences');

        $page->click('Gelap')
            ->wait(0.5)
            ->assertScript('document.documentElement.classList.contains("dark")')
            ->click('Terang')
            ->wait(0.5)
            ->assertScript('! document.documentElement.classList.contains("dark")');

        $page->navigate('/dashboard')
            ->wait(0.5)
            ->assertScript('! document.documentElement.classList.contains("dark")');
    });
});

it('lets a user update their own profile', function () {
    $member = browserMember(Role::Member);

    actingAs($member);

    $page = visit('/settings/profile');

    $page->fill('name', 'Nama Baru')
        ->click('Simpan')
        ->assertSee(__('Profile updated.'));

    $page->navigate('/settings/profile')
        ->assertValue('name', 'Nama Baru');
});

it('updates locale and offers to reload the page', function () {
    $member = browserMember(Role::Member);

    actingAs($member);

    $page = visit('/settings/preferences');

    $page->click('Bahasa Inggris')
        ->assertSee('Preferensi Anda telah diperbarui.')
        ->assertScript('document.querySelector("[data-flux-toast-dialog] ui-close button")?.getAttribute("aria-label") === "Tutup"')
        ->click('Muat ulang halaman')
        ->wait(0.8)
        ->assertSee('Preferences');

    expect($member->refresh()->locale->value)->toBe('en');
});

it('keeps the latest rapid preference selection', function () {
    $member = browserMember(Role::Member);

    actingAs($member);

    $page = visit('/settings/preferences');

    $page->click('Bahasa Inggris')
        ->click('Bahasa Indonesia')
        ->wait(1.5);

    expect($member->refresh()->locale)->toBe(Locale::Indonesian);
});

it('applies the preferred locale to livewire updates', function () {
    $member = browserMember(Role::Member, ['locale' => Locale::English]);

    actingAs($member);

    $page = visit('/settings/profile');

    $page->fill('name', 'English Name')
        ->click('Save')
        ->assertSee(__('Profile updated.', [], 'en'));
});

it('shows a danger toast when preferences cannot be saved', function () {
    $member = browserMember(Role::Member, ['locale' => Locale::English]);

    actingAs($member);

    $page = visit('/settings/preferences');

    $page->script(<<<'JS'
        const select = document.querySelector('[x-model=timezone]');
        select.value = '';
        select.dispatchEvent(new Event('change', { bubbles: true }));
    JS);

    $page->assertSee('The timezone field is required.')
        ->assertScript('document.querySelector("[data-flux-toast-dialog][data-variant=danger]") !== null')
        ->assertScript('document.querySelector("[x-model=timezone]").value === "UTC"');
});

it('updates timezone and offers to reload the page', function () {
    $member = browserMember(Role::Member, ['timezone' => null]);

    actingAs($member);

    $page = visit('/settings/preferences');

    $page->select('[x-model=timezone]', 'Asia/Jakarta')
        ->assertSee('Preferensi Anda telah diperbarui.')
        ->click('Muat ulang halaman')
        ->wait(0.8)
        ->assertScript('document.querySelector("[x-model=timezone]").value === "Asia/Jakarta"');

    expect($member->refresh()->timezone)->toBe('Asia/Jakarta');
});
