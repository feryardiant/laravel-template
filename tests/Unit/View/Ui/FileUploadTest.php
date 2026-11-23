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
        ->toContain('2.0 MB');
    expect(substr_count($html, 'data-invalid'))->toBe(1);
});

it('applies the class attribute to the control root', function () {
    /** @var TestCase $this */
    $view = $this->blade('<x-ui.file-upload label="Attachments" class="w-full sm:w-48" />');

    expect((string) $view)->toMatch('/data-ui-file-upload[^>]*sm:w-48/');
});

it('wires named, multiple and model-bound file inputs', function () {
    /** @var TestCase $this */
    $named = (string) $this->blade('<x-ui.file-upload name="documents" multiple accept=".pdf,.csv" label="Documents" disabled />');

    preg_match('/<input[^>]*type="file"[^>]*>/', $named, $input);

    expect($input[0])->toContain('name="documents[]"')
        ->toContain('multiple')
        ->toContain('accept=".pdf,.csv"')
        ->toContain('disabled')
        ->toContain('aria-label="Documents"');
    expect($named)->toContain('data-disabled');

    $this->withViewErrors([]);

    $bound = (string) $this->blade('<x-ui.file-upload wire:model="photos" name="ignored" />');

    preg_match('/<input[^>]*type="file"[^>]*>/', $bound, $input);

    expect($input[0])->toContain('wire:model="photos"')
        ->not->toContain('name=')
        ->toContain('aria-label="'.__('ui.file-upload.drop').'"');
});

it('marks the upload invalid from its error prop or model errors', function () {
    /** @var TestCase $this */
    $prop = (string) $this->blade('<x-ui.file-upload name="files" label="Files" error="The file is too large" />');

    expect($prop)->toContain('data-invalid')
        ->toContain('The file is too large');

    $this->withViewErrors(['photos' => 'The photos field is required.']);

    $model = (string) $this->blade('<x-ui.file-upload wire:model="photos" label="Photos" />');

    expect($model)->toContain('data-invalid');
});

it('renders field chrome around a custom dropzone slot', function () {
    /** @var TestCase $this */
    $view = $this->blade(<<<'BLADE'
        <x-ui.file-upload name="files" label="Attachments" description="PDF only" description-trailing="Checked on the server" badge="Required">
            <x-ui.file-upload.dropzone heading="Drop files" />
        </x-ui.file-upload>
    BLADE);

    $view->assertSee('Attachments')
        ->assertSee('PDF only')
        ->assertSee('Checked on the server')
        ->assertSee('Required')
        ->assertSeeInOrder(['data-ui-file-upload', 'data-ui-dropzone'], false);
});

it('renders the default, inline and relabeled dropzone headings', function () {
    /** @var TestCase $this */
    $default = (string) $this->blade('<x-ui.file-upload.dropzone />');
    $inline = (string) $this->blade('<x-ui.file-upload.dropzone inline />');
    $labeled = (string) $this->blade('<x-ui.file-upload.dropzone heading="Visible" aria-label="Upload avatar" />');

    expect($default)->toContain('data-ui-dropzone')
        ->toContain('role="button"')
        ->toContain(__('ui.file-upload.drop'))
        ->not->toContain(__('ui.file-upload.drop-inline'))
        ->and($inline)->toContain(__('ui.file-upload.drop-inline'))
        ->and($labeled)->toContain('aria-label="Upload avatar"')
        ->toContain('Visible');
});

it('gives each dropzone hint a unique id and hides it behind a slot', function () {
    /** @var TestCase $this */
    $html = (string) $this->blade(<<<'BLADE'
        <div>
            <x-ui.file-upload.dropzone text="First hint" />
            <x-ui.file-upload.dropzone text="Second hint" />
        </div>
    BLADE);

    preg_match_all('/aria-describedby="(ui-file-upload-hint-[^"]+)"/', $html, $matches);

    expect($matches[1])->toHaveCount(2)
        ->and(array_unique($matches[1]))->toHaveCount(2);

    foreach ($matches[1] as $id) {
        expect($html)->toContain('id="'.$id.'"');
    }

    $slotted = (string) $this->blade(<<<'BLADE'
        <x-ui.file-upload.dropzone heading="Default heading" text="Hidden hint">
            <span data-ui-custom-dropzone>Custom</span>
        </x-ui.file-upload.dropzone>
    BLADE);

    expect($slotted)->toContain('data-ui-custom-dropzone')
        ->toContain('Hidden hint')
        ->toContain('aria-describedby=')
        ->not->toContain('text-sm font-medium text-zinc-700');
});

