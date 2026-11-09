import { resolveLocale } from '@/locale'
import { readModel, watchModel, writeModel, type WireLike } from '@/util/binding'
import {
    addDays,
    addMonthsClamped,
    compareKeys,
    dateKey,
    daysBetweenInclusive,
    endOfWeek,
    firstDayOfWeek,
    formatDayLabel,
    formatMonthLabel,
    formatMonthName,
    formatWeekdayLabels,
    isValidKey,
    localTodayKey,
    monthWeeks,
    parseKey,
    shiftMonth,
    startOfWeek,
    toDateKey,
    utcDate,
} from '@/util/date'

export type CalendarMode = 'single' | 'multiple' | 'range'

/** Translated calendar strings. */
export interface CalendarLabels {
    today: string
    previousMonth: string
    nextMonth: string
    selected: string
    unavailable: string
    rangeTooShort: string
    rangeTooLong: string
    rangeUnavailable: string
}

/** Blade-to-Alpine config for a calendar. */
export interface CalendarConfig {
    mode: CalendarMode
    yearSelect: boolean
    /** When true, selection updates the grid but does not write `model`. */
    draft: boolean
    value: unknown
    model: string | null
    live: boolean
    locale: string | null
    startDay: number | null
    months: number
    fixedWeeks: boolean
    min: string | null
    max: string | null
    unavailable: string[]
    minRange: number | null
    maxRange: number | null
    openTo: string | null
    navigation: boolean
    static: boolean
    withToday: boolean
    rootId: string
    cellClass: string
    formInputId: string | null
    labels: CalendarLabels
}

/** Rendered state for one day cell. */
export interface CalendarCell {
    id: string
    key: string
    day: number
    label: string
    outside: boolean
    today: boolean
    disabled: boolean
    unavailable: boolean
    selected: boolean
    /** Inside the committed range or the hover/focus preview. */
    inRange: boolean
    rangeStart: boolean
    rangeEnd: boolean
    active: boolean
    tabIndex: number
}

/** One rendered month in a one- or two-month calendar. */
export interface CalendarMonth {
    key: string
    year: number
    month: number
    label: string
    weekdays: string[]
    weeks: CalendarCell[][]
}

/**
 * Alpine data for the dependency-free Gregorian calendar.
 *
 * Date values are `Y-m-d` keys. The calendar owns single/multiple/range state and
 * dispatches a bubbling `change` event with the selected value. In `draft` mode it
 * still updates visually but does not write the model, so pickers can manage
 * their own commit/cancel lifecycle.
 */
export interface Calendar {
    mode: CalendarMode
    /** When true, `commit()` skips the model write but still dispatches `change`. */
    draft: boolean
    locale: string
    startDay: number
    months: number
    fixedWeeks: boolean
    navigation: boolean
    static: boolean
    withToday: boolean
    labels: CalendarConfig['labels']
    cellClass: string
    today: string
    viewYear: number
    viewMonth: number
    monthsData: CalendarMonth[]
    heading: string
    monthHeading: string
    yearOptions: number[]
    yearSelect: boolean
    activeKey: string
    selectedValues: string[]
    rangeStart: string | null
    rangeEnd: string | null
    previewKey: string | null
    rangeError: string
    announcement: string
    init: (el?: HTMLElement) => void
    destroy: () => void
    /** Replace the visible value without writing the model; used by pickers. */
    setValue: (value: unknown) => void
    /** Re-apply min/max after render, for time-sensitive bounds such as `now`. */
    setBounds: (min: string | null, max: string | null) => void
    /** Update the month used when there is no selected value. */
    setOpenTo: (key: string | null) => void
    /** Re-resolve the browser's local today across midnight. */
    refreshToday: () => void
    refresh: (announce?: boolean) => void
    build: () => void
    resolveYearOptions: () => number[]
    previousMonth: () => void
    nextMonth: () => void
    todayAction: () => void
    select: (key: string) => void
    onFocus: (key: string) => void
    onHover: (key: string) => void
    clearPreview: () => void
    onKeydown: (event: KeyboardEvent, key: string) => void
    isDisabled: (key: string, outside: boolean) => boolean
    isUnavailable: (key: string) => boolean
    isInView: (key: string) => boolean
    previewBounds: () => { start: string; end: string } | null
    findEnabled: (key: string, direction: number) => string | null
    findCell: (key: string) => CalendarCell | null
    rangeCrossesUnavailable: (start: string, end: string) => boolean
    /** Write the model (unless draft) and dispatch the bubbling `change` event. */
    commit: () => void
}

