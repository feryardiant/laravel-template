/**
 * Shared config and state shapes for the UI Alpine factories.
 *
 * `model` is the Livewire property name, `live` controls whether writes request
 * an immediate round-trip, and `formInputId`/`formStartId`/`formEndId` point at
 * hidden inputs used when the component is rendered outside Livewire.
 */

/** Minimal Livewire surface used by the factories. */
export interface WireLike {
    get(name: string): unknown
    set(name: string, value: unknown, live?: boolean): void
    $watch(name: string, callback: (value: unknown) => void): () => void
}

/** Blade-to-Alpine config for a select. */
export interface SelectConfig {
    /** Pillbox always behaves as multiple. */
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

/** Blade-to-Alpine config for an autocomplete. */
export interface AutocompleteConfig {
    value: string
    model: string | null
    live: boolean
    disabled: boolean
    readonly: boolean
    /** When set, enables remote suggestions from `GET {searchUrl}?q=` */
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

/** One hour or minute row in a time picker. */
export interface TimeOption {
    id: string
    value: number
    label: string
    disabled: boolean
    selected: boolean
}

/** Option metadata scanned from the rendered listbox. */
export interface ListboxOption {
    el: HTMLElement
    value: string
    label: string
    selectedLabel: string
    keywords: string
    disabled: boolean
}

/** Normalized remote autocomplete result. */
export interface RemoteItem {
    label: string
}

/** Selected option rendered in the trigger or pill list. */
export interface SelectedOption {
    value: string
    label: string
    selectedLabel: string
}

export type CalendarMode = 'single' | 'multiple' | 'range'

/** Translated calendar strings. */
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

/** Blade-to-Alpine config for a calendar. */
export interface CalendarConfig {
    mode: CalendarMode
    /** When true, selection updates the grid but does not write `model`. */
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

/** Rendered state for one day cell. */
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
    /** Inside the committed range or the hover/focus preview. */
    inRange: boolean
    rangeStart: boolean
    rangeEnd: boolean
    active: boolean
    tabIndex: number
}

/** One rendered month in a one- or two-month calendar. */
export interface CalendarMonth {
    key: string
    year: number
    month: number
    label: string
    weekdays: string[]
    weeks: CalendarCell[][]
}

export type TimeFormat = 'auto' | '12-hour' | '24-hour'

/** Translated time-picker strings. */
export interface TimePickerLabels {
    hour: string
    minute: string
    am: string
    pm: string
    clear: string
    add: string
    selected: string
    selectedCount: string
    placeholder: string
    placeholderMultiple: string
}

/** Blade-to-Alpine config for a time picker. */
export interface TimePickerConfig {
    /** Keep several times and render the removable selected list. */
    multiple: boolean
    value: unknown
    model: string | null
    live: boolean
    locale: string
    format: TimeFormat
    interval: number
    min: string | null
    max: string | null
    unavailable: string[]
    openTo: string | null
    placeholder: string | null
    clearable: boolean
    disabled: boolean
    triggerId: string
    popoverId: string
    hoursId: string
    minutesId: string
    selectedId: string
    formInputId: string | null
    labels: TimePickerLabels
}

export type DateTimeMode = 'single' | 'range'

/** Translated datetime-picker strings. */
export interface DateTimePickerLabels {
    label: string
    placeholder: string
    placeholderRange: string
    start: string
    end: string
    at: string
    clear: string
    cancel: string
    select: string
    selectRange: string
    rangeInvalid: string
    hour: string
    minute: string
    am: string
    pm: string
}

/** Blade-to-Alpine config for a datetime picker. */
export interface DateTimePickerConfig {
    mode: DateTimeMode
    value: unknown
    model: string | null
    live: boolean
    locale: string
    months: number
    timeFormat: TimeFormat
    interval: number
    min: string | null
    max: string | null
    minDate: string | null
    maxDate: string | null
    /** Markers that make `min`/`max` re-resolve on every open. */
    minSource: 'now' | 'today' | null
    maxSource: 'now' | 'today' | null
    openToDate: string | null
    openToTime: string | null
    /** Marker that makes `open-to` re-resolve on every open. */
    openToSource: 'now' | 'today' | null
    minTime: string | null
    maxTime: string | null
    unavailable: string[]
    defaultStartTime: string | null
    defaultEndTime: string | null
    placeholder: string | null
    withConfirmation: boolean
    clearable: boolean
    disabled: boolean
    triggerId: string
    popoverId: string
    formInputId: string | null
    formStartId: string | null
    formEndId: string | null
    labels: DateTimePickerLabels
}

export type DatePickerMode = 'single' | 'range'

/** Translated date-picker strings. */
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

/** Blade-to-Alpine config for a date picker. */
export interface DatePickerConfig {
    mode: DatePickerMode
    value: unknown
    model: string | null
    live: boolean
    locale: string
    startDay: number | null
    min: string | null
    max: string | null
    unavailable: string[]
    minRange: number | null
    maxRange: number | null
    /** Require the footer even in single mode. */
    withConfirmation: boolean
    withPresets: boolean
    presetKeys: string[]
    placeholder: string | null
    clearable: boolean
    disabled: boolean
    triggerId: string
    popoverId: string
    formInputId: string | null
    formStartId: string | null
    formEndId: string | null
    labels: DatePickerLabels
}
