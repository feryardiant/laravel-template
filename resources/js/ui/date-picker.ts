import { readModel, watchModel, writeModel } from './binding'
import {
    addDays,
    addMonthsClamped,
    compareKeys,
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
} from './date'
import { createPopover, type PopoverController } from './popover'
import type { DatePickerConfig, DatePickerMode, WireLike } from './types'

interface Bounds {
    start: string | null
    end: string | null
}

interface DatePreset {
    key: string
    label: string
    start: string
    end: string
}

interface CalendarHandle {
    setValue: (value: unknown) => void
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

export interface DatePickerData {
    mode: DatePickerMode
    open: boolean
    disabled: boolean
    labels: DatePickerConfig['labels']
    display: string
    start: string | null
    end: string | null
    draftStart: string | null
    draftEnd: string | null
    presets: DatePreset[]
    withConfirmation: boolean
    init: () => void
    destroy: () => void
    applyValue: (value: unknown) => void
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
    presetActive: (key: string) => boolean
    canConfirm: () => boolean
    syncCalendar: () => void
    renderDisplay: () => void
    placeholderText: () => string
    focusFirst: () => void
    dispatch: (name: string, value?: unknown) => void
}

export function datePickerData(config: DatePickerConfig, wire: WireLike | null): DatePickerData {
    let popover: PopoverController | null = null
    let unwatch: (() => void) | null = null
    let suppressFocus = false

    const root = (): HTMLElement | null => document.getElementById(config.rootId)
    const popoverElement = (): HTMLElement | null => document.getElementById(config.popoverId)
    const formInput = (): HTMLInputElement | null =>
        config.formInputId === null ? null : (document.getElementById(config.formInputId) as HTMLInputElement | null)

    const applyValue = (target: DatePickerData, value: unknown): void => {
        const bounds = normalize(value, target.mode)

        target.start = bounds.start
        target.end = bounds.end
    }

    const dispatch = (target: DatePickerData, name: string, value?: unknown): void => {
        root()?.dispatchEvent(new CustomEvent(name, { bubbles: true, detail: { value } }))
    }

    return {
        mode: config.mode,
        open: false,
        disabled: config.disabled,
        labels: config.labels,
        display: '',
        start: null,
        end: null,
        draftStart: null,
        draftEnd: null,
        presets: [],
        withConfirmation: config.withConfirmation,

        init() {
            applyValue(this, readModel(wire, config.model, config.value))

            const today = localTodayKey()
            const startDay = config.startDay ?? firstDayOfWeek(config.locale)
            const min = config.min === 'today' ? today : toDateKey(config.min)
            const max = config.max === 'today' ? today : toDateKey(config.max)

            this.presets = config.presetKeys.flatMap((key) => {
                const bounds = presetBounds(key, today, startDay, min)
                const clamped = bounds === null ? null : clampBounds(bounds, min, max)

                return clamped === null ? [] : [{ key, label: config.labels.presetLabels[key] ?? key, ...clamped }]
            })

            this.renderDisplay()

            const trigger = document.getElementById(config.triggerId)
            const popoverTarget = popoverElement()

            if (trigger !== null && popoverTarget !== null) {
                popover = createPopover(trigger, popoverTarget, {
                    focusTarget: trigger,
                    fullWidthOnTouch: true,
                    onOpen: () => {
                        this.open = true
                        this.draftStart = this.start
                        this.draftEnd = this.end
                        this.syncCalendar()
                        this.focusFirst()
                        dispatch(this, 'open')
                    },
                    onClose: () => {
                        suppressFocus = true
                        this.open = false
                        this.draftStart = this.start
                        this.draftEnd = this.end
                        dispatch(this, 'close')

                        setTimeout(() => {
                            suppressFocus = false
                        }, 0)
                    },
                })
            }

            unwatch = watchModel(wire, config.model, (value) => {
                this.applyValue(value)
                this.renderDisplay()

                if (this.open) {
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

        openPopover() {
            if (this.disabled || this.open) {
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

            if (this.open) {
                this.syncCalendar()
            }

            this.commit()
            this.dispatch('clear')
            this.closePopover()
        },

        commit() {
            const value =
                this.mode === 'range'
                    ? this.draftStart === null
                        ? null
                        : { start: this.draftStart, end: this.draftEnd }
                    : this.draftStart

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
            if (suppressFocus) {
                return
            }

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

            if (event.key === 'Escape' && this.open) {
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
            if (event.key !== 'Tab') {
                return
            }

            const target = popoverElement()

            if (target === null) {
                return
            }

            const focusables = Array.from(
                target.querySelectorAll<HTMLElement>(
                    'a[href], button:not([disabled]), input:not([disabled]), [tabindex]',
                ),
            ).filter((element) => element.tabIndex >= 0 && element.offsetWidth > 0)

            if (focusables.length === 0) {
                return
            }

            const first = focusables[0]
            const last = focusables[focusables.length - 1]

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault()
                last.focus()
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault()
                first.focus()
            }
        },

        selectPreset(key) {
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

            const start = this.open ? this.draftStart : this.start
            const end = this.open ? this.draftEnd : this.end

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

            data?.setValue(this.mode === 'range' ? { start: this.draftStart, end: this.draftEnd } : this.draftStart)
        },

        renderDisplay() {
            if (this.start === null) {
                this.display = ''

                return
            }

            if (this.mode === 'single' || this.end === null) {
                this.display = formatShortDate(config.locale, this.start)

                return
            }

            this.display = `${formatShortDate(config.locale, this.start)} – ${formatShortDate(config.locale, this.end)}`
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
                    target.querySelector<HTMLElement>('td[data-active] button:not([disabled])') ??
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
