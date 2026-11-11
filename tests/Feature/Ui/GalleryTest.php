<?php

declare(strict_types=1);

use App\Livewire\Ui\Gallery;
use Livewire\Livewire;

it('links to every component page', function () {
    Livewire::test(Gallery::class)
        ->assertSeeHtml('href="'.route('ui.select').'"')
        ->assertSeeHtml('href="'.route('ui.autocomplete').'"')
        ->assertSeeHtml('href="'.route('ui.calendar').'"')
        ->assertSeeHtml('href="'.route('ui.date-picker').'"')
        ->assertSeeHtml('href="'.route('ui.time-picker').'"')
        ->assertSeeHtml('href="'.route('ui.datetime-picker').'"')
        ->assertSeeHtml('href="'.route('ui.file-upload').'"')
        ->assertSeeHtml('href="'.route('ui.timeline').'"');
});
