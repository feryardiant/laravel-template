<?php

declare(strict_types=1);

use App\Livewire\Ui\FileUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::livewire('file-upload', FileUpload::class)->name('ui.file-upload');

Route::post('file-upload/plain', function (Request $request) {
    return redirect()
        ->route('ui.file-upload')
        ->with('plain_upload_count', \count($request->file('plain_files') ?? []));
})->name('ui.file-upload.plain');
