/**
 * Livewire and plain-form binding helpers shared by the UI Alpine factories.
 *
 * A bound component reads from the Livewire property when a model is present and
 * from the Blade fallback otherwise. Writes go through `$wire.set()` or through a
 * hidden input when the component is used outside Livewire.
 */

/** Minimal Livewire surface used by the factories. */
export interface WireLike {
    get(name: string): unknown
    set(name: string, value: unknown, live?: boolean): void
    $watch(name: string, callback: (value: unknown) => void): () => void
}

/** Coerce a scalar to the string used by DOM values. */
export function stringValue(value: unknown): string {
    if (typeof value === 'string') {
        return value
    }

    if (typeof value === 'number' || typeof value === 'boolean' || typeof value === 'bigint') {
        return String(value)
    }

    return ''
}

/** Normalize a scalar, comma-separated string or array to non-empty strings. */
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

/**
 * Read a Livewire property, falling back to the initial Blade value.
 *
 * The fallback is used for plain forms and whenever the property is missing.
 */
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

/**
 * Write a value through Livewire, or mirror it into a hidden form input.
 *
 * The hidden-input path dispatches `input` and `change` so plain forms and other
 * listeners see the update.
 */
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

/** Subscribe to Livewire model changes; a no-op when there is no model. */
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
