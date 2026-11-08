import { isValidKey } from './date'
import { isTimeUnavailable, minutesOfDay, timeFromMinutes, type TimeRange } from './time'

/**
 * Wall-clock datetime helpers for the datetime picker.
 *
 * Canonical values are `Y-m-d\TH:i`. No timezone conversion is performed.
 */

/** Normalized unavailable entries used by the datetime picker. */
export interface DateTimeUnavailable {
    dates: Set<string>
    times: TimeRange[]
    datetimes: Set<string>
    datetimeRanges: { date: string; start: number; end: number }[]
}

const DATETIME_PATTERN = /^(\d{4}-\d{2}-\d{2})[T ]([01]\d|2[0-3]):([0-5]\d)$/
const DATETIME_RANGE_PATTERN = /^(\d{4}-\d{2}-\d{2})[T ]([01]\d|2[0-3]):([0-5]\d)-([01]\d|2[0-3]):([0-5]\d)$/
const TIME_RANGE_PATTERN = /^([01]\d|2[0-3]):([0-5]\d)-([01]\d|2[0-3]):([0-5]\d)$/

/**
 * Normalize `Y-m-d\TH:i`, `Y-m-d H:i` or a date-only value.
 *
 * Date-only values become midnight. Invalid values return `null`.
 */
export function parseDateTime(value: unknown): string | null {
    if (typeof value !== 'string') {
        return null
    }

    const trimmed = value.trim()
    const match = DATETIME_PATTERN.exec(trimmed)

    if (match !== null) {
        return isValidKey(match[1]) ? `${match[1]}T${match[2]}:${match[3]}` : null
    }

    return isValidKey(trimmed) ? `${trimmed}T00:00` : null
}

/** Date portion of a canonical datetime. */
export function datePart(value: string): string {
    return value.slice(0, 10)
}

/** Time portion of a canonical datetime. */
export function timePart(value: string): string {
    return value.slice(11, 16)
}

/** Combine a date key and an `H:i` time into the canonical datetime shape. */
export function combineDateTime(date: string, time: string): string {
    return `${date}T${time}`
}

/** Lexicographic comparison for canonical datetimes. */
export function compareDateTime(left: string, right: string): number {
    return left < right ? -1 : left > right ? 1 : 0
}

/**
 * Parse datetime unavailable entries.
 *
 * Accepts dates, daily times, daily time ranges, datetime slots and one-day
 * datetime ranges; invalid entries are dropped.
 */
export function parseUnavailable(entries: string[]): DateTimeUnavailable {
    const unavailable: DateTimeUnavailable = {
        dates: new Set<string>(),
        times: [],
        datetimes: new Set<string>(),
        datetimeRanges: [],
    }

    for (const entry of entries) {
        const datetimeRange = DATETIME_RANGE_PATTERN.exec(entry)

        if (datetimeRange !== null && isValidKey(datetimeRange[1])) {
            const start = minutesOfDay(`${datetimeRange[2]}:${datetimeRange[3]}`)
            const end = minutesOfDay(`${datetimeRange[4]}:${datetimeRange[5]}`)

            if (start !== null && end !== null) {
                unavailable.datetimeRanges.push({
                    date: datetimeRange[1],
                    start: Math.min(start, end),
                    end: Math.max(start, end),
                })
            }

            continue
        }

        const datetime = DATETIME_PATTERN.exec(entry)

        if (datetime !== null && isValidKey(datetime[1])) {
            unavailable.datetimes.add(`${datetime[1]}T${datetime[2]}:${datetime[3]}`)

            continue
        }

        const timeRange = TIME_RANGE_PATTERN.exec(entry)

        if (timeRange !== null) {
            const start = minutesOfDay(`${timeRange[1]}:${timeRange[2]}`)
            const end = minutesOfDay(`${timeRange[3]}:${timeRange[4]}`)

            if (start !== null && end !== null) {
                unavailable.times.push({ start: Math.min(start, end), end: Math.max(start, end) })
            }

            continue
        }

        if (isValidKey(entry)) {
            unavailable.dates.add(entry)

            continue
        }

        const minutes = minutesOfDay(entry)

        if (minutes !== null) {
            unavailable.times.push({ start: minutes, end: minutes })
        }
    }

    return unavailable
}

/** Whether a date and time match any unavailable entry. */
export function isUnavailable(date: string, time: string, unavailable: DateTimeUnavailable): boolean {
    if (unavailable.dates.has(date) || unavailable.datetimes.has(`${date}T${time}`)) {
        return true
    }

    const minutes = minutesOfDay(time)

    if (minutes === null) {
        return true
    }

    if (isTimeUnavailable(minutes, unavailable.times)) {
        return true
    }

    return unavailable.datetimeRanges.some(
        (range) => range.date === date && minutes >= range.start && minutes <= range.end,
    )
}

/** The browser's current local datetime in canonical form. */
export function localNowDateTime(): string {
    const now = new Date()
    const date = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`

    return `${date}T${timeFromMinutes(now.getHours() * 60 + now.getMinutes())}`
}
