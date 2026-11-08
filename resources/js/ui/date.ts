/**
 * Gregorian calendar arithmetic for date-only values.
 *
 * Canonical values are `Y-m-d` strings. Calculations use UTC getters and a noon
 * anchor so DST transitions cannot shift the civil date. Labels are formatted
 * with `Intl` using `calendar: 'gregory'` and `timeZone: 'UTC'`.
 */

export interface RawDay {
    key: string
    day: number
    outside: boolean
}

/** Build a UTC noon date from calendar parts; noon avoids DST date shifts. */
export function utcDate(year: number, monthIndex: number, day: number): Date {
    const date = new Date(0)
    date.setUTCFullYear(year, monthIndex, day)
    date.setUTCHours(12, 0, 0, 0)

    return date
}

/** Format a Date as `Y-m-d` using its UTC parts. */
export function dateKey(date: Date): string {
    const year = String(date.getUTCFullYear()).padStart(4, '0')
    const month = String(date.getUTCMonth() + 1).padStart(2, '0')
    const day = String(date.getUTCDate()).padStart(2, '0')

    return `${year}-${month}-${day}`
}

/**
 * Parse a strict `Y-m-d` key into a UTC noon date.
 *
 * Impossible dates such as 30 February return `null`.
 */
export function parseKey(key: string): Date | null {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(key)

    if (match === null) {
        return null
    }

    const date = utcDate(Number(match[1]), Number(match[2]) - 1, Number(match[3]))

    return dateKey(date) === key ? date : null
}

/** Whether a string is a strict, real `Y-m-d` key. */
export function isValidKey(key: string): boolean {
    return parseKey(key) !== null
}

/**
 * Read a date key from a `Y-m-d` or ISO datetime string.
 *
 * Date-time suffixes are ignored; the date part is validated.
 */
export function toDateKey(value: unknown): string | null {
    if (typeof value !== 'string') {
        return null
    }

    if (isValidKey(value)) {
        return value
    }

    const match = /^(\d{4}-\d{2}-\d{2})(?:[T ].*)?$/.exec(value)

    return match !== null && isValidKey(match[1]) ? match[1] : null
}

/** The browser's local civil date, independent of UTC. */
export function localTodayKey(): string {
    const now = new Date()

    return dateKey(utcDate(now.getFullYear(), now.getMonth(), now.getDate()))
}

/** Shift a key by whole days. */
export function addDays(key: string, days: number): string {
    const date = parseKey(key)

    if (date === null) {
        return key
    }

    date.setUTCDate(date.getUTCDate() + days)

    return dateKey(date)
}

/** Add months using Date month semantics; prefer `addMonthsClamped`. */
export function addMonths(key: string, months: number): string {
    const date = parseKey(key)

    if (date === null) {
        return key
    }

    date.setUTCMonth(date.getUTCMonth() + months)

    return dateKey(date)
}

/**
 * Add months while clamping the day to the target month.
 *
 * 31 January + 1 month becomes 28/29 February instead of rolling into March.
 */
export function addMonthsClamped(key: string, months: number): string {
    const date = parseKey(key)

    if (date === null) {
        return key
    }

    const targetYear = date.getUTCFullYear()
    const targetMonth = date.getUTCMonth() + months
    const day = Math.min(date.getUTCDate(), daysInMonth(targetYear, targetMonth))

    return dateKey(utcDate(targetYear, targetMonth, day))
}

/** Shift a year/month pair by a number of months. */
export function shiftMonth(year: number, month: number, delta: number): { year: number; month: number } {
    const date = utcDate(year, month + delta, 1)

    return { year: date.getUTCFullYear(), month: date.getUTCMonth() }
}

/** First day of the key's month. */
export function startOfMonth(key: string): string {
    const date = parseKey(key)

    if (date === null) {
        return key
    }

    return dateKey(utcDate(date.getUTCFullYear(), date.getUTCMonth(), 1))
}

/** Last day of the key's month. */
export function endOfMonth(key: string): string {
    const date = parseKey(key)

    if (date === null) {
        return key
    }

    return dateKey(utcDate(date.getUTCFullYear(), date.getUTCMonth() + 1, 0))
}

/** First day of the key's year. */
export function startOfYear(key: string): string {
    const date = parseKey(key)

    if (date === null) {
        return key
    }

    return dateKey(utcDate(date.getUTCFullYear(), 0, 1))
}

/** Last day of the key's year. */
export function endOfYear(key: string): string {
    const date = parseKey(key)

    if (date === null) {
        return key
    }

    return dateKey(utcDate(date.getUTCFullYear(), 11, 31))
}

/** First day of the key's week for the given start day. */
export function startOfWeek(key: string, startDay: number): string {
    const date = parseKey(key)

    if (date === null) {
        return key
    }

    const offset = (date.getUTCDay() - startDay + 7) % 7

    return addDays(key, -offset)
}

/** Last day of the key's week for the given start day. */
export function endOfWeek(key: string, startDay: number): string {
    return addDays(startOfWeek(key, startDay), 6)
}

/** Number of days in a zero-based month. */
export function daysInMonth(year: number, month: number): number {
    return utcDate(year, month + 1, 0).getUTCDate()
}

/**
 * Build week rows for a month, including leading and trailing outside days.
 *
 * Natural months use four to six rows; `fixedWeeks` always returns six.
 */