it('renders bare and inline dropzone variants without extra progress', function () {
    /** @var TestCase $this */
    $bare = (string) $this->blade('<x-ui.file-upload.dropzone bare />');
    $inline = (string) $this->blade('<x-ui.file-upload.dropzone inline />');
    $default = (string) $this->blade('<x-ui.file-upload.dropzone />');

    expect($bare)->not->toContain('border-dashed')
        ->not->toContain('px-6')
        ->and($inline)->toContain('px-3 py-2')
        ->not->toContain('px-6')
        ->and($default)->toContain('border-dashed')
        ->toContain('px-6 py-8');

    $progress = (string) $this->blade(<<<'BLADE'
        <div>
            <x-ui.file-upload.dropzone with-progress />
            <x-ui.file-upload.dropzone />
        </div>
    BLADE);

    expect(substr_count($progress, 'x-show="loading"'))->toBe(1);
});

it('renders item metadata, images and the icon fallback', function () {
    /** @var TestCase $this */
    $html = (string) $this->blade(<<<'BLADE'
        <div>
            <x-ui.file-upload.item heading="Report.pdf" text="Uploaded just now" :size="1024" />
            <x-ui.file-upload.item heading="Photo.png" image="/storage/photo.png" />
        </div>
    BLADE);

    expect($html)->toContain('Report.pdf')
        ->toContain('Uploaded just now')
        ->toContain('1.0 KB')
        ->toContain('src="/storage/photo.png"')
        ->toContain('alt=""')
        ->not->toContain('data-invalid');
    expect(substr_count($html, 'data-flux-icon'))->toBe(1);
});

it('renders default-slot actions and marks clickable items', function () {
    /** @var TestCase $this */
    $withActions = (string) $this->blade(<<<'BLADE'
        <x-ui.file-upload.item heading="Report.pdf" wire:click="preview(0)">
            <button data-ui-custom-action>Remove</button>
        </x-ui.file-upload.item>
    BLADE);
    $withoutActions = (string) $this->blade('<x-ui.file-upload.item heading="Report.pdf" />');

    expect($withActions)->toContain('data-ui-custom-action')
        ->toContain('wire:click="preview(0)"')
        ->toContain('cursor-pointer')
        ->toContain('shrink-0 items-center gap-1')
        ->and($withoutActions)->not->toContain('shrink-0 items-center gap-1')
        ->not->toContain('cursor-pointer');
});

it('translates the named preview label', function () {
    expect(__('ui.file-upload.preview-named', ['name' => 'photo.png']))
        ->toContain('photo.png')
        ->not->toBe('ui.file-upload.preview-named');
});

it('renders a keyboard accessible preview action in the default slot', function () {
    /** @var TestCase $this */
    $view = (string) $this->blade(<<<'BLADE'
        <x-ui.file-upload.item heading="photo.png" wire:click="preview(0)">
            <flux:button data-ui-test="preview" aria-label="Preview photo.png">Preview</flux:button>
        </x-ui.file-upload.item>
    BLADE);

    expect($view)->toContain('data-ui-test="preview"')
        ->toContain('aria-label="Preview photo.png"')
        ->toContain('<button')
        ->toContain('wire:click="preview(0)"');
});

it('forwards dropzone attributes without duplicating the label', function () {
    /** @var TestCase $this */
    $view = (string) $this->blade('<x-ui.file-upload.dropzone class="max-w-sm" data-test="drop" aria-label="Attach" />');

    expect($view)->toContain('class="')
        ->toContain('max-w-sm')
        ->toContain('data-test="drop"')
        ->and(substr_count($view, 'aria-label='))->toBe(1);
});

it('renders a placeholder or preview avatar with the matching remove action', function () {
    /** @var TestCase $this */
    $placeholder = (string) $this->blade('<x-ui.file-upload.avatar />');

    expect($placeholder)->toContain('data-ui-dropzone')
        ->toContain('rounded-full')
        ->not->toContain('<img')
        ->not->toContain('wire:click="clearAvatar"');

    $preview = (string) $this->blade('<x-ui.file-upload.avatar url="/storage/avatar.png" alt="Jane Doe" shape="rounded" remove="clearAvatar" aria-label="Change avatar" />');

    expect($preview)->toContain('src="/storage/avatar.png"')
        ->toContain('alt="Jane Doe"')
        ->toContain('rounded-xl')
        ->toContain('wire:click="clearAvatar"')
        ->toContain('aria-label="'.__('ui.file-upload.remove').'"')
        ->toContain('aria-label="Change avatar"');
});