/** Normalized calendar value for the active mode. */
interface NormalizedValue {
    values: string[]
    start: string | null
    end: string | null
}

const stringKey = toDateKey

function normalizeValue(value: unknown, mode: CalendarMode): NormalizedValue {
    if (mode === 'range') {
        if (Array.isArray(value)) {
            return {
                values: [],
                start: stringKey(value[0]),
                end: stringKey(value[1]),
            }
        }

        if (typeof value === 'object' && value !== null) {
            const range = value as { start?: unknown; end?: unknown }

            return {
                values: [],
                start: stringKey(range.start),
                end: stringKey(range.end),
            }
        }

        if (typeof value === 'string') {
            const [start, end] = value.split('/', 2)

            return {
                values: [],
                start: stringKey(start ?? null),
                end: stringKey(end ?? null),
            }
        }

        return { values: [], start: null, end: null }
    }

    if (mode === 'multiple') {
        const candidates = Array.isArray(value) ? value : typeof value === 'string' ? value.split(',') : []

        return {
            values: Array.from(new Set(candidates.map(stringKey).filter((key): key is string => key !== null))),
            start: null,
            end: null,
        }
    }

    return {
        values: stringKey(value) === null ? [] : [stringKey(value) as string],
        start: null,
        end: null,
    }
}

/**
 * Create the Alpine data for a calendar.
 *
 * `wire`/`config.model` are optional; without them the calendar works as a plain
 * form control using the hidden input referenced by `config.formInputId`.
 */
