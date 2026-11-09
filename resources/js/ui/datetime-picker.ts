import { resolveLocale } from '@/locale'
import { readModel, watchModel, writeModel, type WireLike } from '@/util/binding'
import { formatShortDate, toDateKey } from '@/util/date'
import {
    combineDateTime,
    compareDateTime,
    datePart,
    isUnavailable,
    localNowDateTime,
    parseDateTime,
    parseUnavailable,
    timePart,
    type DateTimeUnavailable,
} from '@/util/datetime'
import { compareTime, formatTime, localNowKey, minutesOfDay, prefersHour12, timeFromMinutes } from '@/util/time'

import { createPopover, trapTabFocus, type PopoverController } from './popover'
import type { TimeFormat, TimeOption } from './time-picker'

type DateTimeMode = 'single' | 'range'
type Endpoint = 'start' | 'end'
type Column = 'hour' | 'minute'

/** Time-column state for one endpoint. */
interface TimeDraftState {
    hour: number | null
    minute: number | null
    meridiem: 'am' | 'pm'
    preservedHour: number | null
    hours: TimeOption[]
    minutes: TimeOption[]
    activeHour: number | null
    activeMinute: number | null
}

/** Translated datetime-picker strings. */
export interface DateTimePickerLabels {
    label: string
    placeholder: string
    placeholderRange: string
    start: string
    end: string
    at: string
    clear: string
    cancel: string
    select: string
    selectRange: string
    rangeInvalid: string
    addStart: string
    addEnd: string
    removeStart: string
    removeEnd: string
}

/** Blade-to-Alpine config for a datetime picker. */
export interface DateTimePickerConfig {
    mode: DateTimeMode
    value: unknown
    model: string | null
    live: boolean
    locale: string | null
    months: number
    timeFormat: TimeFormat
    interval: number
    min: string | null
    max: string | null
    minDate: string | null
    maxDate: string | null
    /** Markers that make `min`/`max` re-resolve on every open. */
    minSource: 'now' | 'today' | null
    maxSource: 'now' | 'today' | null
    openToDate: string | null
    openToTime: string | null
    /** Marker that makes `open-to` re-resolve on every open. */
    openToSource: 'now' | 'today' | null
    minTime: string | null
    maxTime: string | null
    unavailable: string[]
    defaultStartTime: string | null
    defaultEndTime: string | null
    placeholder: string | null
    withConfirmation: boolean
    clearable: boolean
    disabled: boolean
    triggerId: string
    popoverId: string
    formInputId: string | null
    formStartId: string | null
    formEndId: string | null
    labels: DateTimePickerLabels
}

/**
 * Alpine data for the datetime picker.
 *
 * Combines one calendar with one time panel. Single mode writes on a date change
 * and closes on a minute; range mode uses the same single draft, Add start/Add
 * end chips and footer commit as the standalone time picker. Earlier times are
 * not disabled in range mode because the end may fall on a later date.
 */
