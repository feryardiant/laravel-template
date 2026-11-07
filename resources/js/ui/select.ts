import { normalizeValues, readModel, watchModel, writeModel } from './binding'
import { applyFilter, assignOptionIds, scanOptions } from './listbox'
import { createPopover, type PopoverController } from './popover'
import type { ListboxOption, SelectConfig, SelectedOption, WireLike } from './types'

export interface SelectData {
    variant: 'listbox' | 'pillbox'
    multiple: boolean
    open: boolean
    query: string
    activeIndex: number
    activeId: string
    selectedValues: string[]
    selectedOptions: SelectedOption[]
    display: string
    disabled: boolean
    emptyText: string
    emptyVisible: boolean
    selectedSuffix: string
    typeahead: string
    init: () => void
    destroy: () => void
    refresh: () => void
    scan: () => void
    applyQuery: () => void
    syncSelection: () => void
    renderTrigger: () => void
    setActive: (index: number) => void
    move: (delta: number) => void
    openPopover: () => void
    closePopover: () => void
    togglePopover: () => void
    onTriggerClick: () => void
    onTriggerFocus: () => void
    onListboxKeydown: (event: KeyboardEvent) => void
    onPillboxKeydown: (event: KeyboardEvent) => void
    onPillboxInput: (event: Event) => void
    onPillboxFocus: () => void
    selectFromEvent: (event: MouseEvent) => void
    choose: (option: ListboxOption) => void
    removeValue: (value: string) => void
    removeLast: () => void
    clear: () => void
    focusSearch: () => void
    commit: () => void
    dispatchChange: () => void
    filterByTypeahead: () => void
    handleNavigation: (event: KeyboardEvent, typeaheadEnabled: boolean) => void
}

