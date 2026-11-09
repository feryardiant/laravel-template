<div class="space-y-8">
    <div class="space-y-2">
        <h1 data-ui-outside class="text-2xl font-semibold">File upload</h1>
        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            Dropzone, drag and drop, Livewire upload progress and removable file items.
        </p>
    </div>

    <section data-ui-demo="file-upload-photos" class="max-w-lg space-y-3">
        <x-ui.file-upload
            wire:model="photos"
            multiple
            accept="image/*"
            label="Upload photos"
            description="JPG, PNG or GIF up to 5 MB"
            description-trailing="Validation stays on the server"
        >
            <x-ui.file-upload.dropzone
                heading="Drop photos here or click to browse"
                text="JPG, PNG or GIF up to 5 MB"
                with-progress
            />
        </x-ui.file-upload>

        <div class="flex flex-col gap-2">
            @foreach ($photos as $index => $photo)
                <x-ui.file-upload.item
                    :heading="$photo->getClientOriginalName()"
                    :image="$photo->temporaryUrl()"
                    :size="$photo->getSize()"
                >
                    <x-slot:actions>
                        <x-ui.file-upload.item.remove
                            wire:click="removePhoto({{ $index }})"
                            :aria-label="__('ui.file-upload.remove-named', ['name' => $photo->getClientOriginalName()])"
                        />
                    </x-slot:actions>
                </x-ui.file-upload.item>
            @endforeach
        </div>

        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            Uploaded: <span data-ui-upload-count="photos">{{ count($photos) }}</span>
        </p>

        <flux:button size="sm" wire:click="$refresh" data-ui-test="refresh">Refresh component</flux:button>
    </section>

    <section data-ui-demo="file-upload-avatar" class="max-w-lg space-y-3">
        <x-ui.file-upload
            wire:model="avatar"
            accept="image/*"
            label="Avatar"
            description="A single square image"
        >
            <x-ui.file-upload.dropzone inline with-progress />
        </x-ui.file-upload>

        @if ($avatar)
            <x-ui.file-upload.item
                :heading="$avatar->getClientOriginalName()"
                :image="$avatar->temporaryUrl()"
                :size="$avatar->getSize()"
            >
                <x-slot:actions>
                    <x-ui.file-upload.item.remove
                        wire:click="clearAvatar"
                        :aria-label="__('ui.file-upload.remove-named', ['name' => $avatar->getClientOriginalName()])"
                    />
                </x-slot:actions>
            </x-ui.file-upload.item>
        @endif

        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            Uploaded: <span data-ui-upload-count="avatar">{{ $avatar ? 1 : 0 }}</span>
        </p>
    </section>

    <section data-ui-demo="file-upload-custom" class="max-w-lg space-y-3">
        <x-ui.file-upload
            wire:model="customAvatar"
            accept="image/*"
            label="Custom avatar"
            description="Click or drop an image"
        >
            <div class="relative w-fit">
                <x-ui.file-upload.dropzone
                    bare
                    heading="Upload avatar"
                    text="PNG or JPG up to 5 MB"
                    aria-label="Upload a custom avatar"
                >
                    <div
                        class="relative flex size-24 items-center justify-center rounded-full border border-zinc-200 bg-zinc-100 transition-colors group-data-dragging:border-accent group-data-dragging:bg-accent/5 hover:bg-zinc-200 dark:border-white/10 dark:bg-white/10 dark:hover:bg-white/15"
                    >
                        @if ($customAvatar)
                            <img src="{{ $customAvatar->temporaryUrl() }}" alt="" class="size-full rounded-full object-cover" />
                        @else
                            <flux:icon name="user" variant="solid" class="size-10 text-zinc-400" />
                        @endif
                    </div>
                </x-ui.file-upload.dropzone>

                @if ($customAvatar)
                    <x-ui.file-upload.item.remove
                        circle
                        wire:click="clearCustomAvatar"
                        :aria-label="__('ui.file-upload.remove-named', ['name' => $customAvatar->getClientOriginalName()])"
                        class="absolute end-0 bottom-0 z-10 border border-zinc-200 bg-white shadow-sm dark:border-white/10 dark:bg-zinc-800"
                    />
                @else
                    <span class="pointer-events-none absolute end-0 bottom-0 rounded-full bg-white p-0.5 dark:bg-zinc-800">
                        <flux:icon name="arrow-up-circle" variant="solid" class="size-6 text-zinc-500" />
                    </span>
                @endif
            </div>
        </x-ui.file-upload>

        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            Uploaded: <span data-ui-upload-count="custom-avatar">{{ $customAvatar ? 1 : 0 }}</span>
        </p>
    </section>

    <section data-ui-demo="file-upload-documents" class="max-w-lg space-y-3">
        <x-ui.file-upload
            wire:model="documents"
            multiple
            accept=".pdf,.txt,.csv"
            label="Upload documents"
            description="PDF, TXT or CSV up to 5 MB"
        >
            <x-ui.file-upload.dropzone icon="document-arrow-up" text="PDF, TXT or CSV up to 5 MB" with-progress />
        </x-ui.file-upload>

        <div class="flex flex-col gap-2">
            @foreach ($documents as $index => $document)
                <x-ui.file-upload.item
                    :heading="$document->getClientOriginalName()"
                    :size="$document->getSize()"
                    icon="document"
                >
                    <x-slot:actions>
                        <x-ui.file-upload.item.remove
                            wire:click="removeDocument({{ $index }})"
                            :aria-label="__('ui.file-upload.remove-named', ['name' => $document->getClientOriginalName()])"
                        />
                    </x-slot:actions>
                </x-ui.file-upload.item>
            @endforeach
        </div>

        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            Uploaded: <span data-ui-upload-count="documents">{{ count($documents) }}</span>
        </p>
    </section>

    <section data-ui-demo="file-upload-disabled" class="max-w-lg space-y-3">
        <x-ui.file-upload name="disabled_files" disabled>
            <x-ui.file-upload.dropzone heading="Uploads are disabled" />
        </x-ui.file-upload>
    </section>

    <section data-ui-demo="file-upload-invalid" class="max-w-lg space-y-3">
        <x-ui.file-upload name="invalid_files" label="Invalid upload" error="The file is too large">
            <x-ui.file-upload.dropzone heading="Upload a smaller file" />
        </x-ui.file-upload>
    </section>

    <section data-ui-demo="file-upload-plain" class="max-w-lg space-y-3">
        <form
            method="post"
            action="{{ route('ui.file-upload.plain') }}"
            enctype="multipart/form-data"
            class="space-y-3"
        >
            @csrf

            <x-ui.file-upload name="plain_files" multiple accept=".pdf" label="Plain files" description="Submitted as multipart form data">
                <x-ui.file-upload.dropzone inline />
            </x-ui.file-upload>
        </form>

        @if (session('plain_upload_count') !== null)
            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                Received:
                <span data-ui-plain-result="{{ session('plain_upload_count') }}">{{ session('plain_upload_count') }}</span>
            </p>
        @endif
    </section>

    <section data-ui-demo="file-upload-items" class="max-w-lg space-y-3">
        <x-ui.file-upload.item heading="photo.png" :size="1536" />
        <x-ui.file-upload.item heading="report.pdf" :size="2097152" />
        <x-ui.file-upload.item heading="broken.pdf" :size="1024" invalid />
    </section>

    <section data-ui-demo="file-upload-remove" class="max-w-lg space-y-3">
        <x-ui.file-upload.item heading="removable.pdf" :size="1024">
            <x-slot:actions>
                <x-ui.file-upload.item.remove
                    :aria-label="__('ui.file-upload.remove-named', ['name' => 'removable.pdf'])"
                />
            </x-slot:actions>
        </x-ui.file-upload.item>
    </section>
</div>
