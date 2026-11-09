/**
 * Alpine factory registration for the first-party UI components.
 *
 * Livewire bundles Alpine; these factories are registered on `alpine:init` and
 * are consumed by the Blade components in `resources/views/components/ui`.
 *
 * Known limitations:
 * - The Blade components render once (`wire:ignore`), so server-rendered
 *   options and `min`/`max` bounds are read at mount. Remount with a changing
 *   `wire:key` when those values can change after the first render.
 * - Popovers close on Escape and outside pointerdown. A picker nested inside a
 *   Flux modal can close both when Escape reaches the document twice.
 * - Dialog pickers lock document scroll. A Flux modal closed programmatically
 *   while a picker is open can restore its stale `overflow: hidden`.
 */

import { autocompleteData } from './autocomplete'
import { calendarData } from './calendar'
import { datePickerData } from './date-picker'
import { datetimePickerData } from './datetime-picker'
import { fileUploadData } from './file-upload'
import { selectData } from './select'
import { timePickerData } from './time-picker'

export function createUi() {
    Alpine.data('uiSelect', selectData)
    Alpine.data('uiAutocomplete', autocompleteData)
    Alpine.data('uiCalendar', calendarData)
    Alpine.data('uiDatePicker', datePickerData)
    Alpine.data('uiTimePicker', timePickerData)
    Alpine.data('uiDatetimePicker', datetimePickerData)
    Alpine.data('uiFileUpload', fileUploadData)
}
