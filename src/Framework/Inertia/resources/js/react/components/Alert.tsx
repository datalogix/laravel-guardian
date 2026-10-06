import type { ReactNode } from 'react'

export default function Alert({ variant = 'info', className = '', children }: { variant?: 'info' | 'error'; className?: string; children: ReactNode }) {
    const colors =
        variant === 'error'
            ? 'border-red-200 bg-red-50 text-red-700 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300'
            : 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-300'

    return (
        <div role={variant === 'error' ? 'alert' : 'status'} className={`rounded-md border px-3 py-2 text-sm ${colors} ${className}`}>
            {children}
        </div>
    )
}
