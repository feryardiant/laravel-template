export interface RawDay {
    key: string
    day: number
    outside: boolean
}

export function utcDate(year: number, monthIndex: number, day: number): Date {
    const date = new Date(0)
    date.setUTCFullYear(year, monthIndex, day)
    date.setUTCHours(12, 0, 0, 0)

    return date
}

export function dateKey(date: Date): string {
    const year = String(date.getUTCFullYear()).padStart(4, '0')
    const month = String(date.getUTCMonth() + 1).padStart(2, '0')
    const day = String(date.getUTCDate()).padStart(2, '0')

    return `${year}-${month}-${day}`
}

export function parseKey(key: string): Date | null {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(key)

    if (match === null) {
        return null
    }

    const date = utcDate(Number(match[1]), Number(match[2]) - 1, Number(match[3]))

    return dateKey(date) === key ? date : null
}

export function isValidKey(key: string): boolean {
    return parseKey(key) !== null
}

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

export function localTodayKey(): string {
    const now = new Date()

    return dateKey(utcDate(now.getFullYear(), now.getMonth(), now.getDate()))
}

export function addDays(key: string, days: number): string {
    const date = parseKey(key)

    if (date === null) {
        return key
    }

    date.setUTCDate(date.getUTCDate() + days)

    return dateKey(date)
}

export function addMonths(key: string, months: number): string {
    const date = parseKey(key)

    if (date === null) {
        return key
    }

    date.setUTCMonth(date.getUTCMonth() + months)

    return dateKey(date)
}

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

export function shiftMonth(year: number, month: number, delta: number): { year: number; month: number } {
    const date = utcDate(year, month + delta, 1)

    return { year: date.getUTCFullYear(), month: date.getUTCMonth() }
}

export function startOfMonth(key: string): string {
    const date = parseKey(key)

    if (date === null) {
        return key
    }

    return dateKey(utcDate(date.getUTCFullYear(), date.getUTCMonth(), 1))
}

export function endOfMonth(key: string): string {
    const date = parseKey(key)

    if (date === null) {
        return key
    }

    return dateKey(utcDate(date.getUTCFullYear(), date.getUTCMonth() + 1, 0))
}

export function startOfYear(key: string): string {
    const date = parseKey(key)

    if (date === null) {
        return key
    }

    return dateKey(utcDate(date.getUTCFullYear(), 0, 1))
}

export function endOfYear(key: string): string {
    const date = parseKey(key)

    if (date === null) {
        return key
    }

    return dateKey(utcDate(date.getUTCFullYear(), 11, 31))
}

export function startOfWeek(key: string, startDay: number): string {
    const date = parseKey(key)

    if (date === null) {
        return key
    }

    const offset = (date.getUTCDay() - startDay + 7) % 7

    return addDays(key, -offset)
}

export function endOfWeek(key: string, startDay: number): string {
    return addDays(startOfWeek(key, startDay), 6)
}

export function daysInMonth(year: number, month: number): number {
    return utcDate(year, month + 1, 0).getUTCDate()
}

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

export function formatMonthLabel(locale: string, year: number, month: number): string {
    return monthFormatter(locale).format(utcDate(year, month, 1))
}

export function formatWeekdayLabels(locale: string, startDay: number): string[] {
    const formatter = weekdayFormatter(locale)

    return Array.from({ length: 7 }, (_, index) => formatter.format(utcDate(2024, 0, 7 + startDay + index)))
}

export function formatDayLabel(locale: string, key: string): string {
    const date = parseKey(key)

    if (date === null) {
        return key
    }

    return dayFormatter(locale).format(date)
}

const shortDateFormatters = new Map<string, Intl.DateTimeFormat>()

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

export function compareKeys(left: string, right: string): number {
    return left < right ? -1 : left > right ? 1 : 0
}

export function daysBetweenInclusive(start: string, end: string): number {
    const startDate = parseKey(start)
    const endDate = parseKey(end)

    if (startDate === null || endDate === null) {
        return 0
    }

    return Math.floor((endDate.getTime() - startDate.getTime()) / 86_400_000) + 1
}
