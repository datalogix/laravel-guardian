import { Head } from '@inertiajs/react'
import type { ReactNode } from 'react'
import Alert from './Alert'

interface Props {
    title: string
    description?: string
    status?: string | null
    wide?: boolean
    footer?: ReactNode
    children: ReactNode
}

export default function AuthLayout({ title, description, status, wide = false, footer, children }: Props) {
    return (
        <>
            <Head title={title} />

            <div className="flex min-h-screen flex-col items-center justify-center bg-zinc-50 px-4 py-10 dark:bg-zinc-950">
                <div className={`w-full ${wide ? 'max-w-2xl' : 'max-w-sm'}`}>
                    <div className="mb-6 text-center">
                        <h1 className="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-50">{title}</h1>
                        {description && <p className="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{description}</p>}
                    </div>

                    {status && <Alert className="mb-4">{status}</Alert>}

                    <div className="space-y-6 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        {children}
                    </div>

                    {footer && <div className="mt-4 text-center text-sm text-zinc-600 dark:text-zinc-400">{footer}</div>}
                </div>
            </div>
        </>
    )
}
