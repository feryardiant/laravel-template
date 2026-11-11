<?php

declare(strict_types=1);

use App\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

describe('accessibilities', function () {
    it('has no serious accessibility issues on the users page', function (string $device) {
        $owner = browserMember(Role::Owner);

        actingAs($owner);

        visit('/members')
            ->on()->{$device}()
            ->assertNoAccessibilityIssues()
            ->assertNoJavaScriptErrors();
    })->with(['desktop', 'mobile']);

    it('has no serious accessibility issues on the user detail page', function () {
        $owner = browserMember(Role::Owner);
        $member = browserMember(Role::Member);

        actingAs($owner);

        visit("/members/{$member->person->uuid}")
            ->assertNoAccessibilityIssues()
            ->assertNoJavaScriptErrors();
    });

    it('exposes the users table sort direction', function () {
        $owner = browserMember(Role::Owner);

        actingAs($owner);

        visit('/members')
            ->assertScript('document.querySelector("[aria-sort]")?.getAttribute("aria-sort") === "descending"')
            ->click('Bergabung pada')
            ->wait(0.4)
            ->assertScript('document.querySelector("[aria-sort]")?.getAttribute("aria-sort") === "ascending"');
    });

    it('exposes tri-state user selection to assistive technology', function () {
        $owner = browserMember(Role::Owner);
        $first = browserMember(Role::Member);
        browserMember(Role::Member);

        actingAs($owner);

        $page = visit('/members');

        $page->click('input[aria-label="Pilih semua anggota"]')
            ->wait(0.4)
            ->assertScript('[...document.querySelectorAll("ui-checkbox[value]")].every((el) => el.getAttribute("aria-checked") === "true")')
            ->assertScript('[...document.querySelectorAll("ui-checkbox[value]")].every((el) => (el.getAttribute("aria-label") || "").startsWith("Pilih "))')
            ->click('ui-checkbox[value="'.$first->person->id.'"]')
            ->wait(0.4)
            ->assertScript('document.querySelector("input[aria-label=\"Pilih semua anggota\"]").indeterminate === true');
    });
});

describe('consistencies', function () {
    it('labels the new controls in Indonesian', function () {
        $owner = browserMember(Role::Owner);
        browserMember(Role::Member);

        actingAs($owner);

        visit('/members')->on()->mobile()
            ->assertScript('document.querySelector("input[aria-label=\"Pilih semua anggota\"]") !== null')
            ->assertScript('(document.querySelector("ui-checkbox[value]").getAttribute("aria-label") || "").startsWith("Pilih ")')
            ->click('button[aria-label="Tindakan"]')
            ->wait(0.3)
            ->assertSee(__('Add member'));
    });

    it('uses pointer cursors for controls and default cursors for menu containers', function () {
        $owner = browserMember(Role::Owner);
        $first = browserMember(Role::Member);
        browserMember(Role::Member);

        actingAs($owner);

        $page = visit('/members');

        $page->hover('[data-flux-row]:has(ui-checkbox[value="'.$first->person->id.'"])')
            ->wait(0.3)
            ->click('ui-checkbox[value="'.$first->person->id.'"]')
            ->wait(0.3)
            ->click('Tindakan')
            ->wait(0.4)
            ->assertScript(<<<'JS'
                (() => {
                    const cursor = (element) => element ? getComputedStyle(element).cursor : null;
                    const menu = [...document.querySelectorAll('[role="menu"]')].at(-1);
                    const trigger = [...document.querySelectorAll('button')]
                        .find((button) => button.textContent.trim() === 'Tindakan');
                    const rowTrigger = document.querySelector('[aria-label^="Tindakan untuk"]:not([disabled])');

                    return cursor(trigger) === 'pointer'
                        && cursor(menu) === 'default'
                        && cursor(menu?.querySelector('[role="menuitem"]')) === 'pointer'
                        && cursor(rowTrigger) === 'pointer';
                })()
                JS);
    });
});

