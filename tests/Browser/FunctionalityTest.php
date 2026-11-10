<?php

declare(strict_types=1);

use App\Enums\Locale;
use App\Enums\Role;
use App\Notifications\UserRoleChangedNotification;

it('redirects guests away from protected pages', function () {
    visit('/members')->assertPathIs('/login');
});

it('lets a verified user sign in and out', function () {
    $owner = browserMember(Role::Owner);

    $page = browserLogin($owner)
        ->assertPathIs('/dashboard')
        ->assertSee('Anggota');

    $page->click('@sidebar-menu-button')
        ->click('[data-flux-sidebar] [data-test="logout-button"]');

    $page->assertPathIs('/login');
});

it('hides management actions on your own user detail page', function () {
    $admin = browserMember(Role::Admin);
    $member = browserMember(Role::Member);

    $page = browserLogin($admin)->navigate("/members/{$admin->person->uuid}");

    $page->assertDontSee('Edit anggota')
        ->assertDontSee('Jadikan Anggota')
        ->navigate("/members/{$member->person->uuid}")
        ->assertSee('Edit anggota');
});

it('lets an owner add a member', function () {
    $owner = browserMember(Role::Owner);

    $page = browserLogin($owner)->navigate('/members');

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

    browserLogin($member, 'mobile')
        ->navigate('/members')
        ->assertSee('Anggota')
        ->assertDontSee('Tambah anggota')
        ->assertMissing('[aria-label^="Tindakan untuk"]');
});

it('rejects invalid credentials without signing in', function () {
    $member = browserMember(Role::Member);

    visit('/login')
        ->fill('email', $member->email)
        ->fill('password', 'wrong-password')
        ->click('@login-button')
        ->assertPathIs('/login')
        ->assertSee('Kredensial ini tidak cocok dengan catatan kami.');
});

it('gates unverified users from verified pages', function () {
    $unverified = browserMember(Role::Member, verified: false);

    browserLogin($unverified)
        ->assertPathIs('/email/verify')
        ->assertSee('verifikasi alamat email');
});

it('prevents admins from managing owners', function () {
    $admin = browserMember(Role::Admin);
    $owner = browserMember(Role::Owner);
    $member = browserMember(Role::Member);

    browserLogin($admin)
        ->navigate('/members')
        ->assertMissing("[aria-label=\"Tindakan untuk {$owner->name}\"]")
        ->assertPresent("[aria-label=\"Tindakan untuk {$member->name}\"]")
        ->assertMissing("[aria-label=\"Tindakan untuk {$admin->name}\"]");
});

it('deletes selected users in one batch', function () {
    $owner = browserMember(Role::Owner);
    $first = browserMember(Role::Member);
    $second = browserMember(Role::Member);

    $page = browserLogin($owner, 'mobile')->navigate('/members');

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

    $page = browserLogin($owner, 'mobile')->navigate('/members');

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

    $page = browserLogin($owner)->navigate('/members');

    $page->click("[aria-label=\"Tindakan untuk {$member->name}\"]")
        ->wait(0.3)
        ->click('Edit anggota')
        ->wait(0.5)
        ->fill('dialog[open] input[name="name"]', 'Nama Baru')
        ->click('dialog[open] button[type="submit"]')
        ->wait(0.8)
        ->assertSee('Anggota diperbarui.')
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

    $page = browserLogin($owner)->navigate('/members');

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

    $page = browserLogin($owner)->navigate('/members');

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
        ->assertSee('Belum ada notifikasi.');
});

it('opens a notification, marks it read, and updates the menu count', function () {
    $owner = browserMember(Role::Member);
    $owner->notify(new UserRoleChangedNotification(browserMember(Role::Member), Role::Admin));

    $page = browserLogin($owner)->navigate('/notifications');

    $page->assertScript('[...document.querySelectorAll("[role=status]")].some((el) => el.textContent.includes("1"))')
        ->click('[data-flux-row] button[wire\:click\.stop]:not([role="checkbox"])')
        ->wait(0.6)
        ->assertScript('(() => {
            const dialog = document.querySelector("dialog[open]");

            return !!dialog && dialog.textContent.includes("Peran Anda di");
        })()')
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

it('lets a user update their own profile', function () {
    $member = browserMember(Role::Member);

    $page = browserLogin($member)->navigate('/settings/profile');

    $page->fill('name', 'Nama Baru')
        ->click('Simpan')
        ->assertSee('Profil diperbarui.');

    $page->navigate('/settings/profile')
        ->assertValue('name', 'Nama Baru');
});

it('updates locale and offers to reload the page', function () {
    $member = browserMember(Role::Member);

    $page = browserLogin($member)->navigate('/settings/preferences');

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

    $page = browserLogin($member)->navigate('/settings/preferences');

    $page->click('Bahasa Inggris')
        ->click('Bahasa Indonesia')
        ->wait(1.5);

    expect($member->refresh()->locale)->toBe(Locale::Indonesian);
});

it('applies the preferred locale to livewire updates', function () {
    $member = browserMember(Role::Member, ['locale' => Locale::English]);

    $page = browserLogin($member)->navigate('/settings/profile');

    $page->fill('name', 'English Name')
        ->click('Save')
        ->assertSee('Profile updated.');
});

it('shows a danger toast when preferences cannot be saved', function () {
    $member = browserMember(Role::Member, ['locale' => Locale::English]);

    $page = browserLogin($member)->navigate('/settings/preferences');

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

    $page = browserLogin($member)->navigate('/settings/preferences');

    $page->select('[x-model=timezone]', 'Asia/Jakarta')
        ->assertSee('Preferensi Anda telah diperbarui.')
        ->click('Muat ulang halaman')
        ->wait(0.8)
        ->assertScript('document.querySelector("[x-model=timezone]").value === "Asia/Jakarta"');

    expect($member->refresh()->timezone)->toBe('Asia/Jakarta');
});
