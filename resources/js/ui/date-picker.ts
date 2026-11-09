import { resolveLocale } from '@/locale'
import { readModel, watchModel, writeModel, type WireLike } from '@/util/binding'
import {
    addDays,
    addMonthsClamped,
    compareKeys,
    daysBetweenInclusive,
    endOfMonth,
    endOfWeek,
    endOfYear,
    firstDayOfWeek,
    formatShortDate,
    localTodayKey,
    startOfMonth,
    startOfWeek,
    startOfYear,
    toDateKey,
} from '@/util/date'

import { createPopover, trapTabFocus, type PopoverController } from './popover'

export type DatePickerMode = 'single' | 'range'

/** Translated date-picker strings. */
export interface DatePickerLabels {
    placeholder: string
    placeholderRange: string
    clear: string
    cancel: string
    select: string
    selectRange: string
    presets: string
    presetLabels: Record<string, string>
}

/** Blade-to-Alpine config for a date picker. */
export interface DatePickerConfig {
    mode: DatePickerMode
    value: unknown
    model: string | null
    live: boolean
    locale: string | null
    startDay: number | null
    min: string | null
    max: string | null
    unavailable: string[]
    minRange: number | null
    maxRange: number | null
    /** Require the footer even in single mode. */
    withConfirmation: boolean
    withPresets: boolean
    presetKeys: string[]
    placeholder: string | null
    clearable: boolean
    disabled: boolean
    triggerId: string
    popoverId: string
    formInputId: string | null
    formStartId: string | null
    formEndId: string | null
    labels: DatePickerLabels
}

/**
 * Alpine data for the date picker popover.
 *
 * The picker keeps a committed value and a draft value. Opening resets the draft
 * from the committed value; cancelling discards it. Single mode commits
 * immediately, while range/confirmation mode waits for the footer. Presets use
 * the browser's local date and are filtered against the active constraints.
 */
export interface DatePicker {
    mode: DatePickerMode
    disabled: boolean
    labels: DatePickerConfig['labels']
    display: string
    start: string | null
    end: string | null
    draftStart: string | null
    draftEnd: string | null
    presets: DatePreset[]
    withConfirmation: boolean
    init: (el?: HTMLElement) => void
    destroy: () => void
    applyValue: (value: unknown) => void
    isPopoverOpen: () => boolean
    openPopover: () => void
    closePopover: () => void
    cancel: () => void
    confirm: () => void
    clear: () => void
    commit: () => void
    onTriggerClick: () => void
    onTriggerFocus: () => void
    onTriggerKeydown: (event: KeyboardEvent) => void
    onCalendarChange: (event: Event) => void
    onPopoverKeydown: (event: KeyboardEvent) => void
    selectPreset: (key: string) => void
    buildPresets: () => void
    presetActive: (key: string) => boolean
    canConfirm: () => boolean
    syncCalendar: () => void
    renderDisplay: () => void
    placeholderText: () => string
    focusFirst: () => void
    dispatch: (name: string, value?: unknown) => void
}

interface Bounds {
    start: string | null
    end: string | null
}

/** A preset range, filtered to the picker constraints. */
interface DatePreset {
    key: string
    label: string
    start: string
    end: string
}

interface CalendarHandle {
    setValue: (value: unknown) => void
    refreshToday: () => void
}

function normalize(value: unknown, mode: DatePickerMode): Bounds {
    if (mode === 'range') {
        if (Array.isArray(value)) {
            return { start: toDateKey(value[0]), end: toDateKey(value[1]) }
        }

        if (typeof value === 'object' && value !== null) {
            const range = value as { start?: unknown; end?: unknown }

            return { start: toDateKey(range.start), end: toDateKey(range.end) }
        }

        if (typeof value === 'string') {
            const [start, end] = value.split('/', 2)

            return { start: toDateKey(start ?? null), end: toDateKey(end ?? null) }
        }

        return { start: null, end: null }
    }

    return { start: toDateKey(value), end: null }
}