describe('usabilities', function () {
    it('keeps the users page within the mobile viewport', function () {
        $owner = browserMember(Role::Owner);

        actingAs($owner);

        visit('/members')->on()->mobile()
            ->assertScript('document.documentElement.scrollWidth <= window.innerWidth');
    });

    it('exposes user actions in the mobile overflow menu', function () {
        $owner = browserMember(Role::Owner);
        browserMember(Role::Member);

        actingAs($owner);

        $page = visit('/members')->on()->mobile();

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

        actingAs($owner);

        $page = visit('/members')->on()->mobile();

        $page->click("[aria-label=\"Tindakan untuk {$member->name}\"]")
            ->wait(0.3)
            ->click('Edit anggota')
            ->wait(0.6)
            ->assertScript(<<<'JS'
                (() => {
                    const save = [...document.querySelectorAll("dialog[open] button")]
                        .find((el) => el.textContent.trim() === "Simpan");

                    if (!save) {
                        return false;
                    }

                    const rect = save.getBoundingClientRect();

                    return rect.top >= 0 && rect.bottom <= window.innerHeight;
                })()
                JS);
    });

    it('selects users by tapping the checkbox on touch screens', function () {
        $owner = browserMember(Role::Owner);
        browserMember(Role::Member);

        actingAs($owner);

        $page = visit('/members')->on()->mobile();

        $page->assertScript(<<<'JS'
            (() => {
                const row = document.querySelector("[data-flux-row]:has(ui-checkbox)");
                const checkbox = row.querySelector("ui-checkbox[value]");
                const avatar = row.querySelector("a span[aria-hidden=\"true\"]");

                return getComputedStyle(checkbox).opacity === "1"
                    && getComputedStyle(checkbox).pointerEvents === "auto"
                    && getComputedStyle(avatar).opacity === "0";
            })()
            JS)
            ->click('ui-checkbox[value]')
            ->wait(0.4)
            ->assertScript('document.querySelector("ui-checkbox[value]").getAttribute("aria-checked") === "true"');
    });

    it('selects a user when tapping the checkbox wrapper', function () {
        $owner = browserMember(Role::Owner);
        browserMember(Role::Member);

        actingAs($owner);

        $page = visit('/members')->on()->mobile();

        $page->script(<<<'JS'
            document.querySelector('[data-flux-row]:has(ui-checkbox) span.absolute')
                .dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, view: window }))
            JS);
        $page->wait(0.4);

        $page->assertPathIs('/members')
            ->assertScript('document.querySelector("ui-checkbox[value]").getAttribute("aria-checked") === "true"');
    });

    it('reveals user selection on hover on desktop', function () {
        $owner = browserMember(Role::Owner);
        browserMember(Role::Member);

        actingAs($owner);

        visit('/members')
            ->assertScript('getComputedStyle(document.querySelector("ui-checkbox[value]")).opacity === "0"')
            ->hover('[data-flux-row]:has(ui-checkbox)')
            ->wait(0.5)
            ->assertScript('getComputedStyle(document.querySelector("ui-checkbox[value]")).opacity === "1"');
    });

    it('opens the mobile navigation', function () {
        $member = browserMember(Role::Member);

        actingAs($member);

        $page = visit('/dashboard')->on()->mobile();

        $page->click('[data-flux-sidebar-toggle]')
            ->click('[data-flux-sidebar] a[href$="/members"]')
            ->assertPathIs('/members');
    });
});

it('hides management actions on your own user detail page', function () {
    $admin = browserMember(Role::Admin);
    $member = browserMember(Role::Member);

    actingAs($admin);

    $page = visit("/members/{$admin->person->uuid}");

    $page->assertDontSee(__('Edit member'))
        ->assertDontSee(__('Demote admin'))
        ->navigate("/members/{$member->person->uuid}")
        ->assertSee(__('Edit member'));
});

it('lets an owner add a member', function () {
    $owner = browserMember(Role::Owner);

    actingAs($owner);

    $page = visit('/members');

    $page->click('Tambah anggota')
        ->fill('name', 'Budi Santoso')
        ->click('Buat kredensial masuk')
        ->wait(0.4)
        ->fill('email', 'budi@example.test')
        ->click('Tambah');

    $page->assertSee('Budi Santoso')
        ->assertSee('budi@example.test');
});

it('hides management controls from members on mobile', function () {
    $member = browserMember(Role::Member);

    actingAs($member);

    visit('/members')->on()->mobile()
        ->assertSee(__('Members'))
        ->assertDontSee(__('Add member'))
        ->assertMissing('[aria-label^="Tindakan untuk"]');
});

