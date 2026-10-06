import { Link, useForm } from '@inertiajs/react'
import type { FormEvent } from 'react'
import AuthLayout from './components/AuthLayout'
import Button from './components/Button'
import Checkbox from './components/Checkbox'
import Field from './components/Field'
import { identifierField } from './components/identifier'
import OAuthProviders from './components/OAuthProviders'
import type { IdentifierKey, OAuthProvider } from './components/types'
import { useTranslator } from './components/translate'

interface Props {
    identifierKey: IdentifierKey
    loginUrl: string | null
    termsUrl: string | null
    oauthProviders: OAuthProvider[]
    status: string | null
    endpoints: { submit: string }
}

export default function SignUp({ identifierKey, loginUrl, termsUrl, oauthProviders, status, endpoints }: Props) {
    const t = useTranslator()

    const identifier = identifierField(identifierKey, t)
    const needsEmail = identifierKey !== 'email'
    const { data, setData, post, processing, errors, reset, transform } = useForm({
        name: '',
        login: '',
        email: '',
        password: '',
        password_confirmation: '',
        terms: false,
    })

    function submit(event: FormEvent) {
        event.preventDefault()
        transform(({ email, ...payload }) => (needsEmail ? { ...payload, email } : payload))
        post(endpoints.submit, { onFinish: () => reset('password', 'password_confirmation') })
    }

    return (
        <AuthLayout
            title={t('Create an account')}
            description={t('Enter your details to get started')}
            status={status}
            footer={
                loginUrl && (
                    <>
                        {t('Already have an account?')}{' '}
                        <Link href={loginUrl} className="font-medium text-zinc-900 underline underline-offset-4 hover:no-underline dark:text-zinc-100">
                            {t('Sign in')}
                        </Link>
                    </>
                )
            }
        >
            <form className="space-y-4" onSubmit={submit}>
                <Field name="name" label={t('Name')} autoComplete="name" value={data.name} onChange={(value) => setData('name', value)} error={errors.name} autoFocus />

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
                />

                {needsEmail && (
                    <Field
                        name="email"
                        label={t('Email')}
                        type="email"
                        autoComplete="email"
                        inputMode="email"
                        placeholder="email@example.com"
                        value={data.email}
                        onChange={(value) => setData('email', value)}
                        error={errors.email}
                    />
                )}

                <Field
                    name="password"
                    label={t('Password')}
                    type="password"
                    autoComplete="new-password"
                    value={data.password}
                    onChange={(value) => setData('password', value)}
                    error={errors.password}
                />

                <Field
                    name="password_confirmation"
                    label={t('Confirm password')}
                    type="password"
                    autoComplete="new-password"
                    value={data.password_confirmation}
                    onChange={(value) => setData('password_confirmation', value)}
                    error={errors.password_confirmation}
                />

                {termsUrl && (
                    <div className="space-y-1.5">
                        <Checkbox name="terms" checked={data.terms} onChange={(checked) => setData('terms', checked)}>
                            {t('I accept the terms and conditions')}
                        </Checkbox>
                        <a
                            href={termsUrl}
                            target="_blank"
                            rel="noopener"
                            className="text-sm font-medium text-zinc-900 underline underline-offset-4 hover:no-underline dark:text-zinc-100"
                        >
                            {t('Read the terms and conditions')}
                        </a>
                        {errors.terms && (
                            <p role="alert" className="text-sm text-red-600 dark:text-red-400">
                                {errors.terms}
                            </p>
                        )}
                    </div>
                )}

                <Button type="submit" full processing={processing}>
                    {t('Create account')}
                </Button>
            </form>

            <OAuthProviders providers={oauthProviders} label={t('Or sign up with')} />
        </AuthLayout>
    )
}
