import type { Alpine as AlpineType } from 'alpinejs'

declare global {
    var Alpine: AlpineType
    var Flux: {
        toast: (options: ToastOptions) => void
    }

    interface Window {
        Alpine: AlpineType
        Flux: {
            toast: (options: ToastOptions) => void
        }
        __notificationPollerStarted?: boolean
    }
}

type ToastOptions = {
    heading?: string
    text?: string
    variant?: string
    duration?: number
    link?: {
        label: string
        href: string
        navigate?: boolean
    }
}

declare global {}
