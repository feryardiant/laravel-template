import { autocompleteData } from './autocomplete'
import { calendarData } from './calendar'
import { selectData } from './select'

document.addEventListener('alpine:init', () => {
    globalThis.Alpine.data('uiSelect', selectData)
    globalThis.Alpine.data('uiAutocomplete', autocompleteData)
    globalThis.Alpine.data('uiCalendar', calendarData)
})