export function monthWeeks(year: number, month: number, startDay: number, fixedWeeks: boolean): RawDay[][] {
    const first = utcDate(year, month, 1)
    const offset = (first.getUTCDay() - startDay + 7) % 7
    const total = offset + daysInMonth(year, month)
    const weekCount = fixedWeeks ? 6 : Math.ceil(total / 7)
    const weeks: RawDay[][] = []

    for (let weekIndex = 0; weekIndex < weekCount; weekIndex++) {
        const week: RawDay[] = []

        for (let dayIndex = 0; dayIndex < 7; dayIndex++) {
            const dayNumber = weekIndex * 7 + dayIndex - offset + 1
            const date = utcDate(year, month, dayNumber)

            week.push({
                key: dateKey(date),
                day: date.getUTCDate(),
                outside: dayNumber < 1 || dayNumber > daysInMonth(year, month),
            })
        }

        weeks.push(week)
    }

    return weeks
}

/**
 * Resolve the locale's first weekday as 0 (Sunday) to 6 (Saturday).
 *
 * Uses `Intl.Locale` week data when available and a documented fallback table
 * for the locales this project supports. Other locales should pass `start-day`.
 */
export function firstDayOfWeek(locale: string): number {
    try {
        const localeInfo = new Intl.Locale(locale) as unknown as {
            getWeekInfo?: () => { firstDay?: number }
            weekInfo?: { firstDay?: number }
        }
        const weekInfo = localeInfo.getWeekInfo?.() ?? localeInfo.weekInfo
        const firstDay = weekInfo?.firstDay

        if (typeof firstDay === 'number') {
            return firstDay % 7
        }
    } catch {
        // Fall through to the documented fallback.
    }

    // Fallback table for locales this project supports. Other locales should
    // pass `start-day` when Intl week data is unavailable.
    const normalized = locale.toLowerCase()
    const language = normalized.split('-')[0] ?? ''

    const fallback: Record<string, number> = {
        id: 0,
        en: 0,
        'en-us': 0,
        'en-ca': 0,
        'en-ph': 0,
        'en-gb': 1,
        'ar-sa': 0,
        'ar-eg': 6,
    }

    if (language === 'id') {
        return 0
    }

    return fallback[normalized] ?? 1
}

const monthFormatters = new Map<string, Intl.DateTimeFormat>()
const weekdayFormatters = new Map<string, Intl.DateTimeFormat>()
const dayFormatters = new Map<string, Intl.DateTimeFormat>()

function monthFormatter(locale: string): Intl.DateTimeFormat {
    let formatter = monthFormatters.get(locale)

    if (formatter === undefined) {
        formatter = new Intl.DateTimeFormat(locale, {
            calendar: 'gregory',
            month: 'long',
            timeZone: 'UTC',
            year: 'numeric',
        })
        monthFormatters.set(locale, formatter)
    }

    return formatter
}

function weekdayFormatter(locale: string): Intl.DateTimeFormat {
    let formatter = weekdayFormatters.get(locale)

    if (formatter === undefined) {
        formatter = new Intl.DateTimeFormat(locale, {
            calendar: 'gregory',
            timeZone: 'UTC',
            weekday: 'short',
        })
        weekdayFormatters.set(locale, formatter)
    }

    return formatter
}

function dayFormatter(locale: string): Intl.DateTimeFormat {
    let formatter = dayFormatters.get(locale)

    if (formatter === undefined) {
        formatter = new Intl.DateTimeFormat(locale, {
            calendar: 'gregory',
            day: 'numeric',
            month: 'long',
            timeZone: 'UTC',
            weekday: 'long',
            year: 'numeric',
        })
        dayFormatters.set(locale, formatter)
    }

    return formatter
}

/** Localized month + year heading, formatted in UTC. */
export function formatMonthLabel(locale: string, year: number, month: number): string {
    return monthFormatter(locale).format(utcDate(year, month, 1))
}

/** Localized short weekday headers in display order. */
export function formatWeekdayLabels(locale: string, startDay: number): string[] {
    const formatter = weekdayFormatter(locale)

    return Array.from({ length: 7 }, (_, index) => formatter.format(utcDate(2024, 0, 7 + startDay + index)))
}

/** Full localized accessible name for a day cell. */
export function formatDayLabel(locale: string, key: string): string {
    const date = parseKey(key)

    if (date === null) {
        return key
    }

    return dayFormatter(locale).format(date)
}

const shortDateFormatters = new Map<string, Intl.DateTimeFormat>()

/** Compact localized date used by trigger labels and range summaries. */
export function formatShortDate(locale: string, key: string): string {
    const date = parseKey(key)

    if (date === null) {
        return key
    }

    let formatter = shortDateFormatters.get(locale)

    if (formatter === undefined) {
        formatter = new Intl.DateTimeFormat(locale, {
            calendar: 'gregory',
            day: 'numeric',
            month: 'short',
            timeZone: 'UTC',
            year: 'numeric',
        })
        shortDateFormatters.set(locale, formatter)
    }

    return formatter.format(date)
}

/** Lexicographic comparison for `Y-m-d` keys. */
export function compareKeys(left: string, right: string): number {
    return left < right ? -1 : left > right ? 1 : 0
}

/** Inclusive day count between two keys; a one-day range returns 1. */
export function daysBetweenInclusive(start: string, end: string): number {
    const startDate = parseKey(start)
    const endDate = parseKey(end)

    if (startDate === null || endDate === null) {
        return 0
    }

    return Math.floor((endDate.getTime() - startDate.getTime()) / 86_400_000) + 1
}
