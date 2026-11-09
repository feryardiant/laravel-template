import { describe, expect, it } from 'vite-plus/test'

import { addDays, findEnabledKey, isValidKey } from './date'

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
