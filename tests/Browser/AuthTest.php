<?php

declare(strict_types=1);

use App\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

describe('accessibilities', function () {
    it('has no serious accessibility issues on the login page', function (string $device) {
        visit('/login')
            ->on()
            ->{$device}()
            ->assertNoAccessibilityIssues()
            ->assertNoJavaScriptErrors();
    })->with(['desktop', 'mobile']);

    it('can sign in using only the keyboard', function () {
        $owner = browserMember(Role::Owner);

        $page = visit('/login');

        $page->assertScript('document.activeElement.name === "handle"')
            ->typeSlowly('handle', $owner->handle)
            ->keys('input[name="handle"]', 'Tab')
            ->assertScript('document.activeElement.name === "password"')
            ->typeSlowly('password', 'password')
            ->keys('input[name="password"]', 'Enter');

        $page->assertPathIs('/dashboard');
    });

    it('moves focus into a modal and back to its trigger', function () {
        $owner = browserMember(Role::Owner);

        actingAs($owner);

        $page = visit('/members');

        $page->click('Tambah anggota')
            ->wait(0.3)
            ->assertScript('!! document.activeElement.closest("dialog")')
            ->keys('dialog[open]', 'Escape')
            ->wait(0.3)
            ->assertScript('document.querySelectorAll("dialog[open]").length === 0')
            ->assertScript('document.activeElement.textContent.trim() === "Tambah anggota"');
    });
});

describe('usabilities', function () {
    it('keeps the login form usable on mobile', function () {
        $member = browserMember(Role::Member);

        $page = visit('/login')->on()->mobile();

        $page->assertScript('document.documentElement.scrollWidth <= window.innerWidth');

        $page->fill('handle', $member->handle)
            ->fill('password', 'password')
            ->click('@login-button')
            ->assertPathIs('/dashboard');
    });
});
