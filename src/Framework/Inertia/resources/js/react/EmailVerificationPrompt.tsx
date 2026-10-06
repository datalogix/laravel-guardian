import { Link, useForm } from '@inertiajs/react'
import type { FormEvent } from 'react'
import AuthLayout from './components/AuthLayout'
import Button from './components/Button'
import { useTranslator } from './components/translate'

interface Props {
    logoutUrl: string | null
    status: string | null
    endpoints: { submit: string }
}

export default function EmailVerificationPrompt({ logoutUrl, status, endpoints }: Props) {
    const t = useTranslator()

    const { post, processing } = useForm({})

    function resend(event: FormEvent) {
        event.preventDefault()
        post(endpoints.submit)
    }

    return (
        <AuthLayout
            title={t('Verify your email')}
            description={t("Click the link we emailed you to verify your address. Didn't get it? We can send you another.")}
            status={status}
            footer={
                logoutUrl && (
                    <Link
                        href={logoutUrl}
                        method="post"
                        as="button"
                        type="button"
                        className="font-medium text-zinc-900 underline underline-offset-4 hover:no-underline dark:text-zinc-100"
                    >
                        {t('Log out')}
                    </Link>
                )
            }
        >
            <form className="space-y-4" onSubmit={resend}>
                <Button type="submit" full processing={processing}>
                    {t('Resend verification email')}
                </Button>
            </form>
        </AuthLayout>
    )
}
