import { readModel, watchModel, writeModel } from './binding'
import { formatShortDate, toDateKey } from './date'
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
} from './datetime'
import { createPopover, trapTabFocus, type PopoverController } from './popover'
import { compareTime, formatTime, localNowKey, minutesOfDay, prefersHour12, timeFromMinutes } from './time'
import type { DateTimePickerConfig, TimeOption, WireLike } from './types'

type DateTimeMode = 'single' | 'range'
type Endpoint = 'start' | 'end'
type Column = 'hour' | 'minute'

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

export interface DateTimePickerData {
    mode: DateTimeMode
    open: boolean
    disabled: boolean
    labels: DateTimePickerConfig['labels']
    display: string
    hour12: boolean
    nowTime: string
    startDate: string | null
    endDate: string | null
    start: TimeDraftState
    end: TimeDraftState
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
    setTimeDraft: (endpoint: Endpoint, time: string | null) => void
    dateFor: (endpoint: Endpoint) => string | null
    draftFor: (endpoint: Endpoint) => TimeDraftState
    draftTimeFor: (endpoint: Endpoint) => string | null
    draftTimeValid: (endpoint: Endpoint) => boolean
    firstValidTime: (endpoint: Endpoint) => string | null
    revalidateTime: (endpoint: Endpoint) => void
    initialTime: (endpoint: Endpoint) => string
    buildTimes: (endpoint: Endpoint) => void
    hourOptions: (draft: TimeDraftState) => { value: number; label: string }[]
    minuteCandidates: (endpoint: Endpoint, hour: number) => number[]
    hourEnabled: (endpoint: Endpoint, hour: number) => boolean
    isTimeDisabled: (endpoint: Endpoint, hour: number, minute: number) => boolean
    changeHour: (endpoint: Endpoint, value: number) => void
    selectHour: (endpoint: Endpoint, value: number) => void
    selectMinute: (endpoint: Endpoint, value: number) => void
    setMeridiem: (endpoint: Endpoint, period: 'am' | 'pm') => void
    activeTimeId: (endpoint: Endpoint, column: Column) => string
    timeListId: (endpoint: Endpoint, column: Column) => string
    timeOptionId: (endpoint: Endpoint, column: Column, value: number) => string
    onTimeKeydown: (event: KeyboardEvent, endpoint: Endpoint, column: Column) => void
    scrollTimeColumn: (endpoint: Endpoint, column: Column) => void
    sizeTimePanel: () => void
    syncActive: (options: TimeOption[], current: number | null, selected: number | null) => number | null
    renderDisplay: () => void
    placeholderText: () => string
    focusFirst: () => void
    dispatch: (name: string, value?: unknown) => void
}

