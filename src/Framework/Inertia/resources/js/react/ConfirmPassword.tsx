import { useForm } from '@inertiajs/react'
import type { FormEvent } from 'react'
import AuthLayout from './components/AuthLayout'
import Button from './components/Button'
import Field from './components/Field'
import { useTranslator } from './components/translate'

interface Props {
    status: string | null
    endpoints: { submit: string }
}

export default function ConfirmPassword({ status, endpoints }: Props) {
    const t = useTranslator()

    const { data, setData, post, processing, errors, reset } = useForm({ password: '' })

    function submit(event: FormEvent) {
        event.preventDefault()
        post(endpoints.submit, { onFinish: () => reset('password') })
    }

    return (
        <AuthLayout
            title={t('Confirm your password')}
            description={t('This is a secure area. Please confirm your password before continuing.')}
            status={status}
        >
            <form className="space-y-4" onSubmit={submit}>
                <Field
                    name="password"
                    label={t('Password')}
                    type="password"
                    autoComplete="current-password"
                    value={data.password}
                    onChange={(value) => setData('password', value)}
                    error={errors.password}
                    autoFocus
                />

                <Button type="submit" full processing={processing}>
                    {t('Confirm')}
                </Button>
            </form>
        </AuthLayout>
    )
}
