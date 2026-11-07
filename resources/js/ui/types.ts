export interface WireLike {
    get(name: string): unknown
    set(name: string, value: unknown, live?: boolean): void
    $watch(name: string, callback: (value: unknown) => void): () => void
}

export interface SelectConfig {
    variant: 'listbox' | 'pillbox'
    multiple: boolean
    value: unknown
    model: string | null
    live: boolean
    disabled: boolean
    empty: string
    selectedSuffix: string
    triggerId: string
    anchorId: string
    popoverId: string
    listboxId: string
    formInputId: string | null
}

export interface AutocompleteConfig {
    value: string
    model: string | null
    live: boolean
    disabled: boolean
    readonly: boolean
    searchUrl: string | null
    minLength: number
    debounce: number
    empty: string
    loading: string
    triggerId: string
    popoverId: string
    listboxId: string
    formInputId: string | null
}

export interface ListboxOption {
    el: HTMLElement
    value: string
    label: string
    selectedLabel: string
    keywords: string
    disabled: boolean
}

export interface RemoteItem {
    label: string
}

export interface SelectedOption {
    value: string
    label: string
    selectedLabel: string
}
