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

export function createUi() {
    Alpine.data('uiSelect', selectData)
    Alpine.data('uiAutocomplete', autocompleteData)
    Alpine.data('uiCalendar', calendarData)
    Alpine.data('uiDatePicker', datePickerData)
    Alpine.data('uiTimePicker', timePickerData)
    Alpine.data('uiDatetimePicker', datetimePickerData)
    Alpine.data('uiFileUpload', fileUploadData)
}
