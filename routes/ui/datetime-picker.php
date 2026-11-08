<?php

declare(strict_types=1);

use App\Livewire\Ui\DatetimePicker;
use Illuminate\Support\Facades\Route;

Route::livewire('datetime-picker', DatetimePicker::class)->name('ui.datetime-picker');
