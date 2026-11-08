<?php

declare(strict_types=1);

use App\Livewire\Ui\Gallery;
use Illuminate\Support\Facades\Route;

Route::livewire('/', Gallery::class)->name('ui.index');

require __DIR__.'/select.php';
require __DIR__.'/autocomplete.php';
require __DIR__.'/calendar.php';
require __DIR__.'/date-picker.php';
require __DIR__.'/time-picker.php';
require __DIR__.'/datetime-picker.php';
require __DIR__.'/file-upload.php';
