<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Notifications\UserRoleChangedNotification;

it('has no serious accessibility issues on the login page', function (string $device) {
    visit('/login')
        ->on()
        ->{$device}()
        ->assertNoAccessibilityIssues()
        ->assertNoJavaScriptErrors();
})->with(['desktop', 'mobile']);

it('has no serious accessibility issues on the users page', function (string $device) {
    $owner = browserMember(Role::Owner);

    browserLogin($owner, $device)
        ->navigate('/members')
        ->assertNoAccessibilityIssues()
        ->assertNoJavaScriptErrors();
})->with(['desktop', 'mobile']);

it('can sign in using only the keyboard', function () {
    $owner = browserMember(Role::Owner);

    $page = visit('/login');

    $page->assertScript('document.activeElement.name === "email"')
        ->typeSlowly('email', $owner->email)
        ->keys('input[name="email"]', 'Tab')
        ->assertScript('document.activeElement.name === "password"')
        ->typeSlowly('password', 'password')
        ->keys('input[name="password"]', 'Enter');

    $page->assertPathIs('/dashboard');
});

it('moves focus into a modal and back to its trigger', function () {
    $owner = browserMember(Role::Owner);

    $page = browserLogin($owner)->navigate('/members');

    $page->click('Tambah anggota')
        ->wait(0.3)
        ->assertScript('!! document.activeElement.closest("dialog")')
        ->keys('dialog[open]', 'Escape')
        ->wait(0.3)
        ->assertScript('document.querySelectorAll("dialog[open]").length === 0')
        ->assertScript('document.activeElement.textContent.trim() === "Tambah anggota"');
});

it('has no serious accessibility issues on the notifications page', function (string $device) {
    $owner = browserMember(Role::Owner);
    $owner->notify(new UserRoleChangedNotification(browserMember(Role::Member), Role::Admin));
    $owner->notify(new UserRoleChangedNotification(browserMember(Role::Member), Role::Member));
    $owner->notifications()->latest()->first()->markAsRead();

    browserLogin($owner, $device)
        ->navigate('/notifications')
        ->assertNoAccessibilityIssues()
        ->assertNoJavaScriptErrors();
})->with(['desktop', 'mobile']);

it('has no serious accessibility issues on the preferences page', function (string $device) {
    $owner = browserMember(Role::Owner);

    browserLogin($owner, $device)
        ->navigate('/settings/preferences')
        ->assertNoAccessibilityIssues()
        ->assertNoJavaScriptErrors();
})->with(['desktop', 'mobile']);

it('has no serious accessibility issues on the user detail page', function () {
    $owner = browserMember(Role::Owner);
    $member = browserMember(Role::Member);

    browserLogin($owner)
        ->navigate("/members/{$member->person->uuid}")
        ->assertNoAccessibilityIssues()
        ->assertNoJavaScriptErrors();
});

it('exposes the users table sort direction', function () {
    $owner = browserMember(Role::Owner);

    browserLogin($owner)
        ->navigate('/members')
        ->assertScript('document.querySelector("[aria-sort]")?.getAttribute("aria-sort") === "descending"')
        ->click('Bergabung pada')
        ->wait(0.4)
        ->assertScript('document.querySelector("[aria-sort]")?.getAttribute("aria-sort") === "ascending"');
});

it('focuses the notification flyout and returns focus to its trigger', function () {
    $owner = browserMember(Role::Owner);
    $owner->notify(new UserRoleChangedNotification(browserMember(Role::Member), Role::Admin));

    $page = browserLogin($owner)->navigate('/notifications');

    $page->click('[data-flux-row] button[wire\:click\.stop]:not([role="checkbox"])')
        ->wait(0.5)
        ->assertScript('(() => {
            const dialog = document.querySelector("dialog[open]");

            return !!dialog
                && !!document.getElementById(dialog.getAttribute("aria-labelledby"))
                && document.activeElement.closest("dialog") === dialog;
        })()')
        ->keys('dialog[open]', 'Escape')
        ->wait(0.5)
        ->assertScript('document.querySelectorAll("dialog[open]").length === 0')
        ->assertScript('document.activeElement.getAttribute("wire:click.stop") !== null');
});

it('exposes tri-state user selection to assistive technology', function () {
    $owner = browserMember(Role::Owner);
    $first = browserMember(Role::Member);
    browserMember(Role::Member);

    $page = browserLogin($owner)->navigate('/members');

    $page->click('input[aria-label="Pilih semua anggota"]')
        ->wait(0.4)
        ->assertScript('[...document.querySelectorAll("ui-checkbox[value]")].every((el) => el.getAttribute("aria-checked") === "true")')
        ->assertScript('[...document.querySelectorAll("ui-checkbox[value]")].every((el) => (el.getAttribute("aria-label") || "").startsWith("Pilih "))')
        ->click('ui-checkbox[value="'.$first->person->id.'"]')
        ->wait(0.4)
        ->assertScript('document.querySelector("input[aria-label=\"Pilih semua anggota\"]").indeterminate === true');
});

it('announces unread notification counts to screen readers', function () {
    $owner = browserMember(Role::Owner);
    $actor = browserMember(Role::Member);
    $owner->notify(new UserRoleChangedNotification($actor, Role::Admin));
    $owner->notify(new UserRoleChangedNotification($actor, Role::Member));

    $page = browserLogin($owner)->navigate('/notifications');

    $page->assertScript('[...document.querySelectorAll("[role=status]")].some((el) => el.textContent.includes("2"))')
        ->click('button[aria-label="Pilih semua notifikasi"]')
        ->wait(0.4)
        ->click('button[aria-label="Tandai sudah dibaca"]')
        ->wait(0.8)
        ->assertScript('[...document.querySelectorAll("[role=status]")].some((el) => el.textContent.includes("Tidak ada notifikasi belum dibaca"))');
});

it('exposes a level one heading on the dashboard', function () {
    $owner = browserMember(Role::Owner);

    browserLogin($owner)
        ->navigate('/dashboard')
        ->assertScript('(() => {
            const headings = [...document.querySelectorAll("h1")];

            return headings.length === 1 && headings[0].textContent.trim() === "Dasbor";
        })()');
});
