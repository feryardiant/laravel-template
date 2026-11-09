import { normalizeValues, readModel, watchModel, writeModel, type WireLike } from '@/util/binding'
import { applyFilter, assignOptionIds, scanOptions, type ListboxOption } from '@/util/listbox'

import { createPopover, type PopoverController } from './popover'

/** Blade-to-Alpine config for a select. */
export interface SelectConfig {
    /** Pillbox always behaves as multiple. */
    variant: 'listbox' | 'pillbox'
    multiple: boolean
    value: unknown
    model: string | null
    live: boolean
    disabled: boolean
    empty: string
    selectedSuffix: string
    triggerId: string
    anchorId: string
    popoverId: string
    listboxId: string
    formInputId: string | null
}

/** Selected option rendered in the trigger or pill list. */
export interface SelectedOption {
    value: string
    label: string
    selectedLabel: string
}

/**
 * Alpine data for the select listbox and pillbox.
 *
 * Pillbox implies multiple selection. The visible trigger is readonly; keyboard
 * input drives typeahead filtering. Writes go through Livewire or the hidden
 * plain-form input.
 */
export interface Select {
    variant: 'listbox' | 'pillbox'
    multiple: boolean
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
    isPopoverOpen: () => boolean
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
    dispatch: (name: string) => void
    dispatchChange: () => void
    filterByTypeahead: () => void
    handleNavigation: (event: KeyboardEvent, typeaheadEnabled: boolean) => void
}

/**
 * Create the Alpine data for a select.
 *
 * `variant: 'pillbox'` forces multiple selection. The factory owns popover
 * placement, active-option movement, selection and model writes.
 */
