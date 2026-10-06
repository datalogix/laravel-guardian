interface Props {
    label: string
    name: string
    value: string
    onChange: (value: string) => void
    type?: string
    error?: string
    autoComplete?: string
    inputMode?: 'email' | 'numeric' | 'text'
    placeholder?: string
    hint?: string
    required?: boolean
    autoFocus?: boolean
    readOnly?: boolean
}

export default function Field({
    label,
    name,
    value,
    onChange,
    type = 'text',
    error,
    autoComplete,
    inputMode,
    placeholder,
    hint,
    required = true,
    autoFocus = false,
    readOnly = false,
}: Props) {
    return (
        <div className="space-y-1.5">
            <label htmlFor={name} className="block text-sm font-medium text-zinc-700 dark:text-zinc-200">
                {label}
            </label>

            <input
                id={name}
                name={name}
                type={type}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                autoComplete={autoComplete}
                inputMode={inputMode}
                placeholder={placeholder}
                required={required}
                autoFocus={autoFocus}
                readOnly={readOnly}
                aria-invalid={error ? 'true' : undefined}
                aria-describedby={error ? `${name}-error` : hint ? `${name}-hint` : undefined}
                className={`block w-full rounded-md border bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm placeholder:text-zinc-400 focus:outline-none focus:ring-2 read-only:bg-zinc-100 dark:bg-zinc-950 dark:text-zinc-100 dark:read-only:bg-zinc-900 ${
                    error
                        ? 'border-red-500 focus:ring-red-500/30'
                        : 'border-zinc-300 focus:ring-zinc-900/20 dark:border-zinc-700 dark:focus:ring-white/20'
                }`}
            />

            {hint && !error && (
                <p id={`${name}-hint`} className="text-xs text-zinc-500 dark:text-zinc-400">
                    {hint}
                </p>
            )}
            {error && (
                <p id={`${name}-error`} role="alert" className="text-sm text-red-600 dark:text-red-400">
                    {error}
                </p>
            )}
        </div>
    )
}
