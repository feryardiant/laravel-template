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

export type CalendarMode = 'single' | 'multiple' | 'range'

export interface CalendarLabels {
    today: string
    previousMonth: string
    nextMonth: string
    selected: string
    unavailable: string
    rangeTooShort: string
    rangeTooLong: string
    rangeUnavailable: string
}

export interface CalendarConfig {
    mode: CalendarMode
    draft: boolean
    value: unknown
    model: string | null
    live: boolean
    locale: string
    startDay: number | null
    months: number
    fixedWeeks: boolean
    min: string | null
    max: string | null
    unavailable: string[]
    minRange: number | null
    maxRange: number | null
    openTo: string | null
    navigation: boolean
    static: boolean
    withToday: boolean
    rootId: string
    cellClass: string
    formInputId: string | null
    labels: CalendarLabels
}

export interface CalendarCell {
    id: string
    key: string
    day: number
    label: string
    outside: boolean
    today: boolean
    disabled: boolean
    unavailable: boolean
    selected: boolean
    inRange: boolean
    rangeStart: boolean
    rangeEnd: boolean
    active: boolean
    tabIndex: number
}

export interface CalendarMonth {
    key: string
    year: number
    month: number
    label: string
    weekdays: string[]
    weeks: CalendarCell[][]
}

export type DatePickerMode = 'single' | 'range'

export interface DatePickerLabels {
    placeholder: string
    placeholderRange: string
    clear: string
    cancel: string
    select: string
    selectRange: string
    presets: string
    presetLabels: Record<string, string>
}

export interface DatePickerConfig {
    mode: DatePickerMode
    value: unknown
    model: string | null
    live: boolean
    locale: string
    startDay: number | null
    min: string | null
    max: string | null
    withConfirmation: boolean
    withPresets: boolean
    presetKeys: string[]
    placeholder: string | null
    clearable: boolean
    disabled: boolean
    rootId: string
    triggerId: string
    popoverId: string
    formInputId: string | null
    formStartId: string | null
    formEndId: string | null
    labels: DatePickerLabels
}
