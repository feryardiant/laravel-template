export interface PopoverOptions {
    onOpen?: () => void
    onClose?: () => void
    focusTarget?: HTMLElement | null
}

export interface PopoverController {
    open: () => void
    close: () => void
    toggle: () => void
    isOpen: () => boolean
    reposition: () => void
    destroy: () => void
}

export function createPopover(
    trigger: HTMLElement,
    popover: HTMLElement,
    options: PopoverOptions = {},
): PopoverController {
    let open = false

    const reposition = (): void => {
        if (!open) {
            return
        }

        const triggerRect = trigger.getBoundingClientRect()
        const popoverWidth = Math.max(popover.offsetWidth, triggerRect.width)
        const popoverHeight = popover.offsetHeight
        const gap = 4
        const viewportGap = 8

        popover.style.minWidth = `${popoverWidth}px`
        popover.style.position = 'fixed'

        let top = triggerRect.bottom + gap

        if (
            top + popoverHeight > window.innerHeight - viewportGap &&
            triggerRect.top - popoverHeight - gap >= viewportGap
        ) {
            top = triggerRect.top - popoverHeight - gap
        }

        let left = triggerRect.left

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

        close()
    }

    const onKeyDown = (event: KeyboardEvent): void => {
        if (event.key !== 'Escape') {
            return
        }

        event.preventDefault()
        close()
        const target = options.focusTarget ?? trigger
        target.focus()
    }

    const attach = (): void => {
        window.addEventListener('resize', reposition)
        window.addEventListener('scroll', reposition, true)
        document.addEventListener('pointerdown', onPointerDown)
        document.addEventListener('keydown', onKeyDown)
    }

    const detach = (): void => {
        window.removeEventListener('resize', reposition)
        window.removeEventListener('scroll', reposition, true)
        document.removeEventListener('pointerdown', onPointerDown)
        document.removeEventListener('keydown', onKeyDown)
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
        attach()
        reposition()
        options.onOpen?.()
    }

    const closePopover = (): void => {
        if (!open) {
            return
        }

        open = false

        if (popover.matches(':popover-open')) {
            popover.hidePopover()
        }

        detach()
        options.onClose?.()

        if (popover.contains(document.activeElement)) {
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
