/**
 * Native popover controller shared by the select, autocomplete and picker
 * components.
 *
 * The popover element must use `popover="manual"`. The controller owns the
 * top-layer lifecycle, open state, trigger ARIA, fixed positioning with
 * viewport clamping and flip, outside-pointer/Escape close, scroll locking,
 * focus restoration and optional full-width touch layout. Callers own only the
 * value and the reason for closing.
 */

export interface PopoverOptions {
    /** Element used for positioning; defaults to the trigger. */
    anchor?: HTMLElement | null
    /** Called after the popover enters the top layer. */
    onOpen?: () => void
    /** Called after the popover leaves the top layer. */
    onClose?: () => void
    /** Element to focus on close; defaults to the trigger. */
    focusTarget?: HTMLElement | null
    /** Use the viewport width (with insets) on touch devices. */
    fullWidthOnTouch?: boolean
    /** Never shrink below the trigger width; defaults to `true`. */
    matchTriggerWidth?: boolean
    /** Prevent document scrolling while open. Use for dialog-style popovers. */
    scrollLock?: boolean
}

/**
 * Keep Tab and Shift+Tab inside a popover by wrapping at its edges.
 */
export function trapTabFocus(container: HTMLElement, event: KeyboardEvent): void {
    if (event.key !== 'Tab') {
        return
    }

    const focusables = Array.from(
        container.querySelectorAll<HTMLElement>(
            'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]',
        ),
    ).filter((element) => element.tabIndex >= 0 && element.offsetWidth > 0)

    if (focusables.length === 0) {
        return
    }

    const first = focusables[0]
    const last = focusables[focusables.length - 1]

    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault()
        last.focus()
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault()
        first.focus()
    }
}

/** Imperative handle returned by {@link createPopover}. */
export interface PopoverController {
    open: () => void
    close: () => void
    toggle: () => void
    isOpen: () => boolean
    /** Re-anchor after content or viewport changes; optionally allow a flip. */
    reposition: (allowFlip?: boolean) => void
    destroy: () => void
}

let scrollLocks = 0
let previousStyles: {
    overflow: string
    position: string
    top: string
    width: string
    paddingRight: string
    scrollY: number
} | null = null

/**
 * Lock document scroll and compensate for the disappearing scrollbar.
 *
 * `overflow: hidden` alone still allows programmatic scrolling, so the body is
 * pinned to the current offset and restored when the last popover closes.
 */
function lockDocumentScroll(): void {
    if (scrollLocks++ > 0) {
        return
    }

    const root = document.documentElement
    const body = document.body
    const scrollbar = window.innerWidth - root.clientWidth

    previousStyles = {
        overflow: root.style.overflow,
        position: body.style.position,
        top: body.style.top,
        width: body.style.width,
        paddingRight: body.style.paddingRight,
        scrollY: window.scrollY,
    }

    root.style.overflow = 'hidden'

    // Another lock owner (e.g. a Flux modal) already pinned the body; leave its
    // offset alone and let it restore on its own close.
    if (getComputedStyle(body).position === 'fixed') {
        return
    }

    body.style.position = 'fixed'
    body.style.top = `-${previousStyles.scrollY}px`
    body.style.width = '100%'

    if (scrollbar > 0) {
        body.style.paddingRight = `${scrollbar}px`
    }
}

/** Release a document scroll lock, restoring the previous styles and offset. */
function unlockDocumentScroll(): void {
    if (scrollLocks === 0 || --scrollLocks > 0 || previousStyles === null) {
        return
    }

    const styles = previousStyles
    previousStyles = null

    document.documentElement.style.overflow = styles.overflow
    document.body.style.position = styles.position
    document.body.style.top = styles.top
    document.body.style.width = styles.width
    document.body.style.paddingRight = styles.paddingRight
    window.scrollTo(0, styles.scrollY)
}

/**
 * Attach a native popover to a trigger.
 *
 * Position is fixed and clamped to the viewport; the popover flips above when
 * there is more room there. Closing restores focus only when focus was inside
 * the popover, so an outside click can keep its own target.
 */
