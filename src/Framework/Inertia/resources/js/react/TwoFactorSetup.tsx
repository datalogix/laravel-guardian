import { router, useForm } from '@inertiajs/react'
import { useState } from 'react'
import type { FormEvent } from 'react'
import Alert from './components/Alert'
import AuthLayout from './components/AuthLayout'
import Button from './components/Button'
import Field from './components/Field'
import type { TrustedDevice, TwoFactorMethod } from './components/types'
import { useTranslator } from './components/translate'

interface Props {
    enabled: boolean
    secretUnreadable: boolean
    canDisable: boolean
    method: TwoFactorMethod
    secret: string | null
    uri: string | null
    qrSvg: string | null
    canManageRecoveryCodes: boolean
    recoveryCodesCount: number
    recoveryCodes: string[]
    trustedDevices: TrustedDevice[]
    awaitingContinueAfterSetup: boolean
    status: string | null
    endpoints: {
        prepare: string
        enable: string
        continue: string
        disable: string
        'recovery-codes': string
        'trusted-devices.destroy-all': string
    }
}

const instructions: Record<TwoFactorMethod, string> = {
    totp: 'Scan the QR code with your authenticator app, or enter the secret by hand, then type the code it shows.',
    email: 'We sent a 6-digit verification code to your email. Enter it below to enable two-factor authentication.',
    sms: 'We sent a 6-digit verification code by SMS. Enter it below to enable two-factor authentication.',
}

