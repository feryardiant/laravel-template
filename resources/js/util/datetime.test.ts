import { describe, expect, it } from 'vite-plus/test'

import { seedEndTime } from './datetime'

const allowAll = (): boolean => true

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
