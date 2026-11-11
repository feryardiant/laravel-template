<?php

declare(strict_types=1);

use App\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('exposes a level one heading on the dashboard', function () {
    $owner = browserMember(Role::Owner);

    browserLogin($owner)
        ->navigate('/dashboard')
        ->assertScript('(() => {
            const headings = [...document.querySelectorAll("h1")];

            return headings.length === 1 && headings[0].textContent.trim() === "Dasbor";
        })()');
});
