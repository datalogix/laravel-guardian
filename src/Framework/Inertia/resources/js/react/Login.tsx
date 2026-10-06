import { Link, useForm, usePage } from '@inertiajs/react'
import type { FormEvent } from 'react'
import Alert from './components/Alert'
import AuthLayout from './components/AuthLayout'
import Button from './components/Button'
import Checkbox from './components/Checkbox'
import Field from './components/Field'
import { identifierField } from './components/identifier'
import OAuthProviders from './components/OAuthProviders'
import type { IdentifierKey, OAuthProvider, PageErrors } from './components/types'
import { useTranslator } from './components/translate'

interface Props {
    identifierKey: IdentifierKey
    forgotPasswordUrl: string | null
    signUpUrl: string | null
    oauthProviders: OAuthProvider[]
    status: string | null
    endpoints: { submit: string }
}

export default function Login({ identifierKey, forgotPasswordUrl, signUpUrl, oauthProviders, status, endpoints }: Props) {
    const t = useTranslator()

    const identifier = identifierField(identifierKey, t)
    const { data, setData, post, processing, errors, reset } = useForm({ login: '', password: '', remember: false })

    const providerError = usePage<{ errors: PageErrors }>().props.errors?.oauth

    function submit(event: FormEvent) {
        event.preventDefault()
        post(endpoints.submit, { onFinish: () => reset('password') })
    }

    return (
        <AuthLayout
            title={t('Sign in')}
            description={t('Enter your details to access your account')}
            status={status}
            footer={
                signUpUrl && (
                    <>
                        {t("Don't have an account?")}{' '}
                        <Link href={signUpUrl} className="font-medium text-zinc-900 underline underline-offset-4 hover:no-underline dark:text-zinc-100">
                            {t('Sign up')}
                        </Link>
                    </>
                )
            }
        >
            {providerError && <Alert variant="error">{providerError}</Alert>}

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

                <div className="space-y-1.5">
                    <Field
                        name="password"
                        label={t('Password')}
                        type="password"
                        autoComplete="current-password"
                        value={data.password}
                        onChange={(value) => setData('password', value)}
                        error={errors.password}
                    />
                    {forgotPasswordUrl && (
                        <div className="text-right text-sm">
                            <Link href={forgotPasswordUrl} className="text-zinc-600 underline underline-offset-4 hover:no-underline dark:text-zinc-400">
                                {t('Forgot your password?')}
                            </Link>
                        </div>
                    )}
                </div>

                <Checkbox name="remember" checked={data.remember} onChange={(checked) => setData('remember', checked)}>
                    {t('Remember me')}
                </Checkbox>

                <Button type="submit" full processing={processing}>
                    {t('Sign in')}
                </Button>
            </form>

            <OAuthProviders providers={oauthProviders} />
        </AuthLayout>
    )
}