export function calendarData(config: CalendarConfig, wire: WireLike | null): Calendar {
    let rootEl: HTMLElement | null = null
    let unwatch: (() => void) | null = null
    let openToKey: string | null = config.openTo
    let minKey: string | null = null
    let maxKey: string | null = null
    let unavailable: string[] = []

    const locale = resolveLocale(config.locale)
    const root = (): HTMLElement | null => rootEl
    const formInput = (): HTMLInputElement | null =>
        config.formInputId === null ? null : (document.getElementById(config.formInputId) as HTMLInputElement | null)
    const months = Math.min(2, Math.max(1, config.months))

    const applyValue = (target: Calendar, value: unknown): void => {
        const normalized = normalizeValue(value, target.mode)

        target.selectedValues = normalized.values
        target.rangeStart = normalized.start
        target.rangeEnd = normalized.end
    }

    const currentKey = (target: Calendar): string =>
        target.rangeStart ?? target.selectedValues[0] ?? openToKey ?? target.today

    return {
        mode: config.mode,
        draft: config.draft,
        locale: locale,
        startDay: config.startDay ?? firstDayOfWeek(locale),
        months,
        fixedWeeks: config.fixedWeeks,
        navigation: config.navigation,
        static: config.static,
        withToday: config.withToday,
        labels: config.labels,
        cellClass: config.cellClass,
        today: localTodayKey(),
        viewYear: 0,
        viewMonth: 0,
        monthsData: [],
        heading: '',
        monthHeading: '',
        yearOptions: [],
        yearSelect: config.yearSelect,
        activeKey: '',
        selectedValues: [],
        rangeStart: null,
        rangeEnd: null,
        previewKey: null,
        rangeError: '',
        announcement: '',

        init(el) {
            // Alpine calls init() itself before x-init supplies the root element.
            if (el === undefined || rootEl !== null) {
                return
            }

            rootEl = el
            minKey = config.min === 'today' ? this.today : stringKey(config.min)
            maxKey = config.max === 'today' ? this.today : stringKey(config.max)
            unavailable = config.unavailable.filter((key) => isValidKey(key))

            this.setValue(readModel(wire, config.model, config.value))

            unwatch = watchModel(wire, config.model, (value) => {
                this.setValue(value)
            })
        },

        destroy() {
            unwatch?.()
        },

        setBounds(min, max) {
            minKey = min === 'today' ? this.today : stringKey(min)
            maxKey = max === 'today' ? this.today : stringKey(max)
            this.previewKey = null
            this.refresh(false)
        },

        setOpenTo(key) {
            openToKey = key
        },

        refreshToday() {
            this.today = localTodayKey()

            if (config.min === 'today') {
                minKey = this.today
            }

            if (config.max === 'today') {
                maxKey = this.today
            }
        },

        setValue(value: unknown) {
            applyValue(this, value)

            let key = currentKey(this)

            if (this.isDisabled(key, false)) {
                if (minKey !== null && compareKeys(key, minKey) < 0) {
                    key = minKey
                } else if (maxKey !== null && compareKeys(key, maxKey) > 0) {
                    key = maxKey
                }

                if (this.isDisabled(key, false)) {
                    key = this.findEnabled(key, 1) ?? this.findEnabled(key, -1) ?? key
                }
            }

            const date = parseKey(key)

            if (date !== null) {
                this.viewYear = date.getUTCFullYear()
                this.viewMonth = date.getUTCMonth()
                this.activeKey = dateKey(date)
            }

            this.previewKey = null
            this.rangeError = ''
            this.refresh(false)
        },

        refresh(announce = false) {
            if (!this.isInView(this.activeKey)) {
                const first = dateKey(utcDate(this.viewYear, this.viewMonth, 1))
                this.activeKey = this.findEnabled(first, 1) ?? first
            }

            this.build()

            if (announce) {
                this.announcement = this.heading
            }
        },

        build() {
            const weekdays = formatWeekdayLabels(this.locale, this.startDay)
            const data: CalendarMonth[] = []

            for (let index = 0; index < this.months; index++) {
                const { year, month } = shiftMonth(this.viewYear, this.viewMonth, index)
                const weeks = monthWeeks(year, month, this.startDay, this.fixedWeeks)
                const monthKey = `${year}-${String(month + 1).padStart(2, '0')}`
                const activeKey = this.activeKey
                const enabledKeys = weeks.flat().filter((day) => !this.isDisabled(day.key, day.outside))
                const tabKey = enabledKeys.some((day) => day.key === activeKey)
                    ? activeKey
                    : (enabledKeys[0]?.key ?? '')

                data.push({
                    key: monthKey,
                    year,
                    month,
                    label: formatMonthLabel(this.locale, year, month),
                    weekdays,
                    weeks: weeks.map((week) =>
                        week.map((day): CalendarCell => {
                            const disabled = this.isDisabled(day.key, day.outside)
                            const unavailable = this.isUnavailable(day.key)
                            const selected =
                                this.selectedValues.includes(day.key) ||
                                this.rangeStart === day.key ||
                                this.rangeEnd === day.key
                            const preview = this.previewBounds()
                            const inRange =
                                preview !== null &&
                                compareKeys(day.key, preview.start) >= 0 &&
                                compareKeys(day.key, preview.end) <= 0 &&
                                !selected
                            const label = [
                                formatDayLabel(this.locale, day.key),
                                unavailable ? this.labels.unavailable : '',
                                selected ? this.labels.selected : '',
                            ]
                                .filter((part) => part !== '')
                                .join(', ')

                            return {
                                id: `${config.rootId}-day-${index}-${day.key}`,
                                key: day.key,
                                day: day.day,
                                label,
                                outside: day.outside,
                                today: day.key === this.today,
                                disabled,
                                unavailable,
                                selected,
                                inRange,
                                rangeStart: this.rangeStart === day.key,
                                rangeEnd: this.rangeEnd === day.key,
                                active: day.key === activeKey,
                                tabIndex: !this.static && !disabled && day.key === tabKey ? 0 : -1,
                            }
                        }),
                    ),
                })
            }

            this.monthsData = data
            this.heading = data.length === 1 ? data[0].label : `${data[0].label} – ${data[data.length - 1].label}`
            this.monthHeading =
                data.length === 1
                    ? formatMonthName(this.locale, data[0].year, data[0].month)
                    : `${formatMonthName(this.locale, data[0].year, data[0].month)} – ${formatMonthName(this.locale, data[data.length - 1].year, data[data.length - 1].month)}`
            this.yearOptions = this.resolveYearOptions()
        },

        /** Years offered by the header select: 1970 through five years ahead. */
        resolveYearOptions(): number[] {
            const today = parseKey(this.today)
            const currentYear = today?.getUTCFullYear() ?? new Date().getUTCFullYear()
            let first = 1970
            let last = currentYear + 5

            if (minKey !== null) {
                first = Math.max(first, Number(minKey.slice(0, 4)))
            }

            if (maxKey !== null) {
                last = Math.min(last, Number(maxKey.slice(0, 4)))
            }

            if (last < first) {
                last = first
            }

            const years = Array.from({ length: last - first + 1 }, (_, index) => first + index)

            // The header select must be able to show the month being viewed, even
            // when `open-to` points outside the default range.
            if (this.viewYear > 0 && !years.includes(this.viewYear)) {
                years.push(this.viewYear)
                years.sort((a, b) => a - b)
            }

            return years
        },

        isDisabled(key: string, outside: boolean): boolean {
            if (this.months > 1 && outside) {
                return true
            }

            if (minKey !== null && compareKeys(key, minKey) < 0) {
                return true
            }

            if (maxKey !== null && compareKeys(key, maxKey) > 0) {
                return true
            }

            return unavailable.includes(key)
        },

        isUnavailable(key: string): boolean {
            return unavailable.includes(key)
        },

        isInView(key: string): boolean {
            const date = parseKey(key)

            if (date === null) {
                return false
            }

            const current = this.viewYear * 12 + this.viewMonth
            const target = date.getUTCFullYear() * 12 + date.getUTCMonth()

            return target >= current && target < current + this.months
        },

        previewBounds(): { start: string; end: string } | null {
            if (this.mode !== 'range') {
                return null
            }

            if (this.rangeStart !== null && this.rangeEnd !== null) {
                return compareKeys(this.rangeStart, this.rangeEnd) <= 0
                    ? { start: this.rangeStart, end: this.rangeEnd }
                    : { start: this.rangeEnd, end: this.rangeStart }
            }

            if (this.rangeStart !== null && this.previewKey !== null) {
                return compareKeys(this.rangeStart, this.previewKey) <= 0
                    ? { start: this.rangeStart, end: this.previewKey }
                    : { start: this.previewKey, end: this.rangeStart }
            }

            return null
        },

        previousMonth() {
            if (!this.navigation) {
                return
            }

            const next = shiftMonth(this.viewYear, this.viewMonth, -1)
            this.viewYear = next.year
            this.viewMonth = next.month
            this.refresh(true)
        },

        nextMonth() {
            if (!this.navigation) {
                return
            }

            const next = shiftMonth(this.viewYear, this.viewMonth, 1)
            this.viewYear = next.year
            this.viewMonth = next.month
            this.refresh(true)
        },

        todayAction() {
            const today = parseKey(this.today)

            if (today === null) {
                return
            }

            if (this.viewYear !== today.getUTCFullYear() || this.viewMonth !== today.getUTCMonth()) {
                this.viewYear = today.getUTCFullYear()
                this.viewMonth = today.getUTCMonth()
                this.activeKey = this.today
                this.refresh(true)

                return
            }

            if (!this.static) {
                this.select(this.today)
            }
        },

        select(key: string) {
            if (this.static) {
                return
            }

            const cell = this.findCell(key)

            if (cell === null || cell.disabled) {
                return
            }

            this.activeKey = key
            this.previewKey = null
            this.rangeError = ''

            if (this.mode === 'single') {
                this.selectedValues = [key]
            } else if (this.mode === 'multiple') {
                this.selectedValues = this.selectedValues.includes(key)
                    ? this.selectedValues.filter((value) => value !== key)
                    : [...this.selectedValues, key].sort()
            } else {
                if (this.rangeStart === null || this.rangeEnd !== null) {
                    this.rangeStart = key
                    this.rangeEnd = null
                    this.commit()
                    this.refresh()

                    return
                }

                if (compareKeys(key, this.rangeStart) < 0) {
                    this.rangeStart = key
                    this.rangeEnd = null
                    this.commit()
                    this.refresh()

                    return
                }

                const length = daysBetweenInclusive(this.rangeStart, key)

                if (config.minRange !== null && length < config.minRange) {
                    this.rangeError = this.labels.rangeTooShort.replace(':count', String(config.minRange))
                    this.refresh()

                    return
                }

                if (config.maxRange !== null && length > config.maxRange) {
                    this.rangeError = this.labels.rangeTooLong.replace(':count', String(config.maxRange))
                    this.refresh()

                    return
                }

                if (this.rangeCrossesUnavailable(this.rangeStart, key)) {
                    this.rangeError = this.labels.rangeUnavailable
                    this.refresh()

                    return
                }

                this.rangeEnd = key
            }

            this.commit()
            this.refresh()
        },

        onFocus(key: string) {
            this.activeKey = key

            if (this.mode === 'range' && this.rangeStart !== null && this.rangeEnd === null) {
                this.previewKey = key
            }

            this.refresh()
        },

        onHover(key: string) {
            if (this.mode !== 'range' || this.rangeStart === null || this.rangeEnd !== null) {
                return
            }

            this.previewKey = key
            this.refresh()
        },

        clearPreview() {
            if (this.previewKey === null) {
                return
            }

            this.previewKey = null
            this.refresh()
        },

        onKeydown(event: KeyboardEvent, key: string) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault()
                this.select(key)

                return
            }

            let target: string | null = null
            let direction = 1

            if (event.key === 'ArrowLeft') {
                target = addDays(key, -1)
                direction = -1
            } else if (event.key === 'ArrowRight') {
                target = addDays(key, 1)
            } else if (event.key === 'ArrowUp') {
                target = addDays(key, -7)
                direction = -1
            } else if (event.key === 'ArrowDown') {
                target = addDays(key, 7)
            } else if (event.key === 'Home') {
                target = startOfWeek(key, this.startDay)
            } else if (event.key === 'End') {
                target = endOfWeek(key, this.startDay)
                direction = -1
            } else if (event.key === 'PageUp') {
                target = addMonthsClamped(key, event.shiftKey ? -12 : -1)
                direction = -1
            } else if (event.key === 'PageDown') {
                target = addMonthsClamped(key, event.shiftKey ? 12 : 1)
            }

            if (target === null) {
                return
            }

            event.preventDefault()

            const next = this.findEnabled(target, direction)

            if (next === null) {
                return
            }

            if (!this.isInView(next)) {
                const date = parseKey(next)

                if (date !== null) {
                    this.viewYear = date.getUTCFullYear()
                    this.viewMonth = date.getUTCMonth()
                }
            }

            this.activeKey = next

            if (this.mode === 'range' && this.rangeStart !== null && this.rangeEnd === null) {
                this.previewKey = next
            }

            this.refresh()

            window.setTimeout(() => {
                const cell = this.findCell(next)

                if (cell !== null) {
                    document.getElementById(cell.id)?.focus()
                }
            }, 0)
        },

        findEnabled(key: string, direction: number): string | null {
            if (!isValidKey(key)) {
                return null
            }

            let candidate = key

            for (let step = 0; step < 42; step++) {
                const date = parseKey(candidate)

                if (date === null) {
                    return null
                }

                if (!this.isDisabled(candidate, false)) {
                    return candidate
                }

                candidate = addDays(candidate, direction)
            }

            return null
        },

        findCell(key: string): CalendarCell | null {
            return (
                this.monthsData
                    .flatMap((month) => month.weeks.flat())
                    .find((cell) => cell.key === key && !cell.disabled) ?? null
            )
        },

        rangeCrossesUnavailable(start: string, end: string): boolean {
            const from = compareKeys(start, end) <= 0 ? start : end
            const to = compareKeys(start, end) <= 0 ? end : start

            return unavailable.some((key) => compareKeys(key, from) >= 0 && compareKeys(key, to) <= 0)
        },

        commit() {
            const value =
                this.mode === 'range'
                    ? this.rangeStart === null
                        ? null
                        : { start: this.rangeStart, end: this.rangeEnd }
                    : this.mode === 'multiple'
                      ? [...this.selectedValues].sort()
                      : (this.selectedValues[0] ?? null)

            if (!config.draft) {
                writeModel(wire, config.model, config.live, value, formInput())
            }

            root()?.dispatchEvent(
                new CustomEvent('change', {
                    bubbles: true,
                    detail: { value },
                }),
            )
        },
    }
}
