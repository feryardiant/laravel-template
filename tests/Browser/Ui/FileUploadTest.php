<?php

declare(strict_types=1);

it('has no serious accessibility issues on the file upload gallery', function (string $device) {
    visit('/ui/file-upload')
        ->on()
        ->{$device}()
        ->assertNoAccessibilityIssues()
        ->assertNoJavaScriptErrors()
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth');
})->with(['desktop', 'mobile']);

it('wires the hidden input, dropzone and plain-form name', function () {
    visit('/ui/file-upload')
        ->on()
        ->desktop()
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=file-upload-photos]');
            const input = root.querySelector('input[type=file]');
            const dropzone = root.querySelector('[data-ui-dropzone]');
            const plain = document.querySelector('[data-ui-demo=file-upload-plain] input[type=file]');

            return input.type === 'file' &&
                input.multiple &&
                input.accept === 'image/*' &&
                input.getAttribute('tabindex') === '-1' &&
                input.classList.contains('sr-only') &&
                getComputedStyle(input).display !== 'none' &&
                dropzone.getAttribute('role') === 'button' &&
                dropzone.getAttribute('tabindex') === '0' &&
                dropzone.getAttribute('aria-label') === 'Drop photos here or click to browse' &&
                dropzone.getAttribute('aria-describedby') !== null &&
                plain.name === 'plain_files[]' &&
                plain.multiple &&
                plain.accept === '.pdf' &&
                plain.closest('form').method.toLowerCase() === 'post' &&
                plain.closest('form').enctype === 'multipart/form-data';
        })()");
});

it('opens the file picker from click, Enter and Space', function () {
    visit('/ui/file-upload')
        ->on()
        ->desktop()
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=file-upload-avatar]');
            const input = root.querySelector('input[type=file]');
            const dropzone = root.querySelector('[data-ui-dropzone]');

            window.__pickerClicks = 0;
            input.click = () => { window.__pickerClicks++ };

            dropzone.click();
            dropzone.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true, cancelable: true }));
            dropzone.dispatchEvent(new KeyboardEvent('keydown', { key: ' ', bubbles: true, cancelable: true }));

            return window.__pickerClicks === 3;
        })()");
});

it('assigns a dropped file to the file input', function () {
    visit('/ui/file-upload')
        ->on()
        ->desktop()
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=file-upload-plain]');
            const input = root.querySelector('input[type=file]');
            const dropzone = root.querySelector('[data-ui-dropzone]');
            const transfer = new DataTransfer();
            transfer.items.add(new File(['hello world'], 'hello.txt', { type: 'text/plain' }));

            dropzone.dispatchEvent(new DragEvent('drop', { bubbles: true, cancelable: true, dataTransfer: transfer }));

            return input.files.length === 1 &&
                input.files[0].name === 'hello.txt' &&
                input.files[0].size === 11 &&
                input.files[0].type === 'text/plain';
        })()");
});

it('supports a custom uploader through the dropzone slot', function () {
    visit('/ui/file-upload')
        ->on()
        ->desktop()
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=file-upload-custom]');
            const dropzone = root.querySelector('[data-ui-dropzone]');
            const input = root.querySelector('input[type=file]');
            const custom = dropzone.querySelector('.size-24');

            window.__customClicks = 0;
            input.click = () => { window.__customClicks++ };

            custom.click();
            dropzone.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true, cancelable: true }));
            dropzone.dispatchEvent(new DragEvent('dragenter', { bubbles: true, cancelable: true, dataTransfer: new DataTransfer() }));

            const describedBy = dropzone.getAttribute('aria-describedby');
            const description = describedBy === null ? null : document.getElementById(describedBy);

            return window.__customClicks === 2 &&
                custom.querySelector('svg') !== null &&
                dropzone.getAttribute('aria-label') === 'Upload a custom avatar' &&
                description !== null &&
                description.textContent.trim() === 'PNG or JPG up to 5 MB';
        })()")
        ->wait(0.2)
        ->assertScript("document.querySelector('[data-ui-demo=file-upload-custom] [data-ui-dropzone]').getAttribute('data-dragging') === 'true'")
        ->assertScript("(() => {
            const dropzone = document.querySelector('[data-ui-demo=file-upload-custom] [data-ui-dropzone]');
            dropzone.dispatchEvent(new DragEvent('dragleave', { bubbles: true, cancelable: true, dataTransfer: new DataTransfer() }));

            return true;
        })()")
        ->wait(0.2)
        ->assertScript("document.querySelector('[data-ui-demo=file-upload-custom] [data-ui-dropzone]').getAttribute('data-dragging') === null");
});

