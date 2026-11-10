<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Notifications\UserRoleChangedNotification;

it('keeps the login form usable on mobile', function () {
    $member = browserMember(Role::Member);

    $page = visit('/login')->on()->mobile();

    $page->assertScript('document.documentElement.scrollWidth <= window.innerWidth');

    $page->fill('email', $member->email)
        ->fill('password', 'password')
        ->click('@login-button')
        ->assertPathIs('/dashboard');
});

it('opens the mobile navigation', function () {
    $member = browserMember(Role::Member);

    $page = browserLogin($member, 'mobile')->assertPathIs('/dashboard');

    $page->click('[data-flux-sidebar-toggle]')
        ->click('[data-flux-sidebar] a[href$="/members"]')
        ->assertPathIs('/members');
});

it('keeps the users page within the mobile viewport', function () {
    $owner = browserMember(Role::Owner);

    browserLogin($owner, 'mobile')
        ->navigate('/members')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth');
});

it('reaches notifications from the mobile user menu', function () {
    $member = browserMember(Role::Member);

    browserLogin($member, 'mobile')
        ->navigate('/dashboard')
        ->click('header button[aria-haspopup]')
        ->wait(0.3)
        ->click('header a[href$="/notifications"]')
        ->assertPathIs('/notifications');
});

it('keeps the notifications page within the mobile viewport', function () {
    $owner = browserMember(Role::Member);
    $owner->notify(new UserRoleChangedNotification(browserMember(Role::Member), Role::Admin));

    browserLogin($owner, 'mobile')
        ->navigate('/notifications')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth');
});

it('exposes user actions in the mobile overflow menu', function () {
    $owner = browserMember(Role::Owner);
    browserMember(Role::Member);

    $page = browserLogin($owner, 'mobile')->navigate('/members');

    $page->click('ui-checkbox[value]')
        ->wait(0.3)
        ->click('button[aria-label="Tindakan"]')
        ->wait(0.3)
        ->assertSee(__('Add member'))
        ->assertSee(__('Remove selected members'));
});

it('keeps the edit flyout actions within the mobile viewport', function () {
    $owner = browserMember(Role::Owner);
    $member = browserMember(Role::Member);

    $page = browserLogin($owner, 'mobile')->navigate('/members');

    $page->click("[aria-label=\"Tindakan untuk {$member->name}\"]")
        ->wait(0.3)
        ->click('Edit anggota')
        ->wait(0.6)
        ->assertScript('(() => {
            const save = [...document.querySelectorAll("dialog[open] button")]
                .find((el) => el.textContent.trim() === "Simpan");

            if (!save) {
                return false;
            }

            const rect = save.getBoundingClientRect();

            return rect.top >= 0 && rect.bottom <= window.innerHeight;
        })()');
});

it('selects users by tapping the checkbox on touch screens', function () {
    $owner = browserMember(Role::Owner);
    browserMember(Role::Member);

    $page = browserLogin($owner, 'mobile')->navigate('/members');

    $page->assertScript('(() => {
        const row = document.querySelector("[data-flux-row]:has(ui-checkbox)");
        const checkbox = row.querySelector("ui-checkbox[value]");
        const avatar = row.querySelector("a span[aria-hidden=\"true\"]");

        return getComputedStyle(checkbox).opacity === "1"
            && getComputedStyle(checkbox).pointerEvents === "auto"
            && getComputedStyle(avatar).opacity === "0";
    })()')
        ->click('ui-checkbox[value]')
        ->wait(0.4)
        ->assertScript('document.querySelector("ui-checkbox[value]").getAttribute("aria-checked") === "true"');
});

it('keeps an unread indicator on touch devices', function () {
    $owner = browserMember(Role::Member);
    $owner->notify(new UserRoleChangedNotification(browserMember(Role::Member), Role::Admin));
    $owner->notify(new UserRoleChangedNotification(browserMember(Role::Member), Role::Member));
    $owner->notifications()->latest()->first()->markAsRead();

    $page = browserLogin($owner, 'mobile')->navigate('/notifications');

    $page->assertScript('(() => {
        const dots = [...document.querySelectorAll("[data-unread-dot]")];

        return dots.length === 1 && getComputedStyle(dots[0]).display !== "none";
    })()');
});

it('selects a user when tapping the checkbox wrapper', function () {
    $owner = browserMember(Role::Owner);
    browserMember(Role::Member);

    $page = browserLogin($owner, 'mobile')->navigate('/members');

    $page->script(<<<'JS'
        document.querySelector('[data-flux-row]:has(ui-checkbox) span.absolute')
            .dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, view: window }))
        JS);
    $page->wait(0.4);

    $page->assertPathIs('/members')
        ->assertScript('document.querySelector("ui-checkbox[value]").getAttribute("aria-checked") === "true"');
});

it('selects notifications by tapping the checkbox on touch screens', function () {
    $owner = browserMember(Role::Member);
    $owner->notify(new UserRoleChangedNotification(browserMember(Role::Member), Role::Admin));

    $page = browserLogin($owner, 'mobile')->navigate('/notifications');

    $page->assertScript('(() => {
        const checkbox = document.querySelector("button[aria-label^=\"Pilih notifikasi\"]");
        const envelope = checkbox.parentElement.querySelector("svg");

        return getComputedStyle(checkbox).opacity === "1"
            && getComputedStyle(checkbox).pointerEvents === "auto"
            && getComputedStyle(envelope).opacity === "0";
    })()')
        ->click('button[aria-label^="Pilih notifikasi"]')
        ->wait(0.4)
        ->assertScript('document.querySelector("button[aria-label^=\"Pilih notifikasi\"]").getAttribute("aria-checked") === "true"');
});

it('reveals user selection on hover on desktop', function () {
    $owner = browserMember(Role::Owner);
    browserMember(Role::Member);

    browserLogin($owner)
        ->navigate('/members')
        ->assertScript('getComputedStyle(document.querySelector("ui-checkbox[value]")).opacity === "0"')
        ->hover('[data-flux-row]:has(ui-checkbox)')
        ->wait(0.5)
        ->assertScript('getComputedStyle(document.querySelector("ui-checkbox[value]")).opacity === "1"');
});

it('reveals notification selection on hover on desktop', function () {
    $owner = browserMember(Role::Member);
    $owner->notify(new UserRoleChangedNotification(browserMember(Role::Member), Role::Admin));
    $owner->notify(new UserRoleChangedNotification(browserMember(Role::Member), Role::Member));
    $owner->notifications()->latest()->first()->markAsRead();

    $page = browserLogin($owner)->navigate('/notifications');

    $page->assertScript('[...document.querySelectorAll("[data-flux-row]")].every((row) => getComputedStyle(row).cursor === "pointer")')
        ->hover('[data-flux-row]:has(button[aria-label*="Belum dibaca"])')
        ->wait(0.5)
        ->assertScript(<<<'JS'
            (() => {
                const row = document.querySelector('[data-flux-row]:has(button[aria-label*="Belum dibaca"])');
                const checkbox = row.querySelector('button[aria-label^="Pilih notifikasi"]');

                return getComputedStyle(checkbox).opacity === '1';
            })()
            JS);
});

it('switches to light mode and remembers it after navigation', function () {
    $member = browserMember(Role::Member);

    $page = browserLogin($member)->navigate('/settings/preferences');

    $page->click('Terang')
        ->wait(0.5)
        ->assertScript('! document.documentElement.classList.contains("dark")');

    $page->navigate('/dashboard')
        ->wait(0.5)
        ->assertScript('! document.documentElement.classList.contains("dark")');
});