export default function TwoFactorSetup({
    enabled,
    secretUnreadable,
    canDisable,
    method,
    secret,
    uri,
    qrSvg,
    canManageRecoveryCodes,
    recoveryCodesCount,
    recoveryCodes,
    trustedDevices,
    awaitingContinueAfterSetup,
    status,
    endpoints,
}: Props) {
    const t = useTranslator()

    const enableForm = useForm({ code: '' })
    const [busy, setBusy] = useState(false)
    const [copied, setCopied] = useState(false)

    const options = {
        preserveScroll: true,
        onStart: () => setBusy(true),
        onFinish: () => setBusy(false),
    }

    const post = (url: string) => router.post(url, {}, options)

    const destroy = (url: string, question: string) => {
        if (window.confirm(question)) {
            router.delete(url, options)
        }
    }

    function enable(event: FormEvent) {
        event.preventDefault()
        enableForm.post(endpoints.enable, { preserveScroll: true, onFinish: () => enableForm.reset('code') })
    }

    async function copyRecoveryCodes() {
        await navigator.clipboard.writeText(recoveryCodes.join('\n'))

        setCopied(true)
        setTimeout(() => setCopied(false), 2000)
    }

    return (
        <AuthLayout
            title={t('Two-factor authentication')}
            description={enabled ? t('Two-factor authentication is enabled for your account.') : t('Two-factor authentication is disabled.')}
            status={status}
            wide
        >
            {secretUnreadable && (
                <Alert variant="error">
                    {t('We could not verify your two-factor secret. Please disable and set up two-factor authentication again.')}
                </Alert>
            )}

            {enabled ? (
                <>
                    {recoveryCodes.length > 0 ? (
                        <section className="space-y-3">
                            <h2 className="text-sm font-medium text-zinc-900 dark:text-zinc-100">{t('Recovery codes')}</h2>
                            <p className="text-sm text-zinc-600 dark:text-zinc-400">
                                {t('Store them safely: each one can be used once if you lose access to your authenticator.')}
                            </p>
                            <ul className="grid gap-2 rounded-md bg-zinc-100 p-3 font-mono text-sm text-zinc-900 sm:grid-cols-2 dark:bg-zinc-800 dark:text-zinc-100">
                                {recoveryCodes.map((code) => (
                                    <li key={code}>{code}</li>
                                ))}
                            </ul>
                            <Button variant="secondary" onClick={copyRecoveryCodes}>
                                {copied ? t('Copied!') : t('Copy codes')}
                            </Button>
                        </section>
                    ) : (
                        canManageRecoveryCodes && (
                            <p className="text-sm text-zinc-600 dark:text-zinc-400">
                                {t('Recovery codes are configured for your account (:count available).', { count: recoveryCodesCount })}
                            </p>
                        )
                    )}

                    <div className="flex flex-wrap gap-3">
                        {awaitingContinueAfterSetup ? (
                            <Button processing={busy} onClick={() => post(endpoints.continue)}>
                                {t('Continue')}
                            </Button>
                        ) : (
                            <>
                                {(recoveryCodes.length > 0 || canManageRecoveryCodes) && (
                                    <Button variant="secondary" processing={busy} onClick={() => post(endpoints['recovery-codes'])}>
                                        {t('Regenerate recovery codes')}
                                    </Button>
                                )}

                                {canDisable && (
                                    <Button
                                        variant="danger"
                                        processing={busy}
                                        onClick={() => destroy(endpoints.disable, t('Disable two-factor authentication?'))}
                                    >
                                        {t('Disable two-factor')}
                                    </Button>
                                )}
                            </>
                        )}
                    </div>

                    {trustedDevices.length > 0 && (
                        <section className="space-y-3">
                            <h2 className="text-sm font-medium text-zinc-900 dark:text-zinc-100">{t('Trusted devices')}</h2>

                            <div className="overflow-x-auto rounded-md border border-zinc-200 dark:border-zinc-700">
                                <table className="min-w-full divide-y divide-zinc-200 text-left text-sm dark:divide-zinc-700">
                                    <thead className="bg-zinc-50 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                                        <tr>
                                            <th className="px-3 py-2 font-medium">{t('Device name')}</th>
                                            <th className="px-3 py-2 font-medium">{t('IP address')}</th>
                                            <th className="px-3 py-2 font-medium">{t('Last used')}</th>
                                            <th className="px-3 py-2" />
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-zinc-200 text-zinc-900 dark:divide-zinc-700 dark:text-zinc-100">
                                        {trustedDevices.map((device) => (
                                            <tr key={device.id}>
                                                <td className="px-3 py-2">{device.name ?? t('Trusted device')}</td>
                                                <td className="px-3 py-2">{device.ip_address ?? t('Unknown IP')}</td>
                                                <td className="px-3 py-2">{device.last_used_at ?? t('Never used')}</td>
                                                <td className="px-3 py-2 text-right">
                                                    <button
                                                        type="button"
                                                        disabled={busy}
                                                        className="text-red-600 underline underline-offset-4 hover:no-underline disabled:opacity-60 dark:text-red-400"
                                                        onClick={() => destroy(device.revokeUrl, t('Revoke this device?'))}
                                                    >
                                                        {t('Revoke')}
                                                    </button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            <Button
                                variant="secondary"
                                processing={busy}
                                onClick={() => destroy(endpoints['trusted-devices.destroy-all'], t('Revoke all trusted devices?'))}
                            >
                                {t('Revoke all trusted devices')}
                            </Button>
                        </section>
                    )}
                </>
            ) : !secret ? (
                <Button processing={busy} onClick={() => post(endpoints.prepare)}>
                    {t('Generate setup secret')}
                </Button>
            ) : (
                <>
                    <p className="text-sm text-zinc-600 dark:text-zinc-400">{t(instructions[method])}</p>

                    {method === 'totp' && (
                        <div className="grid items-start gap-6 md:grid-cols-2">
                            {qrSvg && (
                                <div
                                    className="mx-auto max-w-[14rem] rounded-md bg-white p-2"
                                    role="img"
                                    aria-label={t('QR code for authenticator app setup')}
                                    // Generated by the server from the secret, so it is safe to inject.
                                    // eslint-disable-next-line @eslint-react/dom-no-dangerously-set-innerhtml
                                    dangerouslySetInnerHTML={{ __html: qrSvg }}
                                />
                            )}

                            <div className="space-y-3">
                                <div>
                                    <p className="text-xs font-medium text-zinc-500 dark:text-zinc-400">{t('Secret')}</p>
                                    <pre className="whitespace-pre-wrap break-all rounded-md bg-zinc-100 p-3 text-sm text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100">
                                        {secret}
                                    </pre>
                                </div>
                                {uri && (
                                    <div>
                                        <p className="text-xs font-medium text-zinc-500 dark:text-zinc-400">{t('OTPAuth URI')}</p>
                                        <pre className="whitespace-pre-wrap break-all rounded-md bg-zinc-100 p-3 text-sm text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100">
                                            {uri}
                                        </pre>
                                    </div>
                                )}
                            </div>
                        </div>
                    )}

                    <form className="space-y-4" onSubmit={enable}>
                        <Field
                            name="code"
                            label={t('Verification code')}
                            autoComplete="one-time-code"
                            inputMode="numeric"
                            value={enableForm.data.code}
                            onChange={(value) => enableForm.setData('code', value)}
                            error={enableForm.errors.code}
                            autoFocus
                        />

                        <Button type="submit" processing={enableForm.processing}>
                            {t('Enable two-factor')}
                        </Button>
                    </form>
                </>
            )}
        </AuthLayout>
    )
}
