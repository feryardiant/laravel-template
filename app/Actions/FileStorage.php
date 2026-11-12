<?php

declare(strict_types=1);

namespace App\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class FileStorage
{
    /**
     * Store an uploaded file and return its object path.
     */
    public function handle(UploadedFile $file, string $directory): string
    {
        $path = $file->store($directory);

        if ($path === false) {
            throw new RuntimeException('Unable to store the file.');
        }

        return $path;
    }

    /**
     * Retrieve a temporary pre-signed URL.
     */
    public function getTemporaryUrl(string $path, int $expires = 60): ?string
    {
        if (! Storage::disk()->exists($path)) {
            return null;
        }

        // The browser never sees this internal pre-signed URL.
        return Storage::disk()->temporaryUrl($path, now()->addMinutes($expires));
    }

    /**
     * Replace a previously stored file with a new upload.
     */
    public function replace(UploadedFile $file, string $directory, ?string $previousPath = null): string
    {
        $path = $this->handle($file, $directory);

        $this->delete($previousPath);

        return $path;
    }

    /**
     * Delete a previously stored file.
     */
    public function delete(?string $path): void
    {
        if ($path === null || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return;
        }

        Storage::disk()->delete($path);
    }
}
