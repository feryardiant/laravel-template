import { describe, expect, it } from 'vite-plus/test'

import { isRangeReversed, seedEndTime } from './datetime'

const allowAll = (): boolean => true

describe('isRangeReversed', () => {
    it('accepts a range that moves forward across dates', () => {
        expect(isRangeReversed('2026-11-08', '09:00', '2026-11-10', '17:00')).toBe(false)
    })

    it('rejects a range whose end date is earlier than its start date', () => {
        expect(isRangeReversed('2026-11-10', '09:00', '2026-11-08', '17:00')).toBe(true)
    })

    it('rejects a same-day range that ends before the start', () => {
        expect(isRangeReversed('2026-11-08', '17:00', '2026-11-08', '09:00')).toBe(true)
        expect(isRangeReversed('2026-11-08', '09:00', '2026-11-08', '09:00')).toBe(true)
    })

    it('does not flag an incomplete range', () => {
        expect(isRangeReversed('2026-11-08', '09:00', null, '17:00')).toBe(false)
    })
})

describe('seedEndTime', () => {
    it('uses the configured end when it is allowed', () => {
        expect(
            seedEndTime({ configured: '17:00', start: '09:00', sameDay: false, interval: 30, allowed: allowAll }),
        ).toBe('17:00')
    })

    it('falls back to the start plus the interval when the configured end is unavailable', () => {
        expect(
            seedEndTime({ configured: '17:00', start: '09:00', sameDay: false, interval: 30, allowed: () => false }),
        ).toBe('09:30')
    })

    it('rejects a same-day configured end that does not follow the start', () => {
        expect(
            seedEndTime({ configured: '09:00', start: '17:00', sameDay: true, interval: 30, allowed: allowAll }),
        ).toBe('17:30')
    })

    it('returns the configured end when there is no start', () => {
        expect(
            seedEndTime({ configured: '17:00', start: null, sameDay: false, interval: 30, allowed: () => false }),
        ).toBe('17:00')
    })

    it('clamps the fallback to the end of the day', () => {
        expect(
            seedEndTime({ configured: '23:00', start: '23:45', sameDay: false, interval: 30, allowed: () => false }),
        ).toBe('23:59')
    })
})
