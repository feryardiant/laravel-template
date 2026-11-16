import { describe, expect, it, vi } from 'vite-plus/test'

import { localTodayKey } from '@/util/date'

import { presetToday } from './date-picker'

describe('presetToday', () => {
    it('prefers the server-provided date over the browser date', () => {
        vi.useFakeTimers()
        vi.setSystemTime(new Date(2026, 10, 8, 12))

        try {
            expect(localTodayKey()).toBe('2026-11-08')
            expect(presetToday('2026-11-15')).toBe('2026-11-15')
        } finally {
            vi.useRealTimers()
        }
    })

    it('refreshes the civil date in the configured timezone', () => {
        vi.useFakeTimers()
        vi.setSystemTime(new Date('2026-11-08T23:30:00Z'))

        try {
            expect(presetToday('2026-11-08', 'Asia/Jakarta')).toBe('2026-11-09')
        } finally {
            vi.useRealTimers()
        }
    })

    it('falls back to the browser date', () => {
        expect(presetToday(null)).toBe(localTodayKey())
    })
})