export function createPopover(
    trigger: HTMLElement,
    popover: HTMLElement,
    options: PopoverOptions = {},
): PopoverController {
    let open = false
    let placement: 'bottom' | 'top' = 'bottom'
    let resizeObserver: ResizeObserver | null = null

    const anchor = options.anchor ?? trigger
    const gap = 4
    const viewportGap = 8

    const reposition = (allowFlip = false): void => {
        if (!open) {
            return
        }

        // Dialog pickers lock document scrolling, so the popover itself must be
        // able to scroll to its footer on short viewports.
        if (options.scrollLock === true) {
            popover.style.maxHeight = `${Math.max(0, window.innerHeight - 2 * viewportGap)}px`
            popover.style.overflowY = 'auto'
        }

        const triggerRect = anchor.getBoundingClientRect()
        const maxWidth = Math.max(0, window.innerWidth - 2 * viewportGap)
        const fullWidth = options.fullWidthOnTouch === true && window.matchMedia('(hover: none)').matches
        const contentWidth = popover.offsetWidth
        const minimumWidth =
            options.matchTriggerWidth === false ? contentWidth : Math.max(contentWidth, triggerRect.width)
        const popoverWidth = fullWidth ? maxWidth : Math.min(minimumWidth, maxWidth)
        const popoverHeight = popover.offsetHeight
        const spaceBelow = window.innerHeight - triggerRect.bottom - gap - viewportGap
        const spaceAbove = triggerRect.top - gap - viewportGap

        if (allowFlip) {
            placement = popoverHeight > spaceBelow && spaceAbove > spaceBelow ? 'top' : 'bottom'
        } else if (placement === 'bottom' && popoverHeight > spaceBelow && spaceAbove > spaceBelow) {
            placement = 'top'
        }

        popover.style.minWidth = `${popoverWidth}px`
        popover.style.maxWidth = `${maxWidth}px`
        popover.style.width = fullWidth ? `${popoverWidth}px` : ''
        popover.style.position = 'fixed'

        let top: number

        if (placement === 'top') {
            top = Math.max(viewportGap, triggerRect.top - popoverHeight - gap)
        } else {
            top = triggerRect.bottom + gap

            if (top + popoverHeight > window.innerHeight - viewportGap) {
                top = Math.max(viewportGap, window.innerHeight - viewportGap - popoverHeight)
            }
        }

        let left = fullWidth ? viewportGap : triggerRect.left

        if (left + popoverWidth > window.innerWidth - viewportGap) {
            left = triggerRect.right - popoverWidth
        }

        left = Math.max(viewportGap, Math.min(left, window.innerWidth - popoverWidth - viewportGap))

        popover.style.top = `${Math.round(top)}px`
        popover.style.left = `${Math.round(left)}px`
    }

    const onPointerDown = (event: PointerEvent): void => {
        const target = event.target

        if (!(target instanceof Node)) {
            return
        }

        if (popover.contains(target) || anchor.contains(target)) {
            return
        }

        closePopover()
    }

    const onKeyDown = (event: KeyboardEvent): void => {
        if (event.key !== 'Escape') {
            return
        }

        event.preventDefault()
        closePopover()
        const target = options.focusTarget ?? trigger
        target.focus()
    }

    const onViewportChange = (): void => {
        reposition(true)
    }

    const attach = (): void => {
        window.addEventListener('resize', onViewportChange)
        window.addEventListener('scroll', onViewportChange, true)
        document.addEventListener('pointerdown', onPointerDown)
        document.addEventListener('keydown', onKeyDown)

        if (typeof ResizeObserver !== 'undefined') {
            resizeObserver = new ResizeObserver(() => reposition())
            resizeObserver.observe(popover)
        }

        if (options.scrollLock === true) {
            lockDocumentScroll()
        }
    }

    const detach = (): void => {
        window.removeEventListener('resize', onViewportChange)
        window.removeEventListener('scroll', onViewportChange, true)
        document.removeEventListener('pointerdown', onPointerDown)
        document.removeEventListener('keydown', onKeyDown)
        resizeObserver?.disconnect()
        resizeObserver = null
    }

    const openPopover = (): void => {
        if (open) {
            return
        }

        try {
            popover.showPopover()
        } catch {
            return
        }

        open = true
        trigger.setAttribute('aria-expanded', 'true')

        const triggerRect = anchor.getBoundingClientRect()
        const popoverHeight = popover.offsetHeight
        const spaceBelow = window.innerHeight - triggerRect.bottom - gap - viewportGap
        const spaceAbove = triggerRect.top - gap - viewportGap

        placement = popoverHeight > spaceBelow && spaceAbove > spaceBelow ? 'top' : 'bottom'

        attach()
        reposition()
        options.onOpen?.()
    }

    const closePopover = (): void => {
        if (!open) {
            return
        }

        // Capture this before hidePopover() blurs the active descendant.
        const restoreFocus = popover.contains(document.activeElement)

        if (popover.matches(':popover-open')) {
            popover.hidePopover()
        }

        // Focus while `open` is still true so a trigger focus handler cannot
        // immediately reopen the popover.
        if (restoreFocus) {
            ;(options.focusTarget ?? trigger).focus()
        }

        open = false
        trigger.setAttribute('aria-expanded', 'false')

        // Drop the inline sizing so the next open measures fresh content.
        popover.style.minWidth = ''
        popover.style.maxWidth = ''
        popover.style.width = ''
        popover.style.maxHeight = ''
        popover.style.overflowY = ''

        if (options.scrollLock === true) {
            unlockDocumentScroll()
        }

        detach()
        options.onClose?.()
    }

    trigger.setAttribute('aria-expanded', 'false')

    return {
        open: openPopover,
        close: closePopover,
        toggle: () => {
            if (open) {
                closePopover()
            } else {
                openPopover()
            }
        },
        isOpen: () => open,
        reposition,
        destroy: () => {
            closePopover()
            detach()
        },
    }
}
