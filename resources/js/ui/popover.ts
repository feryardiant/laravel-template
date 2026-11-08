export interface PopoverOptions {
    onOpen?: () => void
    onClose?: () => void
    focusTarget?: HTMLElement | null
    fullWidthOnTouch?: boolean
}

export interface PopoverController {
    open: () => void
    close: () => void
    toggle: () => void
    isOpen: () => boolean
    reposition: (allowFlip?: boolean) => void
    destroy: () => void
}

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