it('tracks drag state and ignores drag on disabled uploads', function () {
    visit('/ui/file-upload')
        ->on()
        ->desktop()
        ->assertScript("(() => {
            const dropzone = document.querySelector('[data-ui-demo=file-upload-photos] [data-ui-dropzone]');
            dropzone.dispatchEvent(new DragEvent('dragenter', { bubbles: true, cancelable: true, dataTransfer: new DataTransfer() }));

            return true;
        })()")
        ->wait(0.2)
        ->assertScript("document.querySelector('[data-ui-demo=file-upload-photos] [data-ui-dropzone]').getAttribute('data-dragging') === 'true'")
        ->assertScript("(() => {
            const dropzone = document.querySelector('[data-ui-demo=file-upload-photos] [data-ui-dropzone]');
            dropzone.dispatchEvent(new DragEvent('dragleave', { bubbles: true, cancelable: true, dataTransfer: new DataTransfer() }));

            return true;
        })()")
        ->wait(0.2)
        ->assertScript("document.querySelector('[data-ui-demo=file-upload-photos] [data-ui-dropzone]').getAttribute('data-dragging') === null")
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=file-upload-disabled]');
            const dropzone = root.querySelector('[data-ui-dropzone]');

            dropzone.dispatchEvent(new DragEvent('dragenter', { bubbles: true, cancelable: true, dataTransfer: new DataTransfer() }));

            return true;
        })()")
        ->wait(0.2)
        ->assertScript("document.querySelector('[data-ui-demo=file-upload-disabled] [data-ui-dropzone]').getAttribute('data-dragging') === null");
});

it('drives the progress bar and loading state from upload events', function () {
    $page = visit('/ui/file-upload')->on()->desktop();

    $page->assertScript("(() => {
        const root = document.querySelector('[data-ui-demo=file-upload-photos]');
        const input = root.querySelector('input[type=file]');
        input.dispatchEvent(new CustomEvent('livewire-upload-start', { bubbles: true }));

        return true;
    })()")
        ->wait(0.15)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=file-upload-photos]');
            const dropzone = root.querySelector('[data-ui-dropzone]');

            return dropzone.getAttribute('data-loading') === 'true' &&
                root.querySelector('[data-ui-file-upload]').getAttribute('style').includes('0%');
        })()")
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=file-upload-photos]');
            const input = root.querySelector('input[type=file]');
            input.dispatchEvent(new CustomEvent('livewire-upload-progress', { bubbles: true, detail: { progress: 42 } }));

            return true;
        })()")
        ->wait(0.15)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=file-upload-photos]');

            return root.querySelector('[data-ui-file-upload]').getAttribute('style').includes('42%') &&
                root.querySelector('[data-ui-dropzone] .bg-accent').getAttribute('style') === 'width: 42%';
        })()")
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=file-upload-photos]');
            root.querySelector('input[type=file]').dispatchEvent(new CustomEvent('livewire-upload-finish', { bubbles: true }));

            return true;
        })()")
        ->wait(0.15)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=file-upload-photos]');

            return root.querySelector('[data-ui-dropzone]').getAttribute('data-loading') === null &&
                root.querySelector('[data-ui-file-upload]').getAttribute('style').includes('100%');
        })()");
});

it('completes uploads only for the bound property', function () {
    $page = visit('/ui/file-upload')->on()->desktop();

    // The synthetic events below are also picked up by Livewire's own upload
    // manager, which expects a real in-flight upload and raises its own errors.
    $page->assertScript("(() => {
        const root = document.querySelector('[data-ui-demo=file-upload-photos]');
        root.querySelector('input[type=file]').dispatchEvent(new CustomEvent('livewire-upload-start', { bubbles: true }));
        root.dispatchEvent(new CustomEvent('upload:finished', { bubbles: true, detail: { name: 'avatar', tmpFilenames: ['stale'] } }));

        return true;
    })()")
        ->wait(0.15)
        ->assertScript("document.querySelector('[data-ui-demo=file-upload-photos] [data-ui-dropzone]').getAttribute('data-loading') === 'true'")
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=file-upload-photos]');
            root.dispatchEvent(new CustomEvent('upload:errored', { bubbles: true, detail: { name: 'photos' } }));

            return true;
        })()")
        ->wait(0.15)
        ->assertScript("document.querySelector('[data-ui-demo=file-upload-photos] [data-ui-dropzone]').getAttribute('data-loading') === null")
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=file-upload-photos]');
            root.querySelector('input[type=file]').dispatchEvent(new CustomEvent('livewire-upload-start', { bubbles: true }));
            root.dispatchEvent(new CustomEvent('upload:finished', { bubbles: true, detail: { name: 'photos', tmpFilenames: ['fresh'] } }));

            return true;
        })()")
        ->wait(0.15)
        ->assertScript("document.querySelector('[data-ui-demo=file-upload-photos] [data-ui-dropzone]').getAttribute('data-loading') === null");
});

