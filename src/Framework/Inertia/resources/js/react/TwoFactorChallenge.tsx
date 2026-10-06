import { router, useForm } from '@inertiajs/react'
import { useState } from 'react'
import type { FormEvent } from 'react'
import AuthLayout from './components/AuthLayout'
import Button from './components/Button'
import Checkbox from './components/Checkbox'
import Field from './components/Field'
import type { TwoFactorMethod } from './components/types'
import { useTranslator } from './components/translate'

interface Props {
    method: TwoFactorMethod
    canRememberDevice: boolean
    rememberDeviceDays: number
    status: string | null
    endpoints: { submit: string; resend: string }
}

const descriptions: Record<TwoFactorMethod, string> = {
    totp: 'Enter the code from your authenticator app.',
    email: 'Enter the code we sent to your email.',
    sms: 'Enter the code we sent by SMS.',
}

export default function TwoFactorChallenge({ method, canRememberDevice, rememberDeviceDays, status, endpoints }: Props) {
    const t = useTranslator()

    const { data, setData, post, processing, errors, reset } = useForm({ code: '', remember_device: false })
    const [useRecoveryCode, setUseRecoveryCode] = useState(false)
    const [resending, setResending] = useState(false)

    function submit(event: FormEvent) {
        event.preventDefault()
        post(endpoints.submit, { onFinish: () => reset('code') })
    }

    function resend() {
        router.post(
            endpoints.resend,
            {},
            { preserveScroll: true, onStart: () => setResending(true), onFinish: () => setResending(false) },
        )
    }

    return (
        <AuthLayout
            title={t('Two-factor authentication')}
            description={useRecoveryCode ? t('Enter one of your recovery codes.') : t(descriptions[method])}
            status={status}
        >
            <form className="space-y-4" onSubmit={submit}>
                <Field
                    name="code"
                    label={useRecoveryCode ? t('Recovery code') : t('Verification code')}
                    autoComplete={useRecoveryCode ? 'off' : 'one-time-code'}
                    inputMode={useRecoveryCode ? 'text' : 'numeric'}
                    value={data.code}
                    onChange={(value) => setData('code', value)}
                    error={errors.code}
                    autoFocus
                />

                {canRememberDevice && (
                    <Checkbox name="remember_device" checked={data.remember_device} onChange={(checked) => setData('remember_device', checked)}>
                        {t('Remember this device for :days days', { days: rememberDeviceDays })}
                    </Checkbox>
                )}

                <Button type="submit" full processing={processing}>
                    {t('Continue')}
                </Button>
            </form>

            <div className="flex flex-col items-center gap-2 text-sm">
                <button
                    type="button"
                    className="text-zinc-600 underline underline-offset-4 hover:no-underline dark:text-zinc-400"
                    onClick={() => setUseRecoveryCode(!useRecoveryCode)}
                >
                    {useRecoveryCode ? t('Use a verification code instead') : t('Use a recovery code instead')}
                </button>

                {method !== 'totp' && !useRecoveryCode && (
                    <button
                        type="button"
                        disabled={resending}
                        className="text-zinc-600 underline underline-offset-4 hover:no-underline disabled:opacity-60 dark:text-zinc-400"
                        onClick={resend}
                    >
                        {t('Send the code again')}
                    </button>
                )}
            </div>
        </AuthLayout>
    )
}
