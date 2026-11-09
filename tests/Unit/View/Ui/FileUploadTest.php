<?php

declare(strict_types=1);

use Tests\TestCase;

it('formats item sizes and marks invalid items', function () {
    /** @var TestCase $this */
    $view = $this->blade(<<<'BLADE'
        <div>
            <x-ui.file-upload.item heading="Report.pdf" :size="1536" />
            <x-ui.file-upload.item heading="Video.mp4" :size="2097152" />
            <x-ui.file-upload.item heading="Broken" invalid />
        </div>
    BLADE);

    $html = (string) $view;

    expect($html)->toContain('1.5 KB')
        ->toContain('2.0 MB')
        ->and(substr_count($html, 'data-invalid'))->toBe(1);
});