it('clears loading when an upload is canceled', function () {
    $page = visit('/ui/file-upload')->on()->desktop();

    $page->assertScript("(() => {
        const input = document.querySelector('[data-ui-demo=file-upload-photos] input[type=file]');
        input.dispatchEvent(new CustomEvent('livewire-upload-start', { bubbles: true }));

        return true;
    })()")
        ->wait(0.1)
        ->assertScript("document.querySelector('[data-ui-demo=file-upload-photos] [data-ui-dropzone]').getAttribute('data-loading') === 'true'")
        ->assertScript("(() => {
            const input = document.querySelector('[data-ui-demo=file-upload-photos] input[type=file]');
            input.dispatchEvent(new CustomEvent('livewire-upload-cancel', { bubbles: true }));

            return true;
        })()")
        ->wait(0.1)
        ->assertScript("document.querySelector('[data-ui-demo=file-upload-photos] [data-ui-dropzone]').getAttribute('data-loading') === null");
});

it('keeps the file input morph-stable during an upload', function () {
    $page = visit('/ui/file-upload')->on()->desktop();

    $page->assertScript("(() => {
        const input = document.querySelector('[data-ui-demo=file-upload-photos] input[type=file]');
        input.dispatchEvent(new CustomEvent('livewire-upload-start', { bubbles: true }));

        return true;
    })()")
        ->wait(0.1)
        ->click('[data-ui-test=refresh]')
        ->wait(0.7)
        ->assertScript("(() => {
            const input = document.querySelector('[data-ui-demo=file-upload-photos] input[type=file]');

            return input !== null && ! input.hasAttribute('id');
        })()")
        ->assertScript("(() => {
            const input = document.querySelector('[data-ui-demo=file-upload-photos] input[type=file]');
            input.dispatchEvent(new CustomEvent('livewire-upload-progress', { bubbles: true, detail: { progress: 42 } }));

            return true;
        })()")
        ->wait(0.15)
        ->assertScript("document.querySelector('[data-ui-demo=file-upload-photos] [data-ui-file-upload]').getAttribute('style').includes('42%')");
});

it('does not open a disabled upload', function () {
    visit('/ui/file-upload')
        ->on()
        ->desktop()
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=file-upload-disabled]');
            const input = root.querySelector('input[type=file]');
            const dropzone = root.querySelector('[data-ui-dropzone]');

            window.__disabledClicks = 0;
            input.click = () => { window.__disabledClicks++ };
            dropzone.click();
            dropzone.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true, cancelable: true }));

            return window.__disabledClicks === 0 &&
                input.disabled &&
                dropzone.getAttribute('tabindex') === '-1' &&
                dropzone.getAttribute('aria-disabled') === 'true';
        })()");
});

it('renders the invalid upload error', function () {
    visit('/ui/file-upload')
        ->on()
        ->desktop()
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=file-upload-invalid]');
            const uploader = root.querySelector('[data-ui-file-upload]');
            const error = root.querySelector('[data-flux-error]');

            return uploader.hasAttribute('data-invalid') &&
                error !== null &&
                getComputedStyle(error).display !== 'none' &&
                error.textContent.includes('The file is too large');
        })()");
});

it('keeps the dropzone and remove targets touch-friendly on mobile', function () {
    visit('/ui/file-upload')
        ->on()
        ->mobile()
        ->assertScript("document.querySelector('[data-ui-demo=file-upload-photos] [data-ui-dropzone]').getBoundingClientRect().height >= 44")
        ->assertScript("document.querySelector('[data-ui-demo=file-upload-avatar] [data-ui-dropzone]').getBoundingClientRect().height >= 44")
        ->assertScript("document.querySelector('[data-ui-demo=file-upload-custom] [data-ui-dropzone]').getBoundingClientRect().height >= 44")
        ->assertScript("(() => {
            const remove = document.querySelector('[data-ui-demo=file-upload-remove] [data-ui-remove]');

            return remove !== null && remove.getBoundingClientRect().height >= 44;
        })()");
});
