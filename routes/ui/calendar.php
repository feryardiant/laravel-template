<?php

declare(strict_types=1);

use App\Livewire\Ui\Calendar;
use Illuminate\Support\Facades\Route;

Route::livewire('calendar', Calendar::class)->name('ui.calendar');
