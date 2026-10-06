import { useForm } from '@inertiajs/react'
import type { FormEvent } from 'react'
import AuthLayout from './components/AuthLayout'
import Button from './components/Button'
import Field from './components/Field'
import { identifierField } from './components/identifier'
import type { IdentifierKey } from './components/types'
import { useTranslator } from './components/translate'

interface Props {
    identifierKey: IdentifierKey
    provider: string | null
    email: string | null
    status: string | null
    endpoints: { submit: string }
}

export default function OAuthCompleteRegistration({ identifierKey, provider, email, status, endpoints }: Props) {
    const t = useTranslator()

    const identifier = identifierField(identifierKey, t)
    const { data, setData, post, processing, errors } = useForm({ login: '' })

    const providerName = provider ? provider.charAt(0).toUpperCase() + provider.slice(1) : 'your provider'

    function submit(event: FormEvent) {
        event.preventDefault()
        post(endpoints.submit)
    }

    return (
        <AuthLayout
            title={t('Complete your registration')}
            description={`You signed in with ${providerName}${email ? ` as ${email}` : ''}. One more detail and your account is ready.`}
            status={status}
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
                    {t('Complete registration')}
                </Button>
            </form>
        </AuthLayout>
    )
}