/** Compute a preset range from the browser's local today. */
function presetBounds(
    key: string,
    today: string,
    startDay: number,
    min: string | null,
): { start: string; end: string } | null {
    switch (key) {
        case 'today':
            return { start: today, end: today }
        case 'yesterday': {
            const day = addDays(today, -1)

            return { start: day, end: day }
        }
        case 'thisWeek':
            return { start: startOfWeek(today, startDay), end: endOfWeek(today, startDay) }
        case 'lastWeek': {
            const day = addDays(today, -7)

            return { start: startOfWeek(day, startDay), end: endOfWeek(day, startDay) }
        }
        case 'last7Days':
            return { start: addDays(today, -6), end: today }
        case 'last14Days':
            return { start: addDays(today, -13), end: today }
        case 'last30Days':
            return { start: addDays(today, -29), end: today }
        case 'thisMonth':
            return { start: startOfMonth(today), end: endOfMonth(today) }
        case 'lastMonth': {
            const day = addMonthsClamped(startOfMonth(today), -1)

            return { start: startOfMonth(day), end: endOfMonth(day) }
        }
        case 'thisYear':
            return { start: startOfYear(today), end: endOfYear(today) }
        case 'lastYear': {
            const day = addMonthsClamped(startOfYear(today), -12)

            return { start: startOfYear(day), end: endOfYear(day) }
        }
        case 'yearToDate':
            return { start: startOfYear(today), end: today }
        case 'allTime':
            return min === null ? null : { start: min, end: today }
    }

    return null
}

/** Clamp a preset to the picker bounds, dropping ranges that collapse. */
function clampBounds(
    bounds: { start: string; end: string },
    min: string | null,
    max: string | null,
): { start: string; end: string } | null {
    let { start, end } = bounds

    if (min !== null && compareKeys(start, min) < 0) {
        start = min
    }

    if (max !== null && compareKeys(end, max) > 0) {
        end = max
    }

    return compareKeys(start, end) <= 0 ? { start, end } : null
}

/** Check a preset against unavailable dates and range-length rules. */
function presetAllowed(
    bounds: { start: string; end: string },
    unavailable: string[],
    minRange: number | null,
    maxRange: number | null,
    enforceRange: boolean,
): boolean {
    const length = daysBetweenInclusive(bounds.start, bounds.end)

    if (enforceRange && minRange !== null && length < minRange) {
        return false
    }

    if (enforceRange && maxRange !== null && length > maxRange) {
        return false
    }

    const from = compareKeys(bounds.start, bounds.end) <= 0 ? bounds.start : bounds.end
    const to = compareKeys(bounds.start, bounds.end) <= 0 ? bounds.end : bounds.start

    return !unavailable.some((key) => compareKeys(key, from) >= 0 && compareKeys(key, to) <= 0)
}

/**
 * Create the Alpine data for a date picker.
 *
 * Writes a `Y-m-d` string for single mode and `{ start, end }` for range mode.
 * The nested calendar is a separate Alpine component controlled through
 * `syncCalendar()`.
 */