export interface DateTimePicker {
    mode: DateTimeMode
    disabled: boolean
    labels: DateTimePickerConfig['labels']
    display: string
    hour12: boolean
    nowTime: string
    startDate: string | null
    endDate: string | null
    draft: TimeDraftState
    rangeStart: string | null
    rangeEnd: string | null
    valueStartDate: string | null
    valueStartTime: string | null
    valueEndDate: string | null
    valueEndTime: string | null
    min: string | null
    max: string | null
    openToDate: string | null
    openToTime: string | null
    rangeError: string
    init: (el?: HTMLElement) => void
    destroy: () => void
    applyValue: (value: unknown) => void
    refreshNow: () => void
    syncDrafts: () => void
    syncCalendar: () => void
    isPopoverOpen: () => boolean
    openPopover: () => void
    closePopover: () => void
    confirm: () => void
    cancel: () => void
    clear: () => void
    commitSingle: () => void
    write: (value: unknown) => void
    canConfirm: () => boolean
    rangeInvalid: () => boolean
    updateRangeError: () => void
    onTriggerClick: () => void
    onTriggerFocus: () => void
    onTriggerKeydown: (event: KeyboardEvent) => void
    onCalendarChange: (event: Event) => void
    onPopoverKeydown: (event: KeyboardEvent) => void
    activeEndpoint: () => Endpoint
    rangeComplete: () => boolean
    addTime: () => void
    addLabel: () => string
    canAddTime: () => boolean
    canAddMore: () => boolean
    removeAdded: (endpoint: Endpoint) => void
    formatTimeValue: (time: string | null) => string
    after: (time: string) => string
    setTimeDraft: (time: string | null) => void
    dateFor: (endpoint: Endpoint) => string | null
    draftTime: () => string | null
    draftTimeValid: () => boolean
    firstValidTimeFor: (endpoint: Endpoint) => string | null
    revalidateRangeTimes: () => void
    initialTime: (endpoint: Endpoint) => string
    buildTimes: () => void
    hourOptions: (draft: TimeDraftState) => { value: number; label: string }[]
    minuteCandidates: (hour: number) => number[]
    hourEnabled: (hour: number) => boolean
    isTimeDisabled: (hour: number, minute: number) => boolean
    isTimeDisabledFor: (endpoint: Endpoint, hour: number, minute: number) => boolean
    changeHour: (value: number) => void
    selectHour: (value: number) => void
    selectMinute: (value: number) => void
    setMeridiem: (period: 'am' | 'pm') => void
    activeTimeId: (column: Column) => string | null
    timeListId: (column: Column) => string
    timeOptionId: (column: Column, value: number) => string
    onTimeKeydown: (event: KeyboardEvent, column: Column) => void
    scrollTimeColumn: (column: Column) => void
    sizeTimePanel: () => void
    syncActive: (options: TimeOption[], current: number | null, selected: number | null) => number | null
    renderDisplay: () => void
    placeholderText: () => string
    focusFirst: () => void
    dispatch: (name: string, value?: unknown) => void
}

/**
 * Create the Alpine data for a datetime picker.
 *
 * Writes `Y-m-d\TH:i` for single mode and `{ start, end }` for range mode. The
 * time panel height is synchronized with the calendar on desktop.
 */
