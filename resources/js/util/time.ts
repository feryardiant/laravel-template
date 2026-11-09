/**
 * Wall-clock time helpers shared by the time and datetime pickers.
 *
 * Values are `H:i` strings with no timezone conversion. Bounds and unavailable
 * ranges are evaluated as minutes of the day.
 */

const TIME_PATTERN = /^([01]\d|2[0-3]):([0-5]\d)$/

/** Inclusive range in minutes of the day. */
export interface TimeRange {
    start: number
    end: number
}

/** Whether a string is a strict `H:i` time. */
export function isValidTime(value: string): boolean {
    return TIME_PATTERN.test(value)
}

/**
 * Normalize `H:i` or `H:i:s` input to `H:i`.
 *
 * Invalid values return `null`.
 */
export function toTime(value: unknown): string | null {
    if (typeof value !== 'string') {
        return null
    }

    const match = /^([01]\d|2[0-3]):([0-5]\d)(?::[0-5]\d)?$/.exec(value)

    return match === null ? null : `${match[1]}:${match[2]}`
}

/** Convert `H:i` to minutes since midnight. */
export function minutesOfDay(key: string): number | null {
    const time = toTime(key)

    if (time === null) {
        return null
    }

    const [hour, minute] = time.split(':').map(Number)

    return hour * 60 + minute
}

/** Convert minutes since midnight to `H:i`. */
export function timeFromMinutes(minutes: number): string {
    const hour = Math.floor(minutes / 60)
    const minute = minutes % 60

    return `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`
}

/** The browser's current local time as `H:i`. */
export function localNowKey(): string {
    const now = new Date()

    return timeFromMinutes(now.getHours() * 60 + now.getMinutes())
}

/** Lexicographic comparison for `H:i` values. */
export function compareTime(left: string, right: string): number {
    return left < right ? -1 : left > right ? 1 : 0
}

const formatters = new Map<string, Intl.DateTimeFormat>()

/** Whether the locale's default time format uses a 12-hour clock. */
export function prefersHour12(locale: string): boolean {
    const key = `${locale}|auto`
    let formatter = formatters.get(key)

    if (formatter === undefined) {
        formatter = new Intl.DateTimeFormat(locale, { hour: 'numeric', timeZone: 'UTC' })
        formatters.set(key, formatter)
    }

    return formatter.resolvedOptions().hour12 ?? false
}

/**
 * Format a time for the active locale and requested 12/24-hour mode.
 *
 * `auto` follows the locale preference; formatting uses a UTC anchor so the
 * wall-clock value cannot shift.
 */
export function formatTime(locale: string, key: string, format: 'auto' | '12-hour' | '24-hour'): string {
    const minutes = minutesOfDay(key)

    if (minutes === null) {
        return key
    }

    const mode = format === 'auto' ? (prefersHour12(locale) ? '12-hour' : '24-hour') : format
    const cacheKey = `${locale}|${mode}`
    let formatter = formatters.get(cacheKey)

    if (formatter === undefined) {
        const date = new Date(0)
        date.setUTCHours(0, 0, 0, 0)

        formatter = new Intl.DateTimeFormat(locale, {
            hour: 'numeric',
            minute: '2-digit',
            timeZone: 'UTC',
            ...(mode === '12-hour' ? { hour12: true } : { hourCycle: 'h23' }),
        })
        formatters.set(cacheKey, formatter)
    }

    const date = new Date(0)
    date.setUTCHours(Math.floor(minutes / 60), minutes % 60, 0, 0)

    return formatter.format(date)
}

/**
 * Parse `H:i` and inclusive `H:i-H:i` entries into minute ranges.
 *
 * Invalid entries are dropped.
 */
export function parseUnavailable(entries: string[]): TimeRange[] {
    return entries.flatMap((entry) => {
        const [start, end] = entry.split('-', 2)
        const from = minutesOfDay(start ?? '')

        if (from === null) {
            return []
        }

        const to = end === undefined ? from : minutesOfDay(end)

        if (to === null) {
            return []
        }

        return [{ start: Math.min(from, to), end: Math.max(from, to) }]
    })
}

/** Whether minutes since midnight fall inside any unavailable range. */
export function isTimeUnavailable(minutes: number, ranges: TimeRange[]): boolean {
    return ranges.some((range) => minutes >= range.start && minutes <= range.end)
}
