/**
 * Alpine factory registration for the first-party UI components.
 *
 * Livewire bundles Alpine; these factories are registered on `alpine:init` and
 * are consumed by the Blade components in `resources/views/components/ui`.
 */

import { autocompleteData } from './autocomplete'
import { calendarData } from './calendar'
import { datePickerData } from './date-picker'
import { datetimePickerData } from './datetime-picker'
import { fileUploadData } from './file-upload'
import { selectData } from './select'
import { timePickerData } from './time-picker'

document.addEventListener('alpine:init', () => {
    globalThis.Alpine.data('uiSelect', selectData)
    globalThis.Alpine.data('uiAutocomplete', autocompleteData)
    globalThis.Alpine.data('uiCalendar', calendarData)
    globalThis.Alpine.data('uiDatePicker', datePickerData)
    globalThis.Alpine.data('uiTimePicker', timePickerData)
    globalThis.Alpine.data('uiDatetimePicker', datetimePickerData)
    globalThis.Alpine.data('uiFileUpload', fileUploadData)
})