export function datetimePickerData(config: DateTimePickerConfig, wire: WireLike | null): DateTimePicker {
    let rootEl: HTMLElement | null = null
    let popover: PopoverController | null = null
    let unwatch: (() => void) | null = null
    let timeObserver: ResizeObserver | null = null
    let resizeListener: (() => void) | null = null
    let unavailable: DateTimeUnavailable = parseUnavailable([])

    const interval = Math.min(60, Math.max(1, config.interval))

    const root = (): HTMLElement | null => rootEl
    const popoverElement = (): HTMLElement | null => document.getElementById(config.popoverId)
    const formInput = (): HTMLInputElement | null =>
        config.formInputId === null ? null : (document.getElementById(config.formInputId) as HTMLInputElement | null)

    const emptyDraft = (): TimeDraftState => ({
        hour: null,
        minute: null,
        meridiem: 'am',
        preservedHour: null,
        hours: [],
        minutes: [],
        activeHour: null,
        activeMinute: null,
    })

    const normalizeRange = (value: unknown): { start: string | null; end: string | null } => {
        if (Array.isArray(value)) {
            return { start: parseDateTime(value[0]), end: parseDateTime(value[1]) }
        }

        if (typeof value === 'object' && value !== null) {
            const range = value as { start?: unknown; end?: unknown }

            return { start: parseDateTime(range.start), end: parseDateTime(range.end) }
        }

        if (typeof value === 'string') {
            const [start, end] = value.split('/', 2)

            return { start: parseDateTime(start ?? null), end: parseDateTime(end ?? null) }
        }

        return { start: null, end: null }
    }

    const dispatch = (target: DateTimePicker, name: string, value?: unknown): void => {
        const detail = value === undefined ? {} : { value }

        root()?.dispatchEvent(new CustomEvent(name, { bubbles: true, detail }))
    }

    const locale = resolveLocale(config.locale)

    return {
        mode: config.mode,
        disabled: config.disabled,
        labels: config.labels,
        display: '',
        hour12: config.timeFormat === '12-hour' || (config.timeFormat === 'auto' && prefersHour12(locale)),
        nowTime: localNowKey(),
        startDate: null,
        endDate: null,
        draft: emptyDraft(),
        rangeStart: null,
        rangeEnd: null,
        valueStartDate: null,
        valueStartTime: null,
        valueEndDate: null,
        valueEndTime: null,
        min: config.min,
        max: config.max,
        openToDate: config.openToDate,
        openToTime: config.openToTime,
        rangeError: '',

        init(el) {
            // Alpine calls init() itself before x-init supplies the root element.
            if (el === undefined || rootEl !== null) {
                return
            }

            rootEl = el
            unavailable = parseUnavailable(config.unavailable)
            this.refreshNow()
            this.applyValue(readModel(wire, config.model, config.value))
            this.syncDrafts()

            const trigger = document.getElementById(config.triggerId)
            const target = popoverElement()

            if (trigger !== null && target !== null) {
                const calendar = target.querySelector<HTMLElement>('[data-ui-calendar]')

                if (calendar !== null && typeof ResizeObserver !== 'undefined') {
                    timeObserver = new ResizeObserver(() => this.sizeTimePanel())
                    timeObserver.observe(calendar)
                }

                resizeListener = () => this.sizeTimePanel()
                window.addEventListener('resize', resizeListener)

                popover = createPopover(trigger, target, {
                    focusTarget: trigger,
                    fullWidthOnTouch: true,
                    scrollLock: true,
                    onOpen: () => {
                        this.refreshNow()
                        this.syncDrafts()
                        this.syncCalendar()
                        this.focusFirst()
                        dispatch(this, 'open')
                    },
                    onClose: () => {
                        this.syncDrafts()
                        dispatch(this, 'close')
                    },
                })
            }

            unwatch = watchModel(wire, config.model, (value) => {
                this.applyValue(value)

                if (this.isPopoverOpen()) {
                    this.syncDrafts()
                    this.syncCalendar()
                }
            })
        },

        destroy() {
            unwatch?.()
            timeObserver?.disconnect()
            timeObserver = null

            if (resizeListener !== null) {
                window.removeEventListener('resize', resizeListener)
                resizeListener = null
            }

            popover?.destroy()
        },

        applyValue(value) {
            if (this.mode === 'range') {
                const range = normalizeRange(value)

                this.valueStartDate = range.start === null ? null : datePart(range.start)
                this.valueStartTime = range.start === null ? null : timePart(range.start)
                this.valueEndDate = range.end === null ? null : datePart(range.end)
                this.valueEndTime = range.end === null ? null : timePart(range.end)
            } else {
                const datetime = parseDateTime(value)

                this.valueStartDate = datetime === null ? null : datePart(datetime)
                this.valueStartTime = datetime === null ? null : timePart(datetime)
                this.valueEndDate = null
                this.valueEndTime = null
            }

            this.renderDisplay()
        },

        /** Re-resolve `now`/`today` bounds and open-to on every open. */
        refreshNow() {
            const now = localNowDateTime()
            const today = datePart(now)

            this.nowTime = timePart(now)
            this.min = config.minSource === 'now' ? now : config.minSource === 'today' ? `${today}T00:00` : config.min
            this.max = config.maxSource === 'now' ? now : config.maxSource === 'today' ? `${today}T23:59` : config.max

            if (config.openToSource === 'now') {
                this.openToDate = today
                this.openToTime = timePart(now)
            } else if (config.openToSource === 'today') {
                this.openToDate = today
                this.openToTime = null
            } else {
                this.openToDate = config.openToDate
                this.openToTime = config.openToTime
            }
        },

        syncDrafts() {
            this.startDate = this.valueStartDate
            this.endDate = this.valueEndDate

            if (this.mode === 'range') {
                this.rangeStart = this.valueStartTime
                this.rangeEnd = this.valueEndTime
                this.setTimeDraft(this.rangeStart ?? (this.startDate === null ? null : this.initialTime('start')))
            } else {
                this.rangeStart = null
                this.rangeEnd = null
                this.setTimeDraft(this.valueStartTime)
            }

            this.buildTimes()
            this.updateRangeError()
        },

        activeEndpoint(): Endpoint {
            if (this.mode !== 'range' || this.rangeStart === null || this.rangeEnd !== null) {
                return 'start'
            }

            return 'end'
        },

        rangeComplete(): boolean {
            return this.mode === 'range' && this.rangeStart !== null && this.rangeEnd !== null
        },

        syncCalendar() {
            const calendar = popoverElement()?.querySelector<HTMLElement>('[data-ui-calendar]')

            if (calendar === undefined || calendar === null || typeof globalThis.Alpine === 'undefined') {
                return
            }

            const data = globalThis.Alpine.$data(calendar) as
                | {
                      setValue: (value: unknown) => void
                      setBounds: (min: string | null, max: string | null) => void
                      setOpenTo: (key: string | null) => void
                      refreshToday: () => void
                  }
                | undefined

            data?.refreshToday()
            data?.setOpenTo(this.openToDate)
            data?.setBounds(
                this.min === null ? null : datePart(this.min),
                this.max === null ? null : datePart(this.max),
            )
            data?.setValue(this.mode === 'range' ? { start: this.startDate, end: this.endDate } : this.startDate)
        },

        isPopoverOpen() {
            return popover?.isOpen() ?? false
        },

        openPopover() {
            if (this.disabled || this.isPopoverOpen()) {
                return
            }

            this.refreshNow()
            this.syncDrafts()
            this.syncCalendar()
            popover?.open()
        },

        closePopover() {
            popover?.close()
        },

        confirm() {
            if (!this.canConfirm()) {
                return
            }

            if (this.mode === 'range' && this.rangeStart !== null && this.rangeEnd !== null) {
                const start = combineDateTime(this.startDate as string, this.rangeStart)
                const end = combineDateTime(this.endDate as string, this.rangeEnd)

                this.valueStartDate = this.startDate
                this.valueStartTime = this.rangeStart
                this.valueEndDate = this.endDate
                this.valueEndTime = this.rangeEnd
                this.write({ start, end })
            } else {
                this.commitSingle()
            }

            this.closePopover()
        },

        cancel() {
            this.closePopover()
        },

        clear() {
            if (this.disabled) {
                return
            }

            this.startDate = null
            this.endDate = null
            this.valueStartDate = null
            this.valueStartTime = null
            this.valueEndDate = null
            this.valueEndTime = null
            this.rangeStart = null
            this.rangeEnd = null
            this.setTimeDraft(null)
            this.write(this.mode === 'range' ? { start: null, end: null } : null)
            dispatch(this, 'clear')
            this.closePopover()
        },

        commitSingle() {
            const date = this.startDate
            const time = this.draftTime()

            if (date === null || time === null || !this.draftTimeValid()) {
                return
            }

            this.valueStartDate = date
            this.valueStartTime = time
            this.valueEndDate = null
            this.valueEndTime = null
            this.write(combineDateTime(date, time))
        },

        write(value) {
            if (config.model !== null) {
                writeModel(wire, config.model, config.live, value, null)
            } else if (this.mode === 'single') {
                writeModel(wire, null, config.live, value, formInput())
            } else {
                setRangeInputs(config.formStartId, config.formEndId, value as { start?: string; end?: string } | null)
            }

            this.renderDisplay()
            this.updateRangeError()
            dispatch(this, 'change', value)
        },

        canConfirm() {
            if (this.mode === 'range') {
                return (
                    this.startDate !== null &&
                    this.endDate !== null &&
                    this.rangeStart !== null &&
                    this.rangeEnd !== null &&
                    !this.rangeInvalid()
                )
            }

            return this.startDate !== null && this.draftTimeValid()
        },

        rangeInvalid() {
            if (this.mode !== 'range' || this.rangeStart === null || this.rangeEnd === null) {
                return false
            }

            if (this.startDate === null || this.endDate === null) {
                return false
            }

            if (this.startDate !== this.endDate) {
                return false
            }

            return (
                compareDateTime(
                    combineDateTime(this.endDate, this.rangeEnd),
                    combineDateTime(this.startDate, this.rangeStart),
                ) <= 0
            )
        },

        updateRangeError() {
            this.rangeError = this.rangeInvalid() ? this.labels.rangeInvalid : ''
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
                const value = detail.value
                const range =
                    typeof value === 'object' && value !== null
                        ? (value as { start?: unknown; end?: unknown })
                        : { start: null, end: null }
                const start = toDateKey(range.start)
                const end = toDateKey(range.end)

                this.startDate = start
                this.endDate = end

                if (start === null) {
                    this.rangeStart = null
                    this.rangeEnd = null
                    this.setTimeDraft(null)
                } else {
                    this.revalidateRangeTimes()

                    if (this.activeEndpoint() === 'start' && this.rangeStart === null) {
                        this.setTimeDraft(this.initialTime('start'))
                    } else if (this.activeEndpoint() === 'end' && this.rangeEnd === null) {
                        this.setTimeDraft(
                            this.rangeStart === null ? this.initialTime('end') : this.after(this.rangeStart),
                        )
                    } else {
                        this.setTimeDraft(this.rangeStart)
                    }
                }

                this.buildTimes()
                this.updateRangeError()

                return
            }

            const date = typeof detail.value === 'string' ? detail.value : null

            this.startDate = date

            if (date === null) {
                this.setTimeDraft(null)
                this.buildTimes()

                return
            }

            if (this.draftTime() === null) {
                this.setTimeDraft(this.initialTime('start'))
            }

            if (!this.draftTimeValid()) {
                this.setTimeDraft(this.firstValidTimeFor('start'))
            }

            this.buildTimes()

            if (!config.withConfirmation) {
                this.commitSingle()
            }
        },

        onPopoverKeydown(event) {
            const target = popoverElement()

            if (target !== null) {
                trapTabFocus(target, event)
            }
        },

        setTimeDraft(time) {
            const draft = this.draft

            if (time === null) {
                draft.hour = null
                draft.minute = null
                draft.meridiem = 'am'
                draft.preservedHour = null

                return
            }

            const minutes = minutesOfDay(time) ?? 0
            const rawMinute = minutes % 60

            draft.hour = Math.floor(minutes / 60)
            draft.preservedHour = rawMinute % interval === 0 ? null : draft.hour
            draft.minute = rawMinute
            draft.meridiem = draft.hour >= 12 ? 'pm' : 'am'
        },

        dateFor(endpoint) {
            return endpoint === 'start' ? this.startDate : this.endDate
        },

        draftTime() {
            const draft = this.draft

            if (draft.hour === null || draft.minute === null) {
                return null
            }

            return timeFromMinutes(draft.hour * 60 + draft.minute)
        },

        draftTimeValid() {
            const draft = this.draft

            return (
                this.dateFor(this.activeEndpoint()) !== null &&
                draft.hour !== null &&
                draft.minute !== null &&
                !this.isTimeDisabled(draft.hour, draft.minute)
            )
        },

        initialTime(endpoint) {
            const start = config.defaultStartTime ?? this.openToTime ?? this.nowTime

            if (endpoint === 'start') {
                return start
            }

            return config.defaultEndTime ?? this.openToTime ?? start
        },

        /** First interval time that satisfies the endpoint constraints, if any. */
        firstValidTimeFor(endpoint) {
            for (let hour = 0; hour < 24; hour++) {
                for (let minute = 0; minute < 60; minute += interval) {
                    if (!this.isTimeDisabledFor(endpoint, hour, minute)) {
                        return timeFromMinutes(hour * 60 + minute)
                    }
                }
            }

            return null
        },

        /** Replace an added time that is no longer available on the selected dates. */
        revalidateRangeTimes() {
            if (this.mode !== 'range') {
                return
            }

            for (const endpoint of ['start', 'end'] as Endpoint[]) {
                const time = endpoint === 'start' ? this.rangeStart : this.rangeEnd

                if (time === null || this.dateFor(endpoint) === null) {
                    continue
                }

                const minutes = minutesOfDay(time) ?? 0
                const hour = Math.floor(minutes / 60)
                const minute = minutes % 60

                if (!this.isTimeDisabledFor(endpoint, hour, minute)) {
                    continue
                }

                const replacement = this.firstValidTimeFor(endpoint)

                if (endpoint === 'start') {
                    this.rangeStart = replacement
                } else {
                    this.rangeEnd = replacement
                }
            }
        },

        buildTimes() {
            const draft = this.draft
            const locked = this.rangeComplete()

            draft.hours = this.hourOptions(draft).map((option) => ({
                id: this.timeOptionId('hour', option.value),
                value: option.value,
                label: option.label,
                disabled: locked || !this.hourEnabled(option.value),
                selected: draft.hour === option.value,
            }))

            const hour = draft.hour

            draft.minutes = this.minuteCandidates(hour ?? 0).map((value) => ({
                id: this.timeOptionId('minute', value),
                value,
                label: String(value).padStart(2, '0'),
                disabled: locked || hour === null || this.isTimeDisabled(hour, value),
                selected: draft.minute === value,
            }))

            draft.activeHour = this.syncActive(draft.hours, draft.activeHour, draft.hour)
            draft.activeMinute = this.syncActive(draft.minutes, draft.activeMinute, draft.minute)
        },

        hourOptions(draft) {
            if (!this.hour12) {
                return Array.from({ length: 24 }, (_, hour) => ({
                    value: hour,
                    label: String(hour).padStart(2, '0'),
                }))
            }

            const offset = draft.meridiem === 'pm' ? 12 : 0

            return [12, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11].map((display) => ({
                value: (display % 12) + offset,
                label: String(display),
            }))
        },

        minuteCandidates(hour) {
            const values: number[] = []

            for (let minute = 0; minute < 60; minute += interval) {
                values.push(minute)
            }

            const draft = this.draft

            if (
                draft.minute !== null &&
                draft.minute % interval !== 0 &&
                hour === draft.preservedHour &&
                !values.includes(draft.minute)
            ) {
                values.push(draft.minute)
            }

            return values.sort((left, right) => left - right)
        },

        hourEnabled(hour) {
            if (this.dateFor(this.activeEndpoint()) === null) {
                return false
            }

            return this.minuteCandidates(hour).some((minute) => !this.isTimeDisabled(hour, minute))
        },

        isTimeDisabled(hour, minute) {
            return this.isTimeDisabledFor(this.activeEndpoint(), hour, minute)
        },

        /** Check bounds, daily windows and unavailable entries for one endpoint. */
        isTimeDisabledFor(endpoint, hour, minute) {
            const date = this.dateFor(endpoint)

            if (date === null) {
                return true
            }

            const time = timeFromMinutes(hour * 60 + minute)
            const candidate = combineDateTime(date, time)

            if (this.min !== null && compareDateTime(candidate, this.min) < 0) {
                return true
            }

            if (this.max !== null && compareDateTime(candidate, this.max) > 0) {
                return true
            }

            if (config.minTime !== null && compareTime(time, config.minTime) < 0) {
                return true
            }

            if (config.maxTime !== null && compareTime(time, config.maxTime) > 0) {
                return true
            }

            return isUnavailable(date, time, unavailable)
        },

        changeHour(value) {
            const draft = this.draft

            if (draft.minute !== null && draft.minute % interval !== 0 && value !== draft.preservedHour) {
                draft.minute = null
                draft.preservedHour = null
            }

            draft.hour = value
        },

        selectHour(value) {
            const draft = this.draft
            const option = draft.hours.find((candidate) => candidate.value === value)

            if (option === undefined || option.disabled) {
                return
            }

            this.changeHour(value)
            draft.activeHour = value
            this.buildTimes()

            window.setTimeout(() => {
                this.scrollTimeColumn('minute')
            }, 0)
        },

        selectMinute(value) {
            const draft = this.draft
            const option = draft.minutes.find((candidate) => candidate.value === value)

            if (option === undefined || option.disabled) {
                return
            }

            draft.minute = value
            draft.activeMinute = value

            if (this.mode === 'single') {
                this.buildTimes()

                if (config.withConfirmation) {
                    return
                }

                this.commitSingle()
                this.closePopover()

                return
            }

            this.buildTimes()
            this.updateRangeError()
        },

        setMeridiem(period) {
            const draft = this.draft

            if (draft.meridiem === period) {
                this.buildTimes()

                return
            }

            if (draft.hour !== null) {
                const display = draft.hour % 12 || 12
                this.changeHour((display % 12) + (period === 'pm' ? 12 : 0))
                draft.activeHour = draft.hour
            }

            draft.meridiem = period
            this.buildTimes()
            this.updateRangeError()

            window.setTimeout(() => {
                this.scrollTimeColumn('hour')
                this.scrollTimeColumn('minute')
            }, 0)
        },

        addTime() {
            if (this.disabled || this.mode !== 'range' || this.rangeComplete()) {
                return
            }

            const time = this.draftTime()

            if (time === null || !this.draftTimeValid()) {
                return
            }

            if (this.rangeStart === null) {
                this.rangeStart = time
                this.setTimeDraft(this.endDate === null ? null : this.initialTime('end'))
            } else {
                this.rangeEnd = time
                this.setTimeDraft(this.rangeStart)
            }

            this.buildTimes()
            this.updateRangeError()
        },

        addLabel() {
            return this.rangeStart === null ? this.labels.addStart : this.labels.addEnd
        },

        canAddTime() {
            return this.mode === 'range' && !this.rangeComplete() && this.draftTimeValid()
        },

        canAddMore() {
            return this.mode === 'range' && !this.rangeComplete()
        },

        removeAdded(endpoint) {
            if (this.disabled || this.mode !== 'range') {
                return
            }

            if (endpoint === 'start') {
                this.rangeStart = null
                this.rangeEnd = null
                this.setTimeDraft(this.startDate === null ? null : this.initialTime('start'))
            } else {
                this.rangeEnd = null
                this.setTimeDraft(
                    this.rangeStart === null
                        ? this.endDate === null
                            ? null
                            : this.initialTime('end')
                        : this.after(this.rangeStart),
                )
            }

            this.buildTimes()
            this.updateRangeError()
        },

        formatTimeValue(time) {
            return time === null ? '' : formatTime(locale, time, config.timeFormat)
        },

        /** First interval time after a start, clamped to the end of the day. */
        after(time) {
            const minutes = minutesOfDay(time) ?? 0

            return timeFromMinutes(Math.min(minutes + interval, 23 * 60 + 59))
        },

        activeTimeId(column) {
            const value = column === 'hour' ? this.draft.activeHour : this.draft.activeMinute

            return value === null ? null : this.timeOptionId(column, value)
        },

        timeListId(column) {
            return `${config.triggerId}-time-${column}s`
        },

        timeOptionId(column, value) {
            return `${this.timeListId(column)}-${value}`
        },

        onTimeKeydown(event, column) {
            const draft = this.draft
            const options = column === 'hour' ? draft.hours : draft.minutes
            const active = column === 'hour' ? draft.activeHour : draft.activeMinute
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
                draft.activeHour = next
            } else {
                draft.activeMinute = next
            }

            window.setTimeout(() => {
                this.scrollTimeColumn(column)
            }, 0)
        },

        /** Scroll one time column so its active option is visible. */
        scrollTimeColumn(column) {
            const listbox = document.getElementById(this.timeListId(column))
            const value = column === 'hour' ? this.draft.activeHour : this.draft.activeMinute

            if (listbox === null || value === null) {
                return
            }

            const option = document.getElementById(this.timeOptionId(column, value))

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

        renderDisplay() {
            if (this.mode === 'range') {
                if (this.valueStartDate === null || this.valueStartTime === null) {
                    this.display = ''

                    return
                }

                const start = formatShortDate(locale, this.valueStartDate)
                const startTime = formatTime(locale, this.valueStartTime, config.timeFormat)

                if (this.valueEndDate === null || this.valueEndTime === null) {
                    this.display = `${start}, ${startTime}`

                    return
                }

                const end = formatShortDate(locale, this.valueEndDate)
                const endTime = formatTime(locale, this.valueEndTime, config.timeFormat)

                this.display =
                    this.valueStartDate === this.valueEndDate
                        ? `${start}, ${startTime} – ${endTime}`
                        : `${start} ${startTime} – ${end} ${endTime}`

                return
            }

            if (this.valueStartDate === null || this.valueStartTime === null) {
                this.display = ''

                return
            }

            this.display = `${formatShortDate(locale, this.valueStartDate)} ${this.labels.at} ${formatTime(locale, this.valueStartTime, config.timeFormat)}`
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
                this.sizeTimePanel()
                this.scrollTimeColumn('hour')
                this.scrollTimeColumn('minute')
            }, 0)
        },

        /**
         * Match the time panel height to the calendar on desktop.
         *
         * The panel is left auto-sized on mobile, where the columns stack.
         */
        sizeTimePanel() {
            const target = popoverElement()
            const calendar = target?.querySelector<HTMLElement>('[data-ui-calendar]')
            const panel = target?.querySelector<HTMLElement>('[data-ui-time-panel]')

            if (calendar === undefined || calendar === null || panel === undefined || panel === null) {
                return
            }

            panel.style.height =
                this.mode === 'range'
                    ? ''
                    : window.matchMedia('(min-width: 1024px)').matches
                      ? `${calendar.offsetHeight}px`
                      : ''
        },

        dispatch(name, value) {
            dispatch(this, name, value)
        },
    }
}

function setRangeInputs(
    startId: string | null,
    endId: string | null,
    value: { start?: string; end?: string } | null,
): void {
    for (const [id, entry] of [
        [startId, value?.start],
        [endId, value?.end],
    ] as const) {
        if (id === null) {
            continue
        }

        const input = document.getElementById(id) as HTMLInputElement | null

        if (input === null) {
            continue
        }

        input.value = entry ?? ''
        input.dispatchEvent(new Event('input', { bubbles: true }))
        input.dispatchEvent(new Event('change', { bubbles: true }))
    }
}