export function selectData(config: SelectConfig, wire: WireLike | null): SelectData {
    let options: ListboxOption[] = []
    let visible: ListboxOption[] = []
    let popover: PopoverController | null = null
    let unwatch: (() => void) | null = null
    let suppressFocus = false

    const multiple = config.variant === 'pillbox' ? true : config.multiple

    const listbox = (): HTMLElement | null => document.getElementById(config.listboxId)
    const trigger = (): HTMLElement | null => document.getElementById(config.triggerId)
    const anchor = (): HTMLElement | null => document.getElementById(config.anchorId)
    const formInput = (): HTMLInputElement | null =>
        config.formInputId === null ? null : (document.getElementById(config.formInputId) as HTMLInputElement | null)

    return {
        variant: config.variant,
        multiple,
        open: false,
        query: '',
        activeIndex: -1,
        activeId: '',
        selectedValues: [],
        selectedOptions: [],
        display: '',
        disabled: config.disabled,
        emptyText: config.empty,
        emptyVisible: false,
        selectedSuffix: config.selectedSuffix,
        typeahead: '',

        init() {
            const anchorElement = anchor()
            const popoverElement = document.getElementById(config.popoverId)

            if (anchorElement === null || popoverElement === null) {
                return
            }

            this.selectedValues = normalizeValues(readModel(wire, config.model, config.value))
            this.refresh()

            popover = createPopover(anchorElement, popoverElement, {
                focusTarget: trigger(),
                onOpen: () => {
                    this.open = true
                },
                onClose: () => {
                    suppressFocus = true
                    this.open = false
                    this.query = ''
                    this.typeahead = ''
                    this.activeIndex = -1
                    this.activeId = ''
                    this.renderTrigger()

                    setTimeout(() => {
                        suppressFocus = false
                    }, 0)
                },
            })

            unwatch = watchModel(wire, config.model, (value) => {
                this.selectedValues = normalizeValues(value)
                this.refresh()
            })
        },

        destroy() {
            unwatch?.()
            popover?.destroy()
        },

        refresh() {
            const activeValue = visible[this.activeIndex]?.value ?? null

            this.scan()
            this.applyQuery()
            this.syncSelection()
            this.renderTrigger()

            if (activeValue !== null) {
                const activeIndex = visible.findIndex((option) => option.value === activeValue)

                if (activeIndex >= 0) {
                    this.setActive(activeIndex)

                    return
                }
            }

            const selectedIndex = visible.findIndex((option) => this.selectedValues.includes(option.value))

            if (selectedIndex >= 0) {
                this.setActive(selectedIndex)
            }
        },

        scan() {
            options = scanOptions(listbox())
            assignOptionIds(options, config.listboxId)
        },

        applyQuery() {
            visible = applyFilter(listbox(), options, this.query)
            this.emptyVisible = visible.length === 0

            const index = visible.length === 0 ? -1 : Math.max(0, Math.min(this.activeIndex, visible.length - 1))

            this.setActive(index)
        },

        syncSelection() {
            for (const option of options) {
                const selected = this.selectedValues.includes(option.value)

                option.el.toggleAttribute('data-selected', selected)
                option.el.setAttribute('aria-selected', selected ? 'true' : 'false')
                option.el.querySelector('[data-selected-indicator]')?.toggleAttribute('hidden', !selected)
            }

            this.selectedOptions = options
                .filter((option) => this.selectedValues.includes(option.value))
                .map((option) => ({
                    value: option.value,
                    label: option.label,
                    selectedLabel: option.selectedLabel,
                }))
        },

        renderTrigger() {
            if (this.variant === 'pillbox') {
                this.display = ''

                return
            }

            if (this.selectedOptions.length === 0) {
                this.display = ''

                return
            }

            if (!this.multiple || this.selectedOptions.length === 1) {
                this.display = this.selectedOptions[0]?.selectedLabel ?? ''

                return
            }

            this.display = this.selectedSuffix.replace(':count', String(this.selectedOptions.length))
        },

        setActive(index) {
            this.activeIndex = index

            for (const [optionIndex, option] of visible.entries()) {
                option.el.toggleAttribute('data-active', optionIndex === index)
            }

            const active = visible[index] ?? null

            this.activeId = this.open ? (active?.el.id ?? '') : ''

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

        openPopover() {
            if (this.disabled) {
                return
            }

            popover?.open()
            this.scan()

            if (this.variant !== 'pillbox') {
                this.query = ''
                this.typeahead = ''
            }

            this.applyQuery()

            const selectedIndex = visible.findIndex((option) => this.selectedValues.includes(option.value))

            this.setActive(selectedIndex >= 0 ? selectedIndex : 0)
        },

        closePopover() {
            popover?.close()
            this.query = ''
            this.typeahead = ''
            this.renderTrigger()
        },

        togglePopover() {
            if (this.open) {
                this.closePopover()
            } else {
                this.openPopover()
            }
        },

        onTriggerClick() {
            if (!this.open) {
                this.openPopover()
            }
        },

        onTriggerFocus() {
            if (suppressFocus) {
                return
            }

            if (!this.open) {
                this.openPopover()
            }
        },

        onPillboxFocus() {
            if (suppressFocus) {
                return
            }

            if (!this.open) {
                this.openPopover()
            }
        },

        onPillboxInput(event) {
            this.query = (event.target as HTMLInputElement).value

            if (!this.open) {
                this.openPopover()
            }

            this.applyQuery()
            this.setActive(0)
        },

        onListboxKeydown(event) {
            this.handleNavigation(event, true)
        },

        onPillboxKeydown(event) {
            if (event.key === 'Backspace' && this.query === '') {
                event.preventDefault()
                this.removeLast()

                return
            }

            this.handleNavigation(event, false)
        },

        handleNavigation(event, typeaheadEnabled) {
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
                event.preventDefault()

                if (!this.open) {
                    this.openPopover()
                } else {
                    const active = visible[this.activeIndex]

                    if (active !== undefined) {
                        this.choose(active)
                    }
                }

                return
            }

            if (event.key === 'Tab') {
                this.closePopover()

                return
            }

            if (!typeaheadEnabled || event.metaKey || event.ctrlKey || event.altKey) {
                return
            }

            if (event.key === 'Backspace') {
                event.preventDefault()
                this.typeahead = this.typeahead.slice(0, -1)
                this.filterByTypeahead()

                return
            }

            if (event.key.length === 1) {
                event.preventDefault()
                this.typeahead += event.key
                this.filterByTypeahead()
            }
        },

        filterByTypeahead() {
            if (!this.open) {
                this.openPopover()
            }

            this.query = this.typeahead
            this.applyQuery()
            this.setActive(0)
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

            if (option === undefined || option.disabled) {
                return
            }

            this.choose(option)
        },

        choose(option) {
            if (option.disabled) {
                return
            }

            if (this.multiple) {
                this.selectedValues = this.selectedValues.includes(option.value)
                    ? this.selectedValues.filter((value) => value !== option.value)
                    : [...this.selectedValues, option.value]
            } else {
                this.selectedValues = [option.value]
            }

            this.commit()
            this.syncSelection()
            this.renderTrigger()
            this.dispatchChange()

            if (this.multiple) {
                this.query = ''
                this.typeahead = ''
                this.applyQuery()

                const index = visible.indexOf(option)

                this.setActive(index >= 0 ? index : 0)

                if (this.variant === 'pillbox') {
                    this.focusSearch()
                }
            } else {
                const index = visible.indexOf(option)

                if (index >= 0) {
                    this.setActive(index)
                }

                this.closePopover()
            }
        },

        removeValue(value) {
            this.selectedValues = this.selectedValues.filter((selected) => selected !== value)
            this.commit()
            this.syncSelection()
            this.renderTrigger()
            this.dispatchChange()
            this.applyQuery()
            this.focusSearch()
        },

        removeLast() {
            if (this.selectedValues.length === 0) {
                return
            }

            this.selectedValues = this.selectedValues.slice(0, -1)
            this.commit()
            this.syncSelection()
            this.renderTrigger()
            this.dispatchChange()
            this.applyQuery()
        },

        clear() {
            this.selectedValues = []
            this.commit()
            this.syncSelection()
            this.renderTrigger()
            this.dispatchChange()
            this.closePopover()
        },

        focusSearch() {
            const element = trigger()

            if (element instanceof HTMLInputElement) {
                element.focus()
            }
        },

        commit() {
            const value = this.multiple ? [...this.selectedValues] : (this.selectedValues[0] ?? null)

            writeModel(wire, config.model, config.live, value, formInput())
        },

        dispatchChange() {
            const element = trigger()

            element?.dispatchEvent(
                new CustomEvent('change', {
                    bubbles: true,
                    detail: { value: this.multiple ? [...this.selectedValues] : (this.selectedValues[0] ?? null) },
                }),
            )
        },
    }
}