it('prevents admins from managing owners', function () {
    $admin = browserMember(Role::Admin);
    $owner = browserMember(Role::Owner);
    $member = browserMember(Role::Member);

    actingAs($admin);

    visit('/members')
        ->assertMissing("[aria-label=\"Tindakan untuk {$owner->name}\"]")
        ->assertPresent("[aria-label=\"Tindakan untuk {$member->name}\"]")
        ->assertMissing("[aria-label=\"Tindakan untuk {$admin->name}\"]");
});

it('deletes selected users in one batch', function () {
    $owner = browserMember(Role::Owner);
    $first = browserMember(Role::Member);
    $second = browserMember(Role::Member);

    actingAs($owner);

    $page = visit('/members')->on()->mobile();

    $page->click('input[aria-label="Pilih semua anggota"]')
        ->wait(0.5)
        ->click('button[aria-label="Tindakan"]')
        ->wait(0.6)
        ->click('[role="menu"]:popover-open [role="menuitem"]:last-of-type')
        ->wait(0.4)
        ->assertSee('Hapus 2 anggota terpilih dari organisasi ini?')
        ->click('button[wire\:click="deleteSelectedConfirmed"]')
        ->wait(0.8)
        ->assertSee('2 anggota berhasil dihapus.')
        ->assertDontSee($first->name)
        ->assertDontSee($second->name);
});

it('clears the user selection when sorting', function () {
    $owner = browserMember(Role::Owner);
    browserMember(Role::Member);
    browserMember(Role::Member);

    actingAs($owner);

    $page = visit('/members')->on()->mobile();

    $page->click('input[aria-label="Pilih semua anggota"]')
        ->wait(0.4)
        ->click('Bergabung pada')
        ->wait(0.5)
        ->assertScript('document.querySelectorAll("ui-checkbox[aria-checked=\"true\"]").length === 0')
        ->assertScript('document.querySelector("input[aria-label=\"Pilih semua anggota\"]").indeterminate === false');
});

it('updates a user from the edit flyout and discards on escape', function () {
    $owner = browserMember(Role::Owner);
    $member = browserMember(Role::Member);

    actingAs($owner);

    $page = visit('/members');

    $page->click("[aria-label=\"Tindakan untuk {$member->name}\"]")
        ->wait(0.3)
        ->click('Edit anggota')
        ->wait(0.5)
        ->fill('dialog[open] input[name="name"]', 'Nama Baru')
        ->click('dialog[open] button[type="submit"]')
        ->wait(0.8)
        ->assertSee(__('Member updated.'))
        ->assertSee('Nama Baru')
        ->click('[aria-label="Tindakan untuk Nama Baru"]')
        ->wait(0.3)
        ->click('Edit anggota')
        ->wait(0.5)
        ->fill('dialog[open] input[name="name"]', 'Nama Dibatalkan')
        ->keys('dialog[open] input[name="name"]', 'Escape')
        ->wait(0.5)
        ->assertSee('Nama Baru')
        ->assertDontSee('Nama Dibatalkan');
});

it('navigates from a user row but not from its selection control', function () {
    $owner = browserMember(Role::Owner);
    $member = browserMember(Role::Member);

    actingAs($owner);

    $page = visit('/members');

    $page->click('[data-flux-row]:has(ui-checkbox[value="'.$member->person->id.'"]) [data-flux-cell]:nth-child(2)')
        ->assertPathIs("/members/{$member->person->uuid}");

    $page->navigate('/members')
        ->hover('[data-flux-row]:has(ui-checkbox[value="'.$member->person->id.'"])')
        ->wait(0.4)
        ->click('ui-checkbox[value="'.$member->person->id.'"]')
        ->wait(0.4)
        ->assertPathIs('/members')
        ->assertScript('document.querySelector("ui-checkbox[value=\"'.$member->person->id.'\"]").getAttribute("aria-checked") === "true"');
});

it('does not navigate when selecting text in a user row', function () {
    $owner = browserMember(Role::Owner);
    browserMember(Role::Member);

    actingAs($owner);

    $page = visit('/members');

    $page->script(<<<'JS'
        const cell = document.querySelector('[data-flux-row]:has(ui-checkbox) [data-flux-cell]:nth-child(2)');
        const range = document.createRange();
        range.selectNodeContents(cell);

        const selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(range);

        cell.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, view: window }))
        JS);
    $page->wait(0.4);

    $page->assertPathIs('/members');
});