export function datePickerData(config: DatePickerConfig, wire: WireLike | null): DatePicker {
    let rootEl: HTMLElement | null = null
    let popover: PopoverController | null = null
    let unwatch: (() => void) | null = null

    const root = (): HTMLElement | null => rootEl
    const popoverElement = (): HTMLElement | null => document.getElementById(config.popoverId)
    const formInput = (): HTMLInputElement | null =>
        config.formInputId === null ? null : (document.getElementById(config.formInputId) as HTMLInputElement | null)

    const applyValue = (target: DatePicker, value: unknown): void => {
        const bounds = normalize(value, target.mode)

        target.start = bounds.start
        target.end = bounds.end
    }

    const dispatch = (target: DatePicker, name: string, value?: unknown): void => {
        const detail = value === undefined ? {} : { value }

        root()?.dispatchEvent(new CustomEvent(name, { bubbles: true, detail }))
    }

    const locale = resolveLocale(config.locale)

    return {
        mode: config.mode,
        disabled: config.disabled,
        labels: config.labels,
        display: '',
        start: null,
        end: null,
        draftStart: null,
        draftEnd: null,
        presets: [],
        withConfirmation: config.withConfirmation,

        init(el) {
            // Alpine calls init() itself before x-init supplies the root element.
            if (el === undefined || rootEl !== null) {
                return
            }

            rootEl = el
            applyValue(this, readModel(wire, config.model, config.value))

            this.buildPresets()
            this.renderDisplay()

            const trigger = document.getElementById(config.triggerId)
            const popoverTarget = popoverElement()

            if (trigger !== null && popoverTarget !== null) {
                popover = createPopover(trigger, popoverTarget, {
                    focusTarget: trigger,
                    fullWidthOnTouch: true,
                    scrollLock: true,
                    // Single mode fits the 7 × (w-9) grid; range keeps the trigger width.
                    matchTriggerWidth: config.mode === 'range',
                    onOpen: () => {
                        this.buildPresets()
                        this.draftStart = this.start
                        this.draftEnd = this.end
                        this.syncCalendar()
                        this.focusFirst()
                        dispatch(this, 'open')
                    },
                    onClose: () => {
                        this.draftStart = this.start
                        this.draftEnd = this.end
                        dispatch(this, 'close')
                    },
                })
            }

            unwatch = watchModel(wire, config.model, (value) => {
                this.applyValue(value)
                this.renderDisplay()

                if (this.isPopoverOpen()) {
                    this.draftStart = this.start
                    this.draftEnd = this.end
                    this.syncCalendar()
                }
            })
        },

        destroy() {
            unwatch?.()
            popover?.destroy()
        },

        applyValue(value) {
            applyValue(this, value)
        },

        isPopoverOpen() {
            return popover?.isOpen() ?? false
        },

        openPopover() {
            if (this.disabled || this.isPopoverOpen()) {
                return
            }

            popover?.open()
        },

        closePopover() {
            popover?.close()
        },

        cancel() {
            this.closePopover()
        },

        confirm() {
            if (!this.canConfirm()) {
                return
            }

            this.commit()
            this.closePopover()
        },

        clear() {
            if (this.disabled) {
                return
            }

            this.start = null
            this.end = null
            this.draftStart = null
            this.draftEnd = null

            if (this.isPopoverOpen()) {
                this.syncCalendar()
            }

            this.commit()
            this.dispatch('clear')
            this.closePopover()
        },

        commit() {
            // Range models keep their shape when cleared; a bare null resets a typed
            // array property to [] and breaks consumers reading start/end.
            const value = this.mode === 'range' ? { start: this.draftStart, end: this.draftEnd } : this.draftStart

            this.start = this.draftStart
            this.end = this.draftEnd

            if (config.model !== null) {
                writeModel(wire, config.model, config.live, value, null)
            } else if (this.mode === 'single') {
                writeModel(wire, null, config.live, value, formInput())
            } else {
                setRangeInputs(config.formStartId, config.formEndId, this.start, this.end)
            }

            this.renderDisplay()
            this.dispatch('change', value)
        },

        onTriggerClick() {
            this.openPopover()
        },

        onTriggerFocus() {
            this.openPopover()
        },

        onTriggerKeydown(event) {
            if (this.disabled) {
                return
            }

            if (event.key === 'ArrowDown' || event.key === 'ArrowUp' || event.key === 'Enter' || event.key === ' ') {
                event.preventDefault()
                this.openPopover()

                return
            }

            if (event.key === 'Escape' && this.isPopoverOpen()) {
                event.preventDefault()
                this.closePopover()
            }
        },

        onCalendarChange(event) {
            const detail = (event as CustomEvent<{ value?: unknown }>).detail

            if (detail === undefined || !('value' in detail)) {
                return
            }

            if (this.mode === 'range') {
                const bounds = normalize(detail.value, 'range')

                this.draftStart = bounds.start
                this.draftEnd = bounds.end

                return
            }

            this.draftStart = toDateKey(detail.value)
            this.draftEnd = null

            if (!this.withConfirmation) {
                this.commit()
                this.closePopover()
            }
        },

        onPopoverKeydown(event) {
            const target = popoverElement()

            if (target !== null) {
                trapTabFocus(target, event)
            }
        },

        /** Rebuild the presets from the current local date and bounds. */
        buildPresets() {
            const today = localTodayKey()
            const startDay = config.startDay ?? firstDayOfWeek(locale)
            const min = config.min === 'today' ? today : toDateKey(config.min)
            const max = config.max === 'today' ? today : toDateKey(config.max)

            this.presets = config.presetKeys.flatMap((key) => {
                const bounds = presetBounds(key, today, startDay, min)
                const clamped = bounds === null ? null : clampBounds(bounds, min, max)

                if (clamped === null) {
                    return []
                }

                if (this.mode === 'single' && !['today', 'yesterday'].includes(key)) {
                    return []
                }

                if (
                    !presetAllowed(clamped, config.unavailable, config.minRange, config.maxRange, this.mode === 'range')
                ) {
                    return []
                }

                return [{ key, label: config.labels.presetLabels[key] ?? key, ...clamped }]
            })
        },

        selectPreset(key) {
            this.buildPresets()

            const preset = this.presets.find((candidate) => candidate.key === key)

            if (preset === undefined) {
                return
            }

            this.draftStart = preset.start
            this.draftEnd = this.mode === 'range' ? preset.end : null
            this.syncCalendar()

            if (!this.withConfirmation) {
                this.commit()
                this.closePopover()
            }
        },

        presetActive(key) {
            const preset = this.presets.find((candidate) => candidate.key === key)

            if (preset === undefined) {
                return false
            }

            const start = this.isPopoverOpen() ? this.draftStart : this.start
            const end = this.isPopoverOpen() ? this.draftEnd : this.end

            return start === preset.start && (this.mode === 'single' || end === preset.end)
        },

        canConfirm() {
            return this.draftStart !== null && (this.mode === 'single' || this.draftEnd !== null)
        },

        syncCalendar() {
            const calendar = popoverElement()?.querySelector<HTMLElement>('[data-ui-calendar]')

            if (calendar === undefined || calendar === null || typeof globalThis.Alpine === 'undefined') {
                return
            }

            const data = globalThis.Alpine.$data(calendar) as CalendarHandle | undefined

            data?.refreshToday()
            data?.setValue(this.mode === 'range' ? { start: this.draftStart, end: this.draftEnd } : this.draftStart)
        },

        renderDisplay() {
            if (this.start === null) {
                this.display = ''

                return
            }

            if (this.mode === 'single' || this.end === null) {
                this.display = formatShortDate(locale, this.start)

                return
            }

            this.display = `${formatShortDate(locale, this.start)} – ${formatShortDate(locale, this.end)}`
        },

        placeholderText() {
            return (
                config.placeholder ?? (this.mode === 'range' ? this.labels.placeholderRange : this.labels.placeholder)
            )
        },

        focusFirst() {
            window.setTimeout(() => {
                const target = popoverElement()

                if (target === null) {
                    return
                }

                const initial =
                    target.querySelector<HTMLElement>('td button[tabindex="0"]:not([disabled])') ??
                    target.querySelector<HTMLElement>('td button:not([disabled])') ??
                    target.querySelector<HTMLElement>('button:not([disabled])')

                initial?.focus()
            }, 0)
        },

        dispatch(name, value) {
            dispatch(this, name, value)
        },
    }
}

function setRangeInputs(startId: string | null, endId: string | null, start: string | null, end: string | null): void {
    for (const [id, value] of [
        [startId, start],
        [endId, end],
    ] as const) {
        if (id === null) {
            continue
        }

        const input = document.getElementById(id) as HTMLInputElement | null

        if (input === null) {
            continue
        }

        input.value = value ?? ''
        input.dispatchEvent(new Event('input', { bubbles: true }))
        input.dispatchEvent(new Event('change', { bubbles: true }))
    }
}
