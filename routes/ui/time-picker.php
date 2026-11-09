<?php

declare(strict_types=1);

use App\Livewire\Ui\TimePicker;
use Illuminate\Support\Facades\Route;

Route::livewire('time-picker', TimePicker::class)->name('ui.time-picker');
