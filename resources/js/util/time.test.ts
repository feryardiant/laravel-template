import { describe, expect, it } from 'vite-plus/test'

import { minutesOfDay, timeFromMinutes, toTime } from './time'

describe('toTime', () => {
    it('trims surrounding whitespace', () => {
        expect(toTime(' 09:30 ')).toBe('09:30')
    })

    it('drops seconds', () => {
        expect(toTime('09:30:45')).toBe('09:30')
    })

    it('rejects invalid values', () => {
        expect(toTime('24:00')).toBeNull()
        expect(toTime('9:30')).toBeNull()
        expect(toTime(930)).toBeNull()
    })
})

describe('minutesOfDay', () => {
    it('converts between times and minutes', () => {
        expect(minutesOfDay('00:00')).toBe(0)
        expect(minutesOfDay('23:59')).toBe(1439)
        expect(timeFromMinutes(570)).toBe('09:30')
    })
})