export function datetimePickerData(config: DateTimePickerConfig, wire: WireLike | null): DateTimePickerData {
    let rootEl: HTMLElement | null = null
    let popover: PopoverController | null = null
    let unwatch: (() => void) | null = null
    let suppressFocus = false
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

    const dispatch = (target: DateTimePickerData, name: string, value?: unknown): void => {
        const detail = value === undefined ? {} : { value }

        root()?.dispatchEvent(new CustomEvent(name, { bubbles: true, detail }))
    }

    return {
        mode: config.mode,
        open: false,
        disabled: config.disabled,
        labels: config.labels,
        display: '',
        hour12: config.timeFormat === '12-hour' || (config.timeFormat === 'auto' && prefersHour12(config.locale)),
        nowTime: localNowKey(),
        startDate: null,
        endDate: null,
        start: emptyDraft(),
        end: emptyDraft(),
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
            rootEl = el ?? null
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
                    onOpen: () => {
                        this.open = true
                        this.refreshNow()
                        this.syncDrafts()
                        this.syncCalendar()
                        this.focusFirst()
                        dispatch(this, 'open')
                    },
                    onClose: () => {
                        suppressFocus = true
                        this.open = false
                        this.syncDrafts()
                        dispatch(this, 'close')

                        setTimeout(() => {
                            suppressFocus = false
                        }, 0)
                    },
                })
            }

            unwatch = watchModel(wire, config.model, (value) => {
                this.applyValue(value)

                if (this.open) {
                    this.syncDrafts()
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
            this.setTimeDraft('start', this.valueStartTime)
            this.setTimeDraft('end', this.valueEndTime)
            this.buildTimes('start')
            this.buildTimes('end')
            this.updateRangeError()
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

        openPopover() {
            if (this.disabled || this.open) {
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

            if (this.mode === 'range') {
                const start = combineDateTime(this.startDate as string, this.draftTimeFor('start') as string)
                const end = combineDateTime(this.endDate as string, this.draftTimeFor('end') as string)

                this.valueStartDate = this.startDate
                this.valueStartTime = this.draftTimeFor('start')
                this.valueEndDate = this.endDate
                this.valueEndTime = this.draftTimeFor('end')
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
            this.setTimeDraft('start', null)
            this.setTimeDraft('end', null)
            this.write(null)
            dispatch(this, 'clear')
            this.closePopover()
        },

        commitSingle() {
            const date = this.startDate
            const time = this.draftTimeFor('start')

            if (date === null || time === null || !this.draftTimeValid('start')) {
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
                    this.draftTimeValid('start') &&
                    this.draftTimeValid('end') &&
                    !this.rangeInvalid()
                )
            }

            return this.startDate !== null && this.draftTimeValid('start')
        },

        rangeInvalid() {
            if (this.mode !== 'range' || this.startDate === null || this.endDate === null) {
                return false
            }

            const startTime = this.draftTimeFor('start')
            const endTime = this.draftTimeFor('end')

            if (startTime === null || endTime === null) {
                return false
            }

            return (
                compareDateTime(combineDateTime(this.endDate, endTime), combineDateTime(this.startDate, startTime)) <= 0
            )
        },

        updateRangeError() {
            this.rangeError = this.rangeInvalid() ? this.labels.rangeInvalid : ''
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
                    this.setTimeDraft('start', null)
                } else if (this.draftTimeFor('start') === null) {
                    this.setTimeDraft('start', this.initialTime('start'))
                }

                if (end === null) {
                    this.setTimeDraft('end', null)
                } else if (this.draftTimeFor('end') === null) {
                    this.setTimeDraft('end', this.initialTime('end'))
                }

                this.revalidateTime('start')
                this.revalidateTime('end')
                this.buildTimes('start')
                this.buildTimes('end')
                this.updateRangeError()

                return
            }

            const date = typeof detail.value === 'string' ? detail.value : null

            this.startDate = date

            if (date === null) {
                this.setTimeDraft('start', null)
                this.buildTimes('start')

                return
            }

            if (this.draftTimeFor('start') === null) {
                this.setTimeDraft('start', this.initialTime('start'))
            }

            this.revalidateTime('start')
            this.buildTimes('start')
            this.commitSingle()
        },

        onPopoverKeydown(event) {
            const target = popoverElement()

            if (target !== null) {
                trapTabFocus(target, event)
            }
        },

        setTimeDraft(endpoint, time) {
            const draft = this.draftFor(endpoint)

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

            if (rawMinute % interval !== 0) {
                draft.preservedHour = draft.hour
                draft.minute = rawMinute
            } else {
                draft.preservedHour = null
                draft.minute = rawMinute
            }

            draft.meridiem = draft.hour >= 12 ? 'pm' : 'am'
        },

        dateFor(endpoint) {
            return endpoint === 'start' ? this.startDate : this.endDate
        },

        draftFor(endpoint) {
            return endpoint === 'start' ? this.start : this.end
        },

        draftTimeFor(endpoint) {
            const draft = this.draftFor(endpoint)

            if (draft.hour === null || draft.minute === null) {
                return null
            }

            return timeFromMinutes(draft.hour * 60 + draft.minute)
        },

        initialTime(endpoint) {
            const start = config.defaultStartTime ?? this.openToTime ?? this.nowTime

            if (endpoint === 'start') {
                return start
            }

            return config.defaultEndTime ?? this.openToTime ?? start
        },

        draftTimeValid(endpoint) {
            const draft = this.draftFor(endpoint)

            return (
                this.dateFor(endpoint) !== null &&
                draft.hour !== null &&
                draft.minute !== null &&
                !this.isTimeDisabled(endpoint, draft.hour, draft.minute)
            )
        },

        firstValidTime(endpoint) {
            for (let hour = 0; hour < 24; hour++) {
                for (let minute = 0; minute < 60; minute += interval) {
                    if (!this.isTimeDisabled(endpoint, hour, minute)) {
                        return timeFromMinutes(hour * 60 + minute)
                    }
                }
            }

            return null
        },

        revalidateTime(endpoint) {
            if (this.dateFor(endpoint) === null) {
                this.setTimeDraft(endpoint, null)

                return
            }

            if (this.draftTimeValid(endpoint)) {
                return
            }

            this.setTimeDraft(endpoint, this.firstValidTime(endpoint))
        },

        buildTimes(endpoint) {
            const draft = this.draftFor(endpoint)

            draft.hours = this.hourOptions(draft).map((option) => ({
                id: this.timeOptionId(endpoint, 'hour', option.value),
                value: option.value,
                label: option.label,
                disabled: !this.hourEnabled(endpoint, option.value),
                selected: draft.hour === option.value,
            }))

            const hour = draft.hour

            draft.minutes = this.minuteCandidates(endpoint, hour ?? 0).map((value) => ({
                id: this.timeOptionId(endpoint, 'minute', value),
                value,
                label: String(value).padStart(2, '0'),
                disabled: hour === null || this.isTimeDisabled(endpoint, hour, value),
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

        minuteCandidates(endpoint, hour) {
            const values: number[] = []

            for (let minute = 0; minute < 60; minute += interval) {
                values.push(minute)
            }

            const draft = this.draftFor(endpoint)

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

        hourEnabled(endpoint, hour) {
            if (this.dateFor(endpoint) === null) {
                return false
            }

            return this.minuteCandidates(endpoint, hour).some((minute) => !this.isTimeDisabled(endpoint, hour, minute))
        },

        isTimeDisabled(endpoint, hour, minute) {
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

            if (isUnavailable(date, time, unavailable)) {
                return true
            }

            if (this.mode === 'range') {
                const startDate = this.startDate
                const endDate = this.endDate
                const startTime = this.draftTimeFor('start')
                const endTime = this.draftTimeFor('end')

                if (startDate !== null && startTime !== null && endDate !== null && endTime !== null) {
                    const start = combineDateTime(startDate, startTime)
                    const end = combineDateTime(endDate, endTime)

                    if (endpoint === 'end' && compareDateTime(candidate, start) <= 0) {
                        return true
                    }

                    if (endpoint === 'start' && compareDateTime(candidate, end) >= 0) {
                        return true
                    }
                }
            }

            return false
        },

        changeHour(endpoint, value) {
            const draft = this.draftFor(endpoint)

            if (draft.minute !== null && draft.minute % interval !== 0 && value !== draft.preservedHour) {
                draft.minute = null
                draft.preservedHour = null
            }

            draft.hour = value
        },

        selectHour(endpoint, value) {
            const draft = this.draftFor(endpoint)
            const option = draft.hours.find((candidate) => candidate.value === value)

            if (option === undefined || option.disabled) {
                return
            }

            this.changeHour(endpoint, value)
            draft.activeHour = value
            this.buildTimes(endpoint)

            if (this.mode === 'range') {
                const other = endpoint === 'start' ? 'end' : 'start'

                this.revalidateTime(other)
                this.buildTimes(other)
                this.updateRangeError()
            }

            window.setTimeout(() => {
                this.scrollTimeColumn(endpoint, 'minute')
            }, 0)
        },

        selectMinute(endpoint, value) {
            const draft = this.draftFor(endpoint)
            const option = draft.minutes.find((candidate) => candidate.value === value)

            if (option === undefined || option.disabled) {
                return
            }

            draft.minute = value
            draft.activeMinute = value

            if (this.mode === 'single') {
                this.buildTimes('start')

                if (config.withConfirmation) {
                    return
                }

                this.commitSingle()
                this.closePopover()

                return
            }

            const other = endpoint === 'start' ? 'end' : 'start'

            this.revalidateTime(other)
            this.buildTimes(endpoint)
            this.buildTimes(other)
            this.updateRangeError()
        },

        setMeridiem(endpoint, period) {
            const draft = this.draftFor(endpoint)

            if (draft.meridiem === period) {
                this.buildTimes(endpoint)

                return
            }

            if (draft.hour !== null) {
                const display = draft.hour % 12 || 12
                this.changeHour(endpoint, (display % 12) + (period === 'pm' ? 12 : 0))
                draft.activeHour = draft.hour
            }

            draft.meridiem = period
            this.buildTimes(endpoint)

            if (this.mode === 'range') {
                const other = endpoint === 'start' ? 'end' : 'start'

                this.revalidateTime(other)
                this.buildTimes(other)
                this.updateRangeError()
            }

            window.setTimeout(() => {
                this.scrollTimeColumn(endpoint, 'hour')
                this.scrollTimeColumn(endpoint, 'minute')
            }, 0)
        },

        activeTimeId(endpoint, column) {
            const draft = this.draftFor(endpoint)
            const value = column === 'hour' ? draft.activeHour : draft.activeMinute

            return value === null ? '' : this.timeOptionId(endpoint, column, value)
        },

        timeListId(endpoint, column) {
            return `${config.triggerId}-${endpoint}-${column}s`
        },

        timeOptionId(endpoint, column, value) {
            return `${this.timeListId(endpoint, column)}-${value}`
        },

        onTimeKeydown(event, endpoint, column) {
            const draft = this.draftFor(endpoint)
            const options = column === 'hour' ? draft.hours : draft.minutes
            const active = column === 'hour' ? draft.activeHour : draft.activeMinute
            const enabled = options.filter((option) => !option.disabled)

            if (enabled.length === 0) {
                return
            }

            const choose = (value: number): void => {
                if (column === 'hour') {
                    this.selectHour(endpoint, value)
                } else {
                    this.selectMinute(endpoint, value)
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
                this.scrollTimeColumn(endpoint, column)
            }, 0)
        },

        scrollTimeColumn(endpoint, column) {
            const listbox = document.getElementById(this.timeListId(endpoint, column))
            const draft = this.draftFor(endpoint)
            const value = column === 'hour' ? draft.activeHour : draft.activeMinute

            if (listbox === null || value === null) {
                return
            }

            const option = document.getElementById(this.timeOptionId(endpoint, column, value))

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

                const start = formatShortDate(config.locale, this.valueStartDate)
                const startTime = formatTime(config.locale, this.valueStartTime, config.timeFormat)

                if (this.valueEndDate === null || this.valueEndTime === null) {
                    this.display = `${start}, ${startTime}`

                    return
                }

                const end = formatShortDate(config.locale, this.valueEndDate)
                const endTime = formatTime(config.locale, this.valueEndTime, config.timeFormat)

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

            this.display = `${formatShortDate(config.locale, this.valueStartDate)} ${this.labels.at} ${formatTime(config.locale, this.valueStartTime, config.timeFormat)}`
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
                this.scrollTimeColumn('start', 'hour')
                this.scrollTimeColumn('start', 'minute')

                if (this.mode === 'range') {
                    this.scrollTimeColumn('end', 'hour')
                    this.scrollTimeColumn('end', 'minute')
                }
            }, 0)
        },

        sizeTimePanel() {
            const target = popoverElement()
            const calendar = target?.querySelector<HTMLElement>('[data-ui-calendar]')
            const panel = target?.querySelector<HTMLElement>('[data-ui-time-panel]')

            if (calendar === undefined || calendar === null || panel === undefined || panel === null) {
                return
            }

            panel.style.height = window.matchMedia('(min-width: 1024px)').matches ? `${calendar.offsetHeight}px` : ''
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
