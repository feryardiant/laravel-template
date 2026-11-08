import { readModel, watchModel, writeModel } from './binding'
import { createPopover, trapTabFocus, type PopoverController } from './popover'
import {
    compareTime,
    formatTime,
    isTimeUnavailable,
    localNowKey,
    minutesOfDay,
    parseUnavailable,
    prefersHour12,
    timeFromMinutes,
    toTime,
    type TimeRange,
} from './time'
import type { TimeOption, TimePickerConfig, WireLike } from './types'

/**
 * Alpine data for the time picker popover.
 *
 * Values are wall-clock `H:i` strings. Single mode applies on minute selection;
 * multiple mode appends to a removable list. 12-hour mode only changes the
 * visible hour options and meridiem; the stored value stays 24-hour.
 */

/** Display hour for 12/24-hour mode; `value` is always the 24-hour hour. */
interface HourOption {
    value: number
    label: string
}

/** State and actions exposed to the time picker Blade markup. */
export interface TimePickerData {
    hour12: boolean
    multiple: boolean
    disabled: boolean
    labels: TimePickerConfig['labels']
    display: string
    selected: string | null
    selectedValues: string[]
    hour: number | null
    minute: number | null
    meridiem: 'am' | 'pm'
    hours: TimeOption[]
    minutes: TimeOption[]
    activeHour: number | null
    activeMinute: number | null
    activeSelected: string | null
    preservedHour: number | null
    now: string
    init: (el?: HTMLElement) => void
    destroy: () => void
    applyValue: (value: unknown) => void
    isPopoverOpen: () => boolean
    openPopover: () => void
    closePopover: () => void
    clear: () => void
    commit: () => void
    selectHour: (value: number) => void
    selectMinute: (value: number) => void
    setMeridiem: (period: 'am' | 'pm') => void
    add: () => void
    remove: (value: string) => void
    onTriggerClick: () => void
    onTriggerFocus: () => void
    onTriggerKeydown: (event: KeyboardEvent) => void
    onHourKeydown: (event: KeyboardEvent) => void
    onMinuteKeydown: (event: KeyboardEvent) => void
    onPopoverKeydown: (event: KeyboardEvent) => void
    canAdd: () => boolean
    hasValue: () => boolean
    isSelected: (value: string) => boolean
    activeHourId: () => string | null
    activeMinuteId: () => string | null
    activeSelectedId: () => string | null
    selectedOptionId: (value: string) => string
    onSelectedKeydown: (event: KeyboardEvent) => void
    syncSelectedActive: () => void
    placeholderText: () => string
    formatSelected: (value: string) => string
    renderDisplay: () => void
    resolveBounds: () => void
    initialDraft: () => string
    setDraft: (key: string, preserve?: boolean) => void
    changeHour: (value: number) => void
    build: () => void
    hourOptions: () => HourOption[]
    minuteCandidates: (hour: number) => number[]
    minuteValues: () => number[]
    hourEnabled: (hour: number) => boolean
    isDisabled: (hour: number, minute: number) => boolean
    compose: () => string | null
    syncActive: (options: TimeOption[], current: number | null, selected: number | null) => number | null
    onColumnKeydown: (event: KeyboardEvent, column: 'hour' | 'minute') => void
    scrollColumn: (column: 'hour' | 'minute') => void
    focusFirst: () => void
    dispatch: (name: string, value?: unknown) => void
}

/**
 * Create the Alpine data for a time picker.
 *
 * Writes `H:i` for single mode and a sorted array for multiple mode. Off-step
 * values are preserved only for their original hour so a new choice still
 * follows the configured interval.
 */
