import type { WireLike } from './types'

export function stringValue(value: unknown): string {
    if (typeof value === 'string') {
        return value
    }

    if (typeof value === 'number' || typeof value === 'boolean' || typeof value === 'bigint') {
        return String(value)
    }

    return ''
}

export function normalizeValues(value: unknown): string[] {
    if (Array.isArray(value)) {
        return value.map(stringValue).filter((item) => item !== '')
    }

    if (value === null || value === undefined || value === '') {
        return []
    }

    return stringValue(value)
        .split(',')
        .map((item) => item.trim())
        .filter((item) => item !== '')
}

export function readModel(wire: WireLike | null, model: string | null, fallback: unknown): unknown {
    if (wire === null || model === null) {
        return fallback
    }

    try {
        const value = wire.get(model)

        return value === undefined || value === null ? fallback : value
    } catch {
        return fallback
    }
}

export function writeModel(
    wire: WireLike | null,
    model: string | null,
    live: boolean,
    value: unknown,
    formInput: HTMLInputElement | null,
): void {
    if (wire !== null && model !== null) {
        wire.set(model, value, live)

        return
    }

    if (formInput === null) {
        return
    }

    formInput.value = Array.isArray(value) ? JSON.stringify(value) : value === null ? '' : stringValue(value)
    formInput.dispatchEvent(new Event('input', { bubbles: true }))
    formInput.dispatchEvent(new Event('change', { bubbles: true }))
}

export function watchModel(
    wire: WireLike | null,
    model: string | null,
    callback: (value: unknown) => void,
): () => void {
    if (wire === null || model === null) {
        return () => undefined
    }

    try {
        return wire.$watch(model, callback)
    } catch {
        return () => undefined
    }
}
