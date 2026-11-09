import type { WireLike } from '@/util/binding'

export interface FileUploadConfig {
    disabled: boolean
    /** Bound Livewire property, used to filter upload lifecycle events. */
    property: string | null
    labels: {
        uploading: string
    }
}

/** Livewire surface plus the component-event subscription used for uploads. */
type WireWithEvents = WireLike & {
    $on?: (event: string, callback: (detail: unknown) => void) => (() => void) | undefined
}

/**
 * Alpine data for the file-upload wrapper.
 *
 * The wrapper owns drag/drop, picker activation and upload feedback. The real
 * file input keeps Livewire's `wire:model`; upload events are read from the DOM
 * and, for completion, from `$wire` because a morph can detach the input before
 * the finish event bubbles.
 */
export interface FileUpload {
    disabled: boolean
    dragging: boolean
    loading: boolean
    progress: number
    announcement: string
    labels: FileUploadConfig['labels']
    /** Counter used so child dragenter/dragleave pairs do not flicker. */
    dragDepth: number
    init: (el?: HTMLElement) => void
    destroy: () => void
    openPicker: () => void
    onDragEnter: (event: DragEvent) => void
    onDragOver: (event: DragEvent) => void
    onDragLeave: () => void
    onDrop: (event: DragEvent) => void
    onUploadStart: () => void
    onUploadProgress: (event: Event) => void
    onUploadFinish: () => void
    onUploadCancel: () => void
    progressStyle: () => string
}

/**
 * Create the Alpine data for a file uploader.
 *
 * `wire` is used only to observe upload completion/error events; the actual
 * upload remains the responsibility of the hidden file input.
 */
export function fileUploadData(config: FileUploadConfig, wire: WireLike | null): FileUpload {
    let rootEl: HTMLElement | null = null
    const unsubscribes: (() => void)[] = []

    const input = (): HTMLInputElement | null => rootEl?.querySelector<HTMLInputElement>('input[type=file]') ?? null

    return {
        disabled: config.disabled,
        dragging: false,
        loading: false,
        progress: 0,
        announcement: '',
        labels: config.labels,
        dragDepth: 0,

        init(el) {
            rootEl = el ?? null

            const events = wire as WireWithEvents | null

            if (events === null || typeof events.$on !== 'function') {
                return
            }

            const finish = (detail: unknown): void => {
                const name = (detail as { name?: string } | undefined)?.name

                if (config.property === null || name === config.property) {
                    this.onUploadFinish()
                }
            }

            // The finish DOM event can be lost when Livewire morphs the input
            // before it bubbles, so also observe the component upload events.
            for (const event of ['upload:finished', 'upload:errored']) {
                const unsubscribe = events.$on(event, finish)

                if (typeof unsubscribe === 'function') {
                    unsubscribes.push(unsubscribe)
                }
            }
        },

        destroy() {
            for (const unsubscribe of unsubscribes) {
                unsubscribe()
            }

            unsubscribes.length = 0
        },

        openPicker() {
            if (this.disabled) {
                return
            }

            input()?.click()
        },

        onDragEnter(event) {
            if (this.disabled) {
                return
            }

            event.preventDefault()

            if (event.dataTransfer !== null) {
                event.dataTransfer.dropEffect = 'copy'
            }

            this.dragDepth += 1
            this.dragging = true
        },

        onDragOver(event) {
            if (this.disabled) {
                return
            }

            event.preventDefault()

            if (event.dataTransfer !== null) {
                event.dataTransfer.dropEffect = 'copy'
            }
        },

        onDragLeave() {
            if (this.disabled) {
                return
            }

            this.dragDepth = Math.max(0, this.dragDepth - 1)

            if (this.dragDepth === 0) {
                this.dragging = false
            }
        },

        onDrop(event) {
            if (this.disabled) {
                return
            }

            event.preventDefault()
            this.dragging = false
            this.dragDepth = 0

            const target = input()
            const files = event.dataTransfer?.files

            if (target === null || files === undefined || files.length === 0) {
                return
            }

            // Hand the files to the real input so Livewire's own change
            // listener performs the upload.
            target.files = files
            target.dispatchEvent(new Event('change', { bubbles: true }))
        },

        onUploadStart() {
            this.loading = true
            this.progress = 0
            this.announcement = this.labels.uploading
        },

        onUploadProgress(event) {
            const detail = (event as CustomEvent<{ progress?: number }>).detail
            const progress = Number(detail?.progress ?? 0)

            this.progress = Math.max(0, Math.min(100, progress))
            this.announcement = `${this.labels.uploading} ${Math.round(this.progress)}%`
        },

        onUploadFinish() {
            this.loading = false
            this.progress = 100
            this.announcement = ''
        },

        onUploadCancel() {
            this.loading = false
            this.announcement = ''
        },

        progressStyle() {
            return `--flux-file-upload-progress: ${this.progress}%; --flux-file-upload-progress-as-string: '${this.progress}%'`
        },
    }
}
