import { usePage } from '@inertiajs/react'

export type Translator = (key: string, replace?: Record<string, string | number>) => string

export function useTranslator(): Translator {
    const page = usePage<{ translations?: Record<string, string> }>()

    return (key, replace = {}) =>
        Object.entries(replace)
            // The longest first, like Laravel: :count would otherwise eat into :count_total.
            .sort(([a], [b]) => b.length - a.length)
            .reduce((line, [name, value]) => line.split(`:${name}`).join(String(value)), page.props.translations?.[key] ?? key)
}
