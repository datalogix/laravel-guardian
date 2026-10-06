import { Link, useForm } from '@inertiajs/react'
import type { FormEvent } from 'react'
import AuthLayout from './components/AuthLayout'
import Button from './components/Button'
import Field from './components/Field'
import { identifierField } from './components/identifier'
import type { IdentifierKey } from './components/types'
import { useTranslator } from './components/translate'

interface Props {
    identifierKey: IdentifierKey
    loginUrl: string | null
    status: string | null
    endpoints: { submit: string }
}

export default function ForgotPassword({ identifierKey, loginUrl, status, endpoints }: Props) {
    const t = useTranslator()

    const identifier = identifierField(identifierKey, t)
    const { data, setData, post, processing, errors } = useForm({ login: '' })

    function submit(event: FormEvent) {
        event.preventDefault()
        post(endpoints.submit)
    }

    return (
        <AuthLayout
            title={t('Forgot your password?')}
            description={`Enter your ${identifier.label} and we'll send you a password reset link.`}
            status={status}
            footer={
                loginUrl && (
                    <>
                        {t('Or, return to')}{' '}
                        <Link href={loginUrl} className="font-medium text-zinc-900 underline underline-offset-4 hover:no-underline dark:text-zinc-100">
                            {t('sign in')}
                        </Link>
                    </>
                )
            }
        >
            <form className="space-y-4" onSubmit={submit}>
                <Field
                    name="login"
                    label={identifier.label}
                    type={identifier.type}
                    autoComplete={identifier.autocomplete}
                    inputMode={identifier.inputmode}
                    placeholder={identifier.placeholder}
                    value={data.login}
                    onChange={(value) => setData('login', value)}
                    error={errors.login}
                    autoFocus
                />

                <Button type="submit" full processing={processing}>
                    {t('Send reset link')}
                </Button>
            </form>
        </AuthLayout>
    )
}