export function selectData(config: SelectConfig, wire: WireLike | null): Select {
    let options: ListboxOption[] = []
    let visible: ListboxOption[] = []
    let popover: PopoverController | null = null
    let unwatch: (() => void) | null = null
    let ready = false
    let form: HTMLFormElement | null = null
    let onReset: (() => void) | null = null
    let initialValues: string[] = []

    const multiple = config.variant === 'pillbox' ? true : config.multiple

    const listbox = (): HTMLElement | null => document.getElementById(config.listboxId)
    const trigger = (): HTMLElement | null => document.getElementById(config.triggerId)
    const anchor = (): HTMLElement | null => document.getElementById(config.anchorId)
    const formInput = (): HTMLInputElement | null =>
        config.formInputId === null ? null : (document.getElementById(config.formInputId) as HTMLInputElement | null)

    return {
        variant: config.variant,
        multiple,
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
            if (ready) {
                return
            }

            ready = true

            const anchorElement = anchor()
            const popoverElement = document.getElementById(config.popoverId)

            if (anchorElement === null || popoverElement === null) {
                return
            }

            this.selectedValues = normalizeValues(readModel(wire, config.model, config.value))
            initialValues = [...this.selectedValues]
            this.refresh()

            popover = createPopover(trigger() ?? anchorElement, popoverElement, {
                anchor: anchorElement,
                focusTarget: trigger(),
                onOpen: () => {
                    this.dispatch('open')
                },
                onClose: () => {
                    this.query = ''
                    this.typeahead = ''
                    this.activeIndex = -1
                    this.activeId = ''
                    this.renderTrigger()
                    this.dispatch('close')
                },
            })

            unwatch = watchModel(wire, config.model, (value) => {
                this.selectedValues = normalizeValues(value)
                this.refresh()
            })

            form = anchorElement.closest('form')

            if (form !== null) {
                onReset = () => {
                    // Run after the browser restores the form controls, then put
                    // the trigger and submitted value back in sync.
                    window.setTimeout(() => {
                        this.selectedValues = [...initialValues]
                        this.refresh()

                        const element = trigger()

                        if (element instanceof HTMLInputElement) {
                            element.value = this.display
                        }

                        this.commit()
                    }, 0)
                }

                form.addEventListener('reset', onReset)
            }
        },

        destroy() {
            unwatch?.()
            popover?.destroy()

            if (form !== null && onReset !== null) {
                form.removeEventListener('reset', onReset)
            }
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
            if (visible.length === 0) {
                this.activeIndex = -1
                this.activeId = ''

                for (const option of options) {
                    option.el.removeAttribute('data-active')
                }

                return
            }

            let target = ((index % visible.length) + visible.length) % visible.length

            for (let step = 0; step < visible.length; step++) {
                if (!visible[target].disabled) {
                    break
                }

                target = (target + 1) % visible.length
            }

            if (visible[target].disabled) {
                this.activeIndex = -1
                this.activeId = ''

                for (const option of options) {
                    option.el.removeAttribute('data-active')
                }

                return
            }

            this.activeIndex = target

            for (const [optionIndex, option] of visible.entries()) {
                option.el.toggleAttribute('data-active', optionIndex === target)
            }

            const active = visible[target] ?? null

            this.activeId = this.isPopoverOpen() ? (active?.el.id ?? '') : ''

            if (this.isPopoverOpen()) {
                active?.el.scrollIntoView({ block: 'nearest' })
            }
        },

        move(delta) {
            const enabled = visible.filter((option) => !option.disabled)

            if (enabled.length === 0) {
                return
            }

            const current = visible[this.activeIndex]
            const currentIndex = current ? enabled.indexOf(current) : -1
            const next =
                currentIndex < 0
                    ? delta > 0
                        ? 0
                        : enabled.length - 1
                    : (currentIndex + delta + enabled.length) % enabled.length

            this.setActive(visible.indexOf(enabled[next]))
        },

        isPopoverOpen() {
            return popover?.isOpen() ?? false
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
            if (this.isPopoverOpen()) {
                this.closePopover()
            } else {
                this.openPopover()
            }
        },

        onTriggerClick() {
            if (!this.isPopoverOpen()) {
                this.openPopover()
            }
        },

        onTriggerFocus() {
            if (!this.isPopoverOpen()) {
                this.openPopover()
            }
        },

        onPillboxFocus() {
            if (!this.isPopoverOpen()) {
                this.openPopover()
            }
        },

        onPillboxInput(event) {
            if (this.disabled) {
                return
            }

            this.query = (event.target as HTMLInputElement).value

            if (!this.isPopoverOpen()) {
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
                if (this.isPopoverOpen()) {
                    event.preventDefault()
                    this.closePopover()
                }

                return
            }

            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault()

                if (!this.isPopoverOpen()) {
                    this.openPopover()
                } else {
                    this.move(event.key === 'ArrowDown' ? 1 : -1)
                }

                return
            }

            if (event.key === 'Home' || event.key === 'End') {
                const enabled = visible.filter((option) => !option.disabled)

                if (this.isPopoverOpen() && enabled.length > 0) {
                    event.preventDefault()
                    this.setActive(visible.indexOf(event.key === 'Home' ? enabled[0] : enabled[enabled.length - 1]))
                }

                return
            }

            if (event.key === 'Enter') {
                event.preventDefault()

                if (!this.isPopoverOpen()) {
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
            // openPopover() clears the query; keep the character that reopened it.
            const typeahead = this.typeahead

            if (!this.isPopoverOpen()) {
                this.openPopover()
            }

            this.typeahead = typeahead
            this.query = typeahead
            this.applyQuery()
            this.setActive(0)
        },

        selectFromEvent(event) {
            if (this.disabled) {
                return
            }

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
            if (this.disabled || option.disabled) {
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
            if (this.disabled) {
                return
            }

            this.selectedValues = this.selectedValues.filter((selected) => selected !== value)
            this.commit()
            this.syncSelection()
            this.renderTrigger()
            this.dispatchChange()
            this.applyQuery()
            this.focusSearch()
        },

        removeLast() {
            if (this.disabled || this.selectedValues.length === 0) {
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
            if (this.disabled) {
                return
            }

            this.selectedValues = []
            this.commit()
            this.syncSelection()
            this.renderTrigger()
            this.dispatchChange()
            this.dispatch('clear')
            this.closePopover()
        },

        focusSearch() {
            if (this.disabled) {
                return
            }

            const element = trigger()

            if (element instanceof HTMLInputElement) {
                element.focus()
            }
        },

        commit() {
            const value = this.multiple ? [...this.selectedValues] : (this.selectedValues[0] ?? null)

            writeModel(wire, config.model, config.live, value, formInput())
        },

        dispatch(name) {
            const element = trigger() ?? anchor()
            const detail =
                name === 'change'
                    ? { value: this.multiple ? [...this.selectedValues] : (this.selectedValues[0] ?? null) }
                    : {}

            element?.dispatchEvent(new CustomEvent(name, { bubbles: true, detail }))
        },

        dispatchChange() {
            this.dispatch('change')
        },
    }
}
