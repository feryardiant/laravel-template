/**
 * Native popover controller shared by the select, autocomplete and picker
 * components.
 *
 * The popover element must use `popover="manual"`. The controller owns the
 * top-layer lifecycle, fixed positioning with viewport clamping and flip,
 * outside-pointer/Escape close, focus restoration and optional full-width touch
 * layout.
 */

export interface PopoverOptions {
    /** Called after the popover enters the top layer. */
    onOpen?: () => void
    /** Called before focus is restored on close. */
    onClose?: () => void
    /** Element to focus on close; defaults to the trigger. */
    focusTarget?: HTMLElement | null
    /** Use the viewport width (with insets) on touch devices. */
    fullWidthOnTouch?: boolean
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

    const gap = 4
    const viewportGap = 8

    const reposition = (allowFlip = false): void => {
        if (!open) {
            return
        }

        const triggerRect = trigger.getBoundingClientRect()
        const maxWidth = Math.max(0, window.innerWidth - 2 * viewportGap)
        const fullWidth = options.fullWidthOnTouch === true && window.matchMedia('(hover: none)').matches
        const popoverWidth = fullWidth ? maxWidth : Math.min(Math.max(popover.offsetWidth, triggerRect.width), maxWidth)
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

        if (popover.contains(target) || trigger.contains(target)) {
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

        const triggerRect = trigger.getBoundingClientRect()
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

        open = false

        if (popover.matches(':popover-open')) {
            popover.hidePopover()
        }

        detach()
        options.onClose?.()

        if (restoreFocus) {
            ;(options.focusTarget ?? trigger).focus()
        }
    }

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
