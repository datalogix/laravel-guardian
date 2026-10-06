<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import AuthLayout from './components/AuthLayout.vue'
import Button from './components/Button.vue'
import Checkbox from './components/Checkbox.vue'
import Field from './components/Field.vue'
import type { TwoFactorMethod } from './components/types'
import { useTranslator } from './components/translate'

const t = useTranslator()

const props = defineProps<{
    method: TwoFactorMethod
    canRememberDevice: boolean
    rememberDeviceDays: number
    status: string | null
    endpoints: { submit: string; resend: string }
}>()

const form = useForm({ code: '', remember_device: false })
const useRecoveryCode = ref(false)
const resending = ref(false)

const description = computed(() => {
    if (useRecoveryCode.value) {
        return t('Enter one of your recovery codes.')
    }

    return {
        totp: t('Enter the code from your authenticator app.'),
        email: t('Enter the code we sent to your email.'),
        sms: t('Enter the code we sent by SMS.'),
    }[props.method]
})

function submit() {
    form.post(props.endpoints.submit, { onFinish: () => form.reset('code') })
}

function resend() {
    router.post(props.endpoints.resend, {}, {
        preserveScroll: true,
        onStart: () => (resending.value = true),
        onFinish: () => (resending.value = false),
    })
}
</script>

<template>
    <AuthLayout :title="t('Two-factor authentication')" :description="description" :status="status">
        <form class="space-y-4" @submit.prevent="submit">
            <Field
                v-model="form.code"
                name="code"
                :label="useRecoveryCode ? t('Recovery code') : t('Verification code')"
                :autocomplete="useRecoveryCode ? 'off' : 'one-time-code'"
                :inputmode="useRecoveryCode ? 'text' : 'numeric'"
                :error="form.errors.code"
                autofocus
            />

            <Checkbox v-if="canRememberDevice" v-model="form.remember_device" name="remember_device">
                {{ t('Remember this device for :days days', { days: rememberDeviceDays }) }}
            </Checkbox>

            <Button type="submit" full :processing="form.processing">{{ t('Continue') }}</Button>
        </form>

        <div class="flex flex-col items-center gap-2 text-sm">
            <button
                type="button"
                class="text-zinc-600 underline underline-offset-4 hover:no-underline dark:text-zinc-400"
                @click="useRecoveryCode = !useRecoveryCode"
            >
                {{ useRecoveryCode ? t('Use a verification code instead') : t('Use a recovery code instead') }}
            </button>

            <button
                v-if="method !== 'totp' && !useRecoveryCode"
                type="button"
                :disabled="resending"
                class="text-zinc-600 underline underline-offset-4 hover:no-underline disabled:opacity-60 dark:text-zinc-400"
                @click="resend"
            >
                {{ t('Send the code again') }}
            </button>
        </div>
    </AuthLayout>
</template>
