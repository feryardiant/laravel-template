/**
 * Resolve the locale used for `Intl` formatting.
 *
 * An explicit component `locale` wins. Otherwise the document language from
 * `<html lang>` is used, with the browser language as a fallback.
 */
export function resolveLocale(locale: string | null | undefined): string {
    const explicit = locale?.trim()

    if (explicit !== undefined && explicit !== '') {
        return explicit.replace(/_/g, '-')
    }

    const documentLocale = typeof document === 'undefined' ? '' : (document.documentElement.lang || '').trim()

    if (documentLocale !== '') {
        return documentLocale.replace(/_/g, '-')
    }

    if (typeof navigator !== 'undefined' && navigator.language) {
        return navigator.language
    }

    return 'en'
}
