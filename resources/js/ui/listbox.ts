import type { ListboxOption } from './types'

/**
 * DOM scanning and filtering for the select listbox.
 *
 * Options are marked with `data-ui-option`; groups with `role="group"`.
 */

/** Normalize search text for accent- and case-insensitive matching. */
export function normalizeSearch(value: string): string {
    return value
        .toLocaleLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim()
}

/** Read option metadata from the rendered DOM. */
export function scanOptions(root: HTMLElement | null): ListboxOption[] {
    if (root === null) {
        return []
    }

    return Array.from(root.querySelectorAll<HTMLElement>('[data-ui-option]')).map((el) => {
        const label = el.dataset.label ?? el.textContent?.trim() ?? ''
        const value = el.dataset.value ?? label

        return {
            el,
            value,
            label,
            selectedLabel: el.dataset.selectedLabel ?? label,
            keywords: el.dataset.keywords ?? '',
            disabled: el.hasAttribute('data-disabled'),
        }
    })
}

/** Match an option against its label and optional keywords. */
export function optionMatches(option: ListboxOption, query: string): boolean {
    if (query === '') {
        return true
    }

    return normalizeSearch(`${option.label} ${option.keywords}`).includes(normalizeSearch(query))
}

/**
 * Toggle option visibility and hide groups whose options are all filtered out.
 */
export function applyFilter(root: HTMLElement | null, options: ListboxOption[], query: string): ListboxOption[] {
    const visible: ListboxOption[] = []

    for (const option of options) {
        const show = optionMatches(option, query)

        option.el.toggleAttribute('hidden', !show)
        option.el.toggleAttribute('data-hidden', !show)

        if (show) {
            visible.push(option)
        }
    }

    root?.querySelectorAll<HTMLElement>('[role="group"]').forEach((group) => {
        const hasVisibleOption = group.querySelector<HTMLElement>('[data-ui-option]:not([hidden])') !== null

        group.toggleAttribute('hidden', !hasVisibleOption)
    })

    return visible
}

/** Give every option a stable id for `aria-activedescendant`. */
export function assignOptionIds(options: ListboxOption[], prefix: string): void {
    options.forEach((option, index) => {
        option.el.id = `${prefix}-option-${index}`
    })
}
