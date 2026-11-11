import { describe, expect, it } from 'vite-plus/test'

import { addDays, findEnabledKey, firstDayOfWeek, formatWeekdayLabels, isValidKey, monthWeeks } from './date'

describe('findEnabledKey', () => {
    const start = '2026-11-01'

    it('skips a long unavailable run', () => {
        const disabled = new Set(Array.from({ length: 50 }, (_, index) => addDays(start, index)))

        expect(
            findEnabledKey({
                start,
                direction: 1,
                min: null,
                max: null,
                maxSteps: disabled.size + 2,
                isDisabled: (key) => disabled.has(key),
            }),
        ).toBe(addDays(start, 50))
    })

    it('stops at the max boundary', () => {
        expect(
            findEnabledKey({
                start,
                direction: 1,
                min: null,
                max: addDays(start, 3),
                maxSteps: 10,
                isDisabled: () => true,
            }),
        ).toBeNull()
    })

    it('clamps a start before the minimum', () => {
        expect(
            findEnabledKey({
                start: '2026-01-01',
                direction: 1,
                min: '2026-02-01',
                max: null,
                maxSteps: 2,
                isDisabled: () => false,
            }),
        ).toBe('2026-02-01')
    })

    it('searches backwards', () => {
        const disabled = new Set([addDays(start, 1), addDays(start, 2), addDays(start, 3)])

        expect(
            findEnabledKey({
                start: addDays(start, 3),
                direction: -1,
                min: null,
                max: null,
                maxSteps: disabled.size + 2,
                isDisabled: (key) => disabled.has(key),
            }),
        ).toBe(start)
    })

    it('rejects an invalid start key', () => {
        expect(
            findEnabledKey({
                start: '10000-01-01',
                direction: 1,
                min: null,
                max: null,
                maxSteps: 2,
                isDisabled: () => false,
            }),
        ).toBeNull()
    })
})

describe('isValidKey', () => {
    it('rejects years outside four digits', () => {
        expect(isValidKey('2026-11-01')).toBe(true)
        expect(isValidKey('10000-01-01')).toBe(false)
    })
})

describe('monthWeeks', () => {
    it('lays out a leap February in five rows', () => {
        const weeks = monthWeeks(2024, 1, 0, false)

        expect(weeks).toHaveLength(5)
        expect(weeks.flat().filter((day) => !day.outside)).toHaveLength(29)
    })

    it('lays out a natural four-week February in four rows', () => {
        const weeks = monthWeeks(2026, 1, 0, false)

        expect(weeks).toHaveLength(4)
        expect(weeks.flat().filter((day) => !day.outside)).toHaveLength(28)
    })
})

describe('week starts', () => {
    it('uses the locale default and an explicit start day', () => {
        expect(firstDayOfWeek('en')).toBe(0)
        expect(formatWeekdayLabels('en', 0)[0]).toBe('Sun')
        expect(formatWeekdayLabels('id', 1)[0]).toBe('Sen')
    })

    it('falls back to the supported locale table without Intl week info', () => {
        const descriptor = Object.getOwnPropertyDescriptor(Intl, 'Locale')

        Object.defineProperty(Intl, 'Locale', {
            configurable: true,
            value: function () {
                return {}
            },
        })

        try {
            expect(firstDayOfWeek('en-CA')).toBe(0)
            expect(firstDayOfWeek('en-PH')).toBe(0)
            expect(firstDayOfWeek('en')).toBe(0)
            expect(firstDayOfWeek('en-GB')).toBe(1)
            expect(firstDayOfWeek('ar-SA')).toBe(0)
            expect(firstDayOfWeek('ar-EG')).toBe(6)
        } finally {
            if (descriptor !== undefined) {
                Object.defineProperty(Intl, 'Locale', descriptor)
            }
        }
    })
})
