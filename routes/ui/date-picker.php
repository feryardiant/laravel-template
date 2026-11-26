<?php

declare(strict_types=1);

use App\Livewire\View\Ui\DatePicker;
use Illuminate\Support\Facades\Route;

Route::livewire('date-picker', DatePicker::class)->name('ui.date-picker');
