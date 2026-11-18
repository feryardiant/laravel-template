<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StoredFileController extends Controller
{
    /**
     * Stream a stored file through the application.
     */
    public function __invoke(string $path): StreamedResponse
    {
        abort_unless(preg_match('#^(attachments|avatars|photos|logos)/[A-Za-z0-9._-]+$#', $path) === 1, 404);

        $disk = Storage::disk();

        abort_unless($disk->exists($path), 404);

        return Response::streamDownload(function () use ($disk, $path): void {
            $stream = $disk->readStream($path);

            if ($stream !== null) {
                fpassthru($stream);
                fclose($stream);
            }
        }, basename($path), [
            'Content-Type' => $disk->mimeType($path) ?: 'application/octet-stream',
            'Cache-Control' => 'private, max-age=86400',
        ], 'inline');
    }
}
