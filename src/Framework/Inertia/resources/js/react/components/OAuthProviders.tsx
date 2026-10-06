import type { OAuthProvider } from './types'
import { useTranslator } from './translate'

const title = (name: string) => name.charAt(0).toUpperCase() + name.slice(1)

export default function OAuthProviders({ providers, label }: { providers: OAuthProvider[]; label?: string }) {
    const t = useTranslator()

    if (providers.length === 0) {
        return null
    }

    return (
        <div>
            <div className="relative">
                <div className="absolute inset-0 flex items-center" aria-hidden="true">
                    <div className="w-full border-t border-zinc-200 dark:border-zinc-700" />
                </div>
                <div className="relative flex justify-center text-xs uppercase">
                    <span className="bg-white px-2 text-zinc-500 dark:bg-zinc-900 dark:text-zinc-400">{label ?? t('Or continue with')}</span>
                </div>
            </div>

            {/* Plain links, not Inertia visits: the browser has to follow the redirect to the provider. */}
            <div className="mt-4 grid gap-2">
                {providers.map((provider) => (
                    <a
                        key={provider.name}
                        href={provider.url}
                        className="inline-flex items-center justify-center rounded-md border border-zinc-300 bg-white px-4 py-2 text-sm font-medium text-zinc-900 hover:bg-zinc-50 focus:outline-none focus:ring-2 focus:ring-zinc-900/20 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:hover:bg-zinc-800"
                    >
                        {title(provider.name)}
                    </a>
                ))}
            </div>
        </div>
    )
}