export function timePickerData(config: TimePickerConfig, wire: WireLike | null): TimePickerData {
    let rootEl: HTMLElement | null = null
    let popover: PopoverController | null = null
    let unwatch: (() => void) | null = null
    let minKey: string | null = null
    let maxKey: string | null = null
    let unavailable: TimeRange[] = []

    const root = (): HTMLElement | null => rootEl
    const popoverElement = (): HTMLElement | null => document.getElementById(config.popoverId)
    const formInput = (): HTMLInputElement | null =>
        config.formInputId === null ? null : (document.getElementById(config.formInputId) as HTMLInputElement | null)
    const interval = Math.min(60, Math.max(1, config.interval))

    const dispatch = (target: TimePickerData, name: string, value?: unknown): void => {
        const detail = value === undefined ? {} : { value }

        root()?.dispatchEvent(new CustomEvent(name, { bubbles: true, detail }))
    }

    const normalize = (value: unknown, multiple: boolean): string | string[] | null => {
        if (multiple) {
            const entries = Array.isArray(value) ? value : typeof value === 'string' ? value.split(',') : []
            const times = entries.map((entry) => toTime(entry)).filter((entry): entry is string => entry !== null)

            return Array.from(new Set(times)).sort(compareTime)
        }

        return toTime(value)
    }

    return {
        hour12: config.format === '12-hour' || (config.format === 'auto' && prefersHour12(config.locale)),
        multiple: config.multiple,
        disabled: config.disabled,
        labels: config.labels,
        display: '',
        selected: null,
        selectedValues: [],
        hour: null,
        minute: null,
        meridiem: 'am',
        hours: [],
        minutes: [],
        activeHour: null,
        activeMinute: null,
        activeSelected: null,
        preservedHour: null,

        init(el) {
            rootEl = el ?? null
            unavailable = parseUnavailable(config.unavailable)
            this.applyValue(readModel(wire, config.model, config.value))
            this.resolveBounds()
            this.setDraft(this.initialDraft(), !this.multiple && this.selected !== null)
            this.build()

            const trigger = document.getElementById(config.triggerId)
            const target = popoverElement()

            if (trigger !== null && target !== null) {
                popover = createPopover(trigger, target, {
                    focusTarget: trigger,
                    fullWidthOnTouch: true,
                    matchTriggerWidth: false,
                    scrollLock: true,
                    onOpen: () => {
                        this.focusFirst()
                        dispatch(this, 'open')
                    },
                    onClose: () => {
                        dispatch(this, 'close')
                    },
                })
            }

            unwatch = watchModel(wire, config.model, (value) => {
                this.applyValue(value)
                this.renderDisplay()

                if (this.isPopoverOpen() && !this.multiple) {
                    this.setDraft(this.selected ?? this.now, this.selected !== null)
                    this.activeHour = null
                    this.activeMinute = null
                    this.build()
                }
            })
        },

        destroy() {
            unwatch?.()
            popover?.destroy()
        },

        applyValue(value) {
            if (this.multiple) {
                this.selectedValues = normalize(value, true) as string[]
                this.syncSelectedActive()
            } else {
                this.selected = normalize(value, false) as string | null
            }

            this.renderDisplay()
        },

        isPopoverOpen() {
            return popover?.isOpen() ?? false
        },

        openPopover() {
            if (this.disabled || this.isPopoverOpen()) {
                return
            }

            this.resolveBounds()
            this.activeHour = null
            this.activeMinute = null
            this.setDraft(this.initialDraft(), !this.multiple && this.selected !== null)
            this.build()
            popover?.open()
        },

        closePopover() {
            popover?.close()
        },

        clear() {
            if (this.disabled) {
                return
            }

            this.selected = null
            this.selectedValues = []
            this.activeSelected = null
            this.minute = null
            this.commit()
            dispatch(this, 'clear')
            this.closePopover()
        },

        commit() {
            const value = this.multiple ? [...this.selectedValues] : this.selected

            writeModel(wire, config.model, config.live, value, this.multiple ? null : formInput())
            this.renderDisplay()
            dispatch(this, 'change', value)
        },

        selectHour(value) {
            if (this.disabled) {
                return
            }

            const option = this.hours.find((candidate) => candidate.value === value)

            if (option === undefined || option.disabled) {
                return
            }

            this.changeHour(value)
            this.activeHour = value
            this.build()

            window.setTimeout(() => {
                this.scrollColumn('minute')
            }, 0)
        },

        selectMinute(value) {
            if (this.disabled || this.hour === null) {
                return
            }

            const option = this.minutes.find((candidate) => candidate.value === value)

            if (option === undefined || option.disabled) {
                return
            }

            this.minute = value
            this.activeMinute = value

            if (this.multiple) {
                this.build()

                return
            }

            const time = this.compose()

            if (time === null) {
                return
            }

            this.selected = time
            this.commit()
            this.closePopover()
        },

        setMeridiem(period) {
            if (this.meridiem === period) {
                this.build()

                return
            }

            if (this.hour !== null) {
                const display = this.hour % 12 || 12
                const hour = (display % 12) + (period === 'pm' ? 12 : 0)
                this.changeHour(hour)
                this.activeHour = hour
            }

            this.meridiem = period
            this.build()

            window.setTimeout(() => {
                this.scrollColumn('hour')
                this.scrollColumn('minute')
            }, 0)
        },

        /** Append the composed time to a multiple selection and keep the popover open. */
        add() {
            if (this.disabled || this.hour === null || this.minute === null) {
                return
            }

            const time = this.compose()

            if (time === null || this.isDisabled(this.hour, this.minute)) {
                return
            }

            if (!this.selectedValues.includes(time)) {
                this.selectedValues = [...this.selectedValues, time].sort(compareTime)
            }

            this.activeSelected = time
            this.commit()
            this.minute = null
            this.activeMinute = null
            this.preservedHour = null
            this.build()

            window.setTimeout(() => {
                this.scrollColumn('minute')
            }, 0)
        },

        /**
         * Remove a selected time and move focus to the next option or the list.
         */
        remove(value) {
            if (this.disabled) {
                return
            }

            const index = this.selectedValues.indexOf(value)

            this.selectedValues = this.selectedValues.filter((selected) => selected !== value)

            if (this.selectedValues.length === 0) {
                this.activeSelected = null
            } else {
                this.activeSelected = this.selectedValues[Math.min(Math.max(index, 0), this.selectedValues.length - 1)]
            }

            this.commit()

            window.setTimeout(() => {
                document.getElementById(config.selectedId)?.focus()
            }, 0)
        },

        syncSelectedActive() {
            if (this.selectedValues.length === 0) {
                this.activeSelected = null

                return
            }

            if (this.activeSelected === null || !this.selectedValues.includes(this.activeSelected)) {
                this.activeSelected = this.selectedValues[0]
            }
        },

        selectedOptionId(value) {
            return `${config.selectedId}-${value.replace(':', '')}`
        },

        activeSelectedId() {
            return this.activeSelected === null ? null : this.selectedOptionId(this.activeSelected)
        },

        onSelectedKeydown(event) {
            if (this.selectedValues.length === 0) {
                return
            }

            const current = this.activeSelected ?? this.selectedValues[0]
            const index = this.selectedValues.indexOf(current)
            let next: string | null = null

            if (event.key === 'ArrowDown') {
                next = this.selectedValues[(index + 1) % this.selectedValues.length]
            } else if (event.key === 'ArrowUp') {
                next = this.selectedValues[(index - 1 + this.selectedValues.length) % this.selectedValues.length]
            } else if (event.key === 'Home') {
                next = this.selectedValues[0]
            } else if (event.key === 'End') {
                next = this.selectedValues[this.selectedValues.length - 1]
            } else if (
                event.key === 'Enter' ||
                event.key === ' ' ||
                event.key === 'Delete' ||
                event.key === 'Backspace'
            ) {
                event.preventDefault()
                this.remove(current)

                return
            } else {
                return
            }

            event.preventDefault()
            this.activeSelected = next

            window.setTimeout(() => {
                if (next !== null) {
                    document.getElementById(this.selectedOptionId(next))?.scrollIntoView({ block: 'nearest' })
                }
            }, 0)
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

        onHourKeydown(event) {
            this.onColumnKeydown(event, 'hour')
        },

        onMinuteKeydown(event) {
            this.onColumnKeydown(event, 'minute')
        },

        onPopoverKeydown(event) {
            const target = popoverElement()

            if (target !== null) {
                trapTabFocus(target, event)
            }
        },

        canAdd() {
            return (
                this.multiple && this.hour !== null && this.minute !== null && !this.isDisabled(this.hour, this.minute)
            )
        },

        hasValue() {
            return this.multiple ? this.selectedValues.length > 0 : this.selected !== null
        },

        isSelected(value) {
            return this.selectedValues.includes(value)
        },

        activeHourId() {
            return this.hours.find((option) => option.value === this.activeHour)?.id ?? null
        },

        activeMinuteId() {
            return this.minutes.find((option) => option.value === this.activeMinute)?.id ?? null
        },

        placeholderText() {
            return config.placeholder ?? (this.multiple ? this.labels.placeholderMultiple : this.labels.placeholder)
        },

        formatSelected(value) {
            return formatTime(config.locale, value, config.format)
        },

        renderDisplay() {
            if (this.multiple) {
                if (this.selectedValues.length === 0) {
                    this.display = ''
                } else if (this.selectedValues.length === 1) {
                    this.display = formatTime(config.locale, this.selectedValues[0], config.format)
                } else {
                    this.display = this.labels.selectedCount.replace(':count', String(this.selectedValues.length))
                }

                return
            }

            this.display = this.selected === null ? '' : formatTime(config.locale, this.selected, config.format)
        },

        now: localNowKey(),

        resolveBounds() {
            this.now = localNowKey()
            minKey = config.min === 'now' ? this.now : toTime(config.min)
            maxKey = config.max === 'now' ? this.now : toTime(config.max)
        },

        initialDraft() {
            if (this.multiple) {
                return config.openTo === 'now' ? this.now : (toTime(config.openTo) ?? this.now)
            }

            return this.selected ?? (config.openTo === 'now' ? this.now : (toTime(config.openTo) ?? this.now))
        },

        /**
         * Seed the draft from a time.
         *
         * `preserve` keeps an off-step committed minute; otherwise it snaps down
         * to the interval so new selections stay on the configured grid.
         */
        setDraft(key, preserve = false) {
            const minutes = minutesOfDay(key ?? this.now) ?? 0
            const rawMinute = minutes % 60

            this.hour = Math.floor(minutes / 60)

            if (preserve && rawMinute % interval !== 0) {
                this.preservedHour = this.hour
                this.minute = rawMinute
            } else {
                this.preservedHour = null
                this.minute = rawMinute % interval === 0 ? rawMinute : Math.floor(rawMinute / interval) * interval
            }

            this.meridiem = this.hour >= 12 ? 'pm' : 'am'
        },

        changeHour(value) {
            if (this.minute !== null && this.minute % interval !== 0 && value !== this.preservedHour) {
                this.minute = null
                this.preservedHour = null
            }

            this.hour = value
        },

        build() {
            if (this.hour === null) {
                this.setDraft(this.initialDraft())
            }

            this.hours = this.hourOptions().map((option) => ({
                id: `${config.hoursId}-${option.value}`,
                value: option.value,
                label: option.label,
                disabled: !this.hourEnabled(option.value),
                selected: this.hour === option.value,
            }))

            const hour = this.hour

            this.minutes = this.minuteValues().map((value) => ({
                id: `${config.minutesId}-${value}`,
                value,
                label: String(value).padStart(2, '0'),
                disabled: hour === null || this.isDisabled(hour, value),
                selected: this.minute === value,
            }))

            this.activeHour = this.syncActive(this.hours, this.activeHour, this.hour)
            this.activeMinute = this.syncActive(this.minutes, this.activeMinute, this.minute)
        },

        hourOptions(): HourOption[] {
            if (!this.hour12) {
                return Array.from({ length: 24 }, (_, hour) => ({
                    value: hour,
                    label: String(hour).padStart(2, '0'),
                }))
            }

            const offset = this.meridiem === 'pm' ? 12 : 0

            return [12, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11].map((display) => ({
                value: (display % 12) + offset,
                label: String(display),
            }))
        },

        /** Interval minutes plus the preserved off-step minute for its own hour. */
        minuteCandidates(hour): number[] {
            const values: number[] = []

            for (let minute = 0; minute < 60; minute += interval) {
                values.push(minute)
            }

            if (
                this.minute !== null &&
                this.minute % interval !== 0 &&
                hour === this.preservedHour &&
                !values.includes(this.minute)
            ) {
                values.push(this.minute)
            }

            return values.sort((left, right) => left - right)
        },

        minuteValues(): number[] {
            return this.minuteCandidates(this.hour ?? 0)
        },

        hourEnabled(hour) {
            return this.minuteCandidates(hour).some((minute) => !this.isDisabled(hour, minute))
        },

        isDisabled(hour, minute) {
            const key = timeFromMinutes(hour * 60 + minute)

            if (minKey !== null && compareTime(key, minKey) < 0) {
                return true
            }

            if (maxKey !== null && compareTime(key, maxKey) > 0) {
                return true
            }

            return isTimeUnavailable(hour * 60 + minute, unavailable)
        },

        compose() {
            if (this.hour === null || this.minute === null) {
                return null
            }

            return timeFromMinutes(this.hour * 60 + this.minute)
        },

        syncActive(options, current, selected) {
            if (current !== null) {
                const option = options.find((candidate) => candidate.value === current)

                if (option !== undefined && !option.disabled) {
                    return current
                }
            }

            if (selected !== null) {
                const option = options.find((candidate) => candidate.value === selected)

                if (option !== undefined && !option.disabled) {
                    return selected
                }
            }

            return options.find((option) => !option.disabled)?.value ?? null
        },

        onColumnKeydown(event, column) {
            const options = column === 'hour' ? this.hours : this.minutes
            const active = column === 'hour' ? this.activeHour : this.activeMinute
            const enabled = options.filter((option) => !option.disabled)

            if (enabled.length === 0) {
                return
            }

            const choose = (value: number): void => {
                if (column === 'hour') {
                    this.selectHour(value)
                } else {
                    this.selectMinute(value)
                }
            }

            const move = (delta: number): number | null => {
                const index = enabled.findIndex((option) => option.value === active)

                if (index < 0) {
                    return enabled[delta > 0 ? 0 : enabled.length - 1].value
                }

                return enabled[(index + delta + enabled.length) % enabled.length].value
            }

            let next: number | null = null
            let edge: 'first' | 'last' | null = null

            if (event.key === 'ArrowDown') {
                next = move(1)
            } else if (event.key === 'ArrowUp') {
                next = move(-1)
            } else if (event.key === 'Home') {
                edge = 'first'
            } else if (event.key === 'End') {
                edge = 'last'
            } else if (event.key === 'PageDown') {
                next = move(5)
            } else if (event.key === 'PageUp') {
                next = move(-5)
            } else if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault()

                if (active !== null) {
                    choose(active)
                }

                return
            }

            if (edge !== null) {
                event.preventDefault()
                next = enabled[edge === 'first' ? 0 : enabled.length - 1].value
            }

            if (next === null) {
                return
            }

            event.preventDefault()

            if (column === 'hour') {
                this.activeHour = next
            } else {
                this.activeMinute = next
            }

            window.setTimeout(() => {
                this.scrollColumn(column)
            }, 0)
        },

        scrollColumn(column) {
            const listboxId = column === 'hour' ? config.hoursId : config.minutesId
            const listbox = document.getElementById(listboxId)
            const value = column === 'hour' ? this.activeHour : this.activeMinute

            if (listbox === null || value === null) {
                return
            }

            const option = document.getElementById(`${listboxId}-${value}`)

            if (option === null) {
                return
            }

            const box = listbox.getBoundingClientRect()
            const rect = option.getBoundingClientRect()

            if (rect.top < box.top) {
                listbox.scrollTop -= box.top - rect.top
            } else if (rect.bottom > box.bottom) {
                listbox.scrollTop += rect.bottom - box.bottom
            }
        },

        focusFirst() {
            window.setTimeout(() => {
                document.getElementById(config.hoursId)?.focus()
                this.scrollColumn('hour')
                this.scrollColumn('minute')
            }, 0)
        },

        dispatch(name, value) {
            dispatch(this, name, value)
        },
    }
}
