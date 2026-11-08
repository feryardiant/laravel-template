<?php

declare(strict_types=1);

use App\Livewire\Ui\FileUpload;
use Illuminate\Support\Facades\Route;

Route::livewire('file-upload', FileUpload::class)->name('ui.file-upload');
