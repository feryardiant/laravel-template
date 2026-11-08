import { autocompleteData } from './autocomplete'
import { calendarData } from './calendar'
import { datePickerData } from './date-picker'
import { selectData } from './select'

document.addEventListener('alpine:init', () => {
    globalThis.Alpine.data('uiSelect', selectData)
    globalThis.Alpine.data('uiAutocomplete', autocompleteData)
    globalThis.Alpine.data('uiCalendar', calendarData)
    globalThis.Alpine.data('uiDatePicker', datePickerData)
})
