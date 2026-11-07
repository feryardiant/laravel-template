import { readModel, stringValue, watchModel, writeModel } from './binding'
import { applyFilter, assignOptionIds, scanOptions } from './listbox'
import { createPopover, type PopoverController } from './popover'
import type { AutocompleteConfig, ListboxOption, RemoteItem, WireLike } from './types'

export interface AutocompleteData {
    query: string
    open: boolean
    activeIndex: number
    activeId: string
    loading: boolean
    emptyVisible: boolean
    results: RemoteItem[]
    disabled: boolean
    readonly: boolean
    emptyText: string
    loadingText: string
    init: () => void
    destroy: () => void
    openPopover: () => void
    closePopover: () => void
    refresh: () => void
    onInput: (event: Event) => void
    onFocus: () => void
    onKeydown: (event: KeyboardEvent) => void
    onBlur: () => void
    selectFromEvent: (event: MouseEvent) => void
    selectRemote: (item: RemoteItem) => void
    selectOption: (option: ListboxOption) => void
    clear: () => void
    setActive: (index: number) => void
    move: (delta: number) => void
    scanLocal: () => void
    applyLocalFilter: () => void
    syncRemoteOptions: () => void
    scheduleFetch: () => void
    fetchRemote: () => Promise<void>
    focusTrigger: () => void
    write: () => void
}

function normalizeRemote(data: unknown): RemoteItem[] {
    if (!Array.isArray(data)) {
        return []
    }

    return data.flatMap((item) => {
        if (typeof item === 'string') {
            return [{ label: item }]
        }

        if (typeof item === 'object' && item !== null && 'label' in item) {
            const label = (item as { label: unknown }).label

            if (typeof label === 'string') {
                return [{ label }]
            }
        }

        return []
    })
}

