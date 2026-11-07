import { autocompleteData } from './autocomplete'
import { selectData } from './select'

document.addEventListener('alpine:init', () => {
    globalThis.Alpine.data('uiSelect', selectData)
    globalThis.Alpine.data('uiAutocomplete', autocompleteData)
})
