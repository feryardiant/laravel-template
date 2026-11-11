<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Notifications\UserRoleChangedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('accessibilities', function () {
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

    it('focuses the notification flyout and returns focus to its trigger', function () {
        $owner = browserMember(Role::Owner);
        $owner->notify(new UserRoleChangedNotification(browserMember(Role::Member), Role::Admin));

        $page = browserLogin($owner)->navigate('/notifications');

        $page->click('[data-flux-row] button[wire\:click\.stop]:not([role="checkbox"])')
            ->wait(0.5)
            ->assertScript(<<<'JS'
                (() => {
                    const dialog = document.querySelector("dialog[open]");

                    return !!dialog
                        && !!document.getElementById(dialog.getAttribute("aria-labelledby"))
                        && document.activeElement.closest("dialog") === dialog;
                })()
                JS)
            ->keys('dialog[open]', 'Escape')
            ->wait(0.5)
            ->assertScript('document.querySelectorAll("dialog[open]").length === 0')
            ->assertScript('document.activeElement.getAttribute("wire:click.stop") !== null');
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
});

describe('consistencies', function () {
    it('mutes read notification titles and descriptions', function () {
        $owner = browserMember(Role::Member);
        $actor = browserMember(Role::Member);

        $owner->notify(new UserRoleChangedNotification($actor, Role::Admin));
        $owner->notify(new UserRoleChangedNotification($actor, Role::Member));
        $owner->notifications()->latest()->first()->markAsRead();

        browserLogin($owner)
            ->navigate('/notifications')
            ->assertScript(<<<'JS'
                (() => {
                    const luminance = (color) => {
                        const [r, g, b] = color.match(/\d+/g).map(Number).slice(0, 3).map((channel) => {
                            const value = channel / 255;

                            return value <= 0.03928 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4;
                        });

                        return 0.2126 * r + 0.7152 * g + 0.0722 * b;
                    };
                    const contrast = (foreground) => {
                        const background = getComputedStyle(document.body).backgroundColor;
                        const lighter = Math.max(luminance(foreground), luminance(background));
                        const darker = Math.min(luminance(foreground), luminance(background));

                        return (lighter + 0.05) / (darker + 0.05);
                    };
                    const items = [...document.querySelectorAll('[data-flux-row]')].map((row) => {
                        const title = row.querySelector('button[wire\\:click\\.stop]:not([role="checkbox"])');
                        const description = title.parentElement.querySelector('div.text-sm');

                        return {
                            state: title.textContent.includes('Sudah dibaca') ? 'read' : 'unread',
                            title: getComputedStyle(title).color,
                            description: getComputedStyle(description).color,
                        };
                    });
                    const read = items.find((item) => item.state === 'read');
                    const unread = items.find((item) => item.state === 'unread');

                    return contrast(read.title) < contrast(unread.title)
                        && contrast(read.description) < contrast(unread.description)
                        && contrast(read.title) >= 4.5
                        && contrast(read.description) >= 4.5;
                })()
                JS);
    });
});

describe('usabilities', function () {
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

    it('keeps an unread indicator on touch devices', function () {
        $owner = browserMember(Role::Member);
        $owner->notify(new UserRoleChangedNotification(browserMember(Role::Member), Role::Admin));
        $owner->notify(new UserRoleChangedNotification(browserMember(Role::Member), Role::Member));
        $owner->notifications()->latest()->first()->markAsRead();

        $page = browserLogin($owner, 'mobile')->navigate('/notifications');

        $page->assertScript(<<<'JS'
            (() => {
                const dots = [...document.querySelectorAll("[data-unread-dot]")];

                return dots.length === 1 && getComputedStyle(dots[0]).display !== "none";
            })()
            JS);
    });

    it('selects notifications by tapping the checkbox on touch screens', function () {
        $owner = browserMember(Role::Member);
        $owner->notify(new UserRoleChangedNotification(browserMember(Role::Member), Role::Admin));

        $page = browserLogin($owner, 'mobile')->navigate('/notifications');

        $page->assertScript(<<<'JS'
            (() => {
                const checkbox = document.querySelector("button[aria-label^=\"Pilih notifikasi\"]");
                const envelope = checkbox.parentElement.querySelector("svg");

                return getComputedStyle(checkbox).opacity === "1"
                    && getComputedStyle(checkbox).pointerEvents === "auto"
                    && getComputedStyle(envelope).opacity === "0";
            })()
            JS)
            ->click('button[aria-label^="Pilih notifikasi"]')
            ->wait(0.4)
            ->assertScript('document.querySelector("button[aria-label^=\"Pilih notifikasi\"]").getAttribute("aria-checked") === "true"');
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
});

it('lists only the signed-in user notifications', function () {
    $owner = browserMember(Role::Member);
    $actor = browserMember(Role::Member);
    $otherActor = browserMember(Role::Member);

    $owner->notify(new UserRoleChangedNotification($actor, Role::Admin));
    browserMember(Role::Member)->notify(new UserRoleChangedNotification($otherActor, Role::Admin));

    browserLogin($owner)
        ->navigate('/notifications')
        ->assertSee($actor->name)
        ->assertDontSee($otherActor->name);
});

it('shows an empty state when there are no notifications', function () {
    $member = browserMember(Role::Member);

    browserLogin($member)
        ->navigate('/notifications')
        ->assertSee(__('No notifications yet.'));
});

it('opens a notification, marks it read, and updates the menu count', function () {
    $owner = browserMember(Role::Member);
    $owner->notify(new UserRoleChangedNotification(browserMember(Role::Member), Role::Admin));

    $page = browserLogin($owner)->navigate('/notifications');

    $page->assertScript('[...document.querySelectorAll("[role=status]")].some((el) => el.textContent.includes("1"))')
        ->click('[data-flux-row] button[wire\:click\.stop]:not([role="checkbox"])')
        ->wait(0.6)
        ->assertScript(<<<'JS'
            (() => {
                const dialog = document.querySelector("dialog[open]");

                return !!dialog && dialog.textContent.includes("Peran Anda di");
            })()
            JS)
        ->assertScript('[...document.querySelectorAll("[role=status]")].some((el) => el.textContent.includes("Tidak ada notifikasi belum dibaca"))');
});

it('opens a notification from a deep link', function () {
    $owner = browserMember(Role::Member);
    $owner->notify(new UserRoleChangedNotification(browserMember(Role::Member), Role::Admin));

    $notification = $owner->notifications()->firstOrFail();

    browserLogin($owner)
        ->navigate("/notifications?open={$notification->id}")
        ->wait(0.5)
        ->assertScript('document.querySelector("dialog[open]") !== null')
        ->assertScript('window.location.search === ""');

    expect($notification->refresh()->read_at)->not->toBeNull();
});

it('marks selected notifications read and unread in bulk', function () {
    $owner = browserMember(Role::Member);
    $owner->notify(new UserRoleChangedNotification(browserMember(Role::Member), Role::Admin));
    $owner->notify(new UserRoleChangedNotification(browserMember(Role::Member), Role::Member));

    $page = browserLogin($owner, 'mobile')->navigate('/notifications');

    $page->click('button[aria-label="Pilih semua notifikasi"]')
        ->wait(0.4)
        ->click('button[aria-label="Tandai sudah dibaca"]')
        ->wait(0.8)
        ->assertSee('Berhasil menandai 2 notifikasi sebagai sudah dibaca')
        ->click('button[aria-label="Pilih semua notifikasi"]')
        ->wait(0.4)
        ->click('button[aria-label="Tandai belum dibaca"]')
        ->wait(0.8)
        ->assertSee('Berhasil menandai 2 notifikasi sebagai belum dibaca');
});

it('shows a toast when a new notification arrives', function () {
    $owner = browserMember(Role::Member);
    $owner->notify(new UserRoleChangedNotification(browserMember(Role::Member), Role::Admin));

    $page = browserLogin($owner)->navigate('/dashboard');

    $page->wait(1);

    $actor = browserMember(Role::Member);
    $owner->notify(new UserRoleChangedNotification($actor, Role::Member));

    $page->wait(7)
        ->assertSee($actor->name)
        ->assertScript('[...document.querySelectorAll("[role=status]")].some((el) => el.textContent.includes("2"))');
});