export function autocompleteData(config: AutocompleteConfig, wire: WireLike | null): AutocompleteData {
    let options: ListboxOption[] = []
    let visible: ListboxOption[] = []
    let popover: PopoverController | null = null
    let unwatch: (() => void) | null = null
    let debounceTimer: ReturnType<typeof setTimeout> | null = null
    let abortController: AbortController | null = null
    let requestId = 0
    let suppressFocus = false

    const trigger = (): HTMLInputElement | null => document.getElementById(config.triggerId) as HTMLInputElement | null
    const listbox = (): HTMLElement | null => document.getElementById(config.listboxId)
    const formInput = (): HTMLInputElement | null =>
        config.formInputId === null ? null : (document.getElementById(config.formInputId) as HTMLInputElement | null)

    return {
        query: '',
        open: false,
        activeIndex: -1,
        activeId: '',
        loading: false,
        emptyVisible: false,
        results: [],
        disabled: config.disabled,
        readonly: config.readonly,
        emptyText: config.empty,
        loadingText: config.loading,

        init() {
            const triggerElement = trigger()
            const popoverElement = document.getElementById(config.popoverId)

            if (triggerElement === null || popoverElement === null) {
                return
            }

            const bound = readModel(wire, config.model, config.value)
            this.query =
                typeof bound === 'string' ? bound : bound === null || bound === undefined ? '' : stringValue(bound)

            popover = createPopover(triggerElement, popoverElement, {
                focusTarget: triggerElement,
                onOpen: () => {
                    this.open = true
                },
                onClose: () => {
                    this.open = false
                    this.activeIndex = -1
                    this.activeId = ''
                },
            })

            unwatch = watchModel(wire, config.model, (value) => {
                this.query =
                    typeof value === 'string' ? value : value === null || value === undefined ? '' : stringValue(value)

                if (this.open) {
                    this.refresh()
                }
            })
        },

        destroy() {
            abortController?.abort()
            unwatch?.()
            popover?.destroy()

            if (debounceTimer !== null) {
                clearTimeout(debounceTimer)
            }
        },

        openPopover() {
            if (this.disabled) {
                return
            }

            popover?.open()
            this.refresh()
        },

        refresh() {
            if (config.searchUrl !== null) {
                if (this.query.length >= config.minLength) {
                    this.scheduleFetch()
                } else {
                    this.results = []
                    this.emptyVisible = false
                }

                return
            }

            this.scanLocal()
            this.applyLocalFilter()
        },

        closePopover() {
            popover?.close()
        },

        onInput(event) {
            if (this.disabled || this.readonly) {
                return
            }

            this.query = (event.target as HTMLInputElement).value
            this.write()

            if (!this.open) {
                popover?.open()
            }

            this.refresh()
        },

        onFocus() {
            if (suppressFocus) {
                return
            }

            if (!this.open) {
                this.openPopover()
            }
        },

        onBlur() {
            // Outside pointerdown and Tab handling close the popover; keep focus
            // stable while the user clicks an option.
        },

        onKeydown(event) {
            if (this.disabled) {
                return
            }

            if (event.key === 'Escape') {
                if (this.open) {
                    event.preventDefault()
                    this.closePopover()
                }

                return
            }

            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault()

                if (!this.open) {
                    this.openPopover()
                } else {
                    this.move(event.key === 'ArrowDown' ? 1 : -1)
                }

                return
            }

            if (event.key === 'Home' || event.key === 'End') {
                if (this.open && visible.length > 0) {
                    event.preventDefault()
                    this.setActive(event.key === 'Home' ? 0 : visible.length - 1)
                }

                return
            }

            if (event.key === 'Enter') {
                const active = visible[this.activeIndex]

                if (this.open && active !== undefined) {
                    event.preventDefault()
                    this.selectOption(active)
                }

                return
            }

            if (event.key === 'Tab') {
                this.closePopover()
            }
        },

        selectFromEvent(event) {
            const target = event.target

            if (!(target instanceof Element)) {
                return
            }

            const element = target.closest<HTMLElement>('[data-ui-option]')

            if (element === null) {
                return
            }

            const option = options.find((candidate) => candidate.el === element)

            if (option !== undefined && !option.disabled) {
                this.selectOption(option)
            }
        },

        selectRemote(item) {
            this.query = item.label
            this.write()
            this.closePopover()
            this.focusTrigger()
        },

        selectOption(option) {
            if (option.disabled) {
                return
            }

            this.query = option.selectedLabel
            this.write()
            this.closePopover()
            this.focusTrigger()
        },

        clear() {
            this.query = ''
            this.results = []
            this.emptyVisible = false
            this.write()
            this.closePopover()
            this.focusTrigger()
        },

        setActive(index) {
            this.activeIndex = index

            for (const [optionIndex, option] of visible.entries()) {
                option.el.toggleAttribute('data-active', optionIndex === index)
            }

            const active = visible[index] ?? null

            this.activeId = active?.el.id ?? ''

            if (this.open) {
                active?.el.scrollIntoView({ block: 'nearest' })
            }
        },

        move(delta) {
            if (visible.length === 0) {
                return
            }

            const current = this.activeIndex < 0 ? (delta > 0 ? -1 : 0) : this.activeIndex
            const next = (current + delta + visible.length) % visible.length

            this.setActive(next)
        },

        scanLocal() {
            options = scanOptions(listbox())
            assignOptionIds(options, config.listboxId)
        },

        applyLocalFilter() {
            visible = applyFilter(listbox(), options, this.query)
            this.emptyVisible = visible.length === 0
            this.setActive(visible.length > 0 ? 0 : -1)
        },

        syncRemoteOptions() {
            options = scanOptions(listbox())
            assignOptionIds(options, config.listboxId)
            visible = options
            this.emptyVisible = visible.length === 0
            this.setActive(visible.length > 0 ? 0 : -1)
        },

        scheduleFetch() {
            if (debounceTimer !== null) {
                clearTimeout(debounceTimer)
            }

            if (this.query.length < config.minLength) {
                abortController?.abort()
                this.loading = false
                this.results = []
                this.emptyVisible = false

                return
            }

            debounceTimer = setTimeout(() => {
                void this.fetchRemote()
            }, config.debounce)
        },

        async fetchRemote() {
            if (config.searchUrl === null) {
                return
            }

            abortController?.abort()

            const controller = new AbortController()
            abortController = controller
            const id = ++requestId

            this.loading = true

            const url = new URL(config.searchUrl, window.location.origin)
            url.searchParams.set('q', this.query)

            try {
                const response = await fetch(url.toString(), {
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                })

                if (!response.ok) {
                    throw new Error('Autocomplete request failed')
                }

                const data: unknown = await response.json()

                if (id !== requestId) {
                    return
                }

                this.results = normalizeRemote(data)
                this.loading = false

                setTimeout(() => {
                    if (id === requestId) {
                        this.syncRemoteOptions()
                    }
                }, 0)
            } catch {
                if (controller.signal.aborted || id !== requestId) {
                    return
                }

                this.loading = false
                this.results = []
                this.emptyVisible = true
            }
        },

        focusTrigger() {
            suppressFocus = true
            trigger()?.focus()

            setTimeout(() => {
                suppressFocus = false
            }, 0)
        },

        write() {
            writeModel(wire, config.model, config.live, this.query, formInput())
        },
    }
}
