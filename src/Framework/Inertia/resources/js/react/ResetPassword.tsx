import { useForm } from '@inertiajs/react'
import type { FormEvent } from 'react'
import Alert from './components/Alert'
import AuthLayout from './components/AuthLayout'
import Button from './components/Button'
import Field from './components/Field'
import { identifierField } from './components/identifier'
import type { IdentifierKey } from './components/types'
import { useTranslator } from './components/translate'

interface Props {
    identifierKey: IdentifierKey
    token: string
    login: string
    status: string | null
    endpoints: { submit: string }
}

export default function ResetPassword({ identifierKey, token, login, status, endpoints }: Props) {
    const t = useTranslator()

    const identifier = identifierField(identifierKey, t)
    const { data, setData, post, processing, errors, reset } = useForm({ token, login, password: '', password_confirmation: '' })

    function submit(event: FormEvent) {
        event.preventDefault()
        post(endpoints.submit, { onFinish: () => reset('password', 'password_confirmation') })
    }

    return (
        <AuthLayout title={t('Reset your password')} description={t('Choose a new password for your account')} status={status}>
            <form className="space-y-4" onSubmit={submit}>
                {errors.token && <Alert variant="error">{errors.token}</Alert>}

                <Field
                    name="login"
                    label={identifier.label}
                    type={identifier.type}
                    autoComplete={identifier.autocomplete}
                    inputMode={identifier.inputmode}
                    value={data.login}
                    onChange={(value) => setData('login', value)}
                    error={errors.login}
                />

                <Field
                    name="password"
                    label={t('New password')}
                    type="password"
                    autoComplete="new-password"
                    value={data.password}
                    onChange={(value) => setData('password', value)}
                    error={errors.password}
                    autoFocus
                />

                <Field
                    name="password_confirmation"
                    label={t('Confirm new password')}
                    type="password"
                    autoComplete="new-password"
                    value={data.password_confirmation}
                    onChange={(value) => setData('password_confirmation', value)}
                    error={errors.password_confirmation}
                />

                <Button type="submit" full processing={processing}>
                    {t('Reset password')}
                </Button>
            </form>
        </AuthLayout>
    )
}
