import type { ReactNode } from 'react'

interface Props {
    name: string
    checked: boolean
    onChange: (checked: boolean) => void
    children: ReactNode
}

export default function Checkbox({ name, checked, onChange, children }: Props) {
    return (
        <label htmlFor={name} className="flex items-start gap-2 text-sm text-zinc-700 dark:text-zinc-300">
            <input
                id={name}
                name={name}
                type="checkbox"
                checked={checked}
                onChange={(event) => onChange(event.target.checked)}
                className="mt-0.5 h-4 w-4 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-900/30 dark:border-zinc-600 dark:bg-zinc-950"
            />
            <span>{children}</span>
        </label>
    )
}
