<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::view('/', 'ui.index')->name('ui.index');

require __DIR__.'/select.php';
require __DIR__.'/autocomplete.php';
