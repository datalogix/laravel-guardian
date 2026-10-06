<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import Alert from './components/Alert.vue'
import AuthLayout from './components/AuthLayout.vue'
import Button from './components/Button.vue'
import Field from './components/Field.vue'
import type { TrustedDevice, TwoFactorMethod } from './components/types'
import { useTranslator } from './components/translate'

const t = useTranslator()

const props = defineProps<{
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
}>()

const enableForm = useForm({ code: '' })
const busy = ref(false)
const copied = ref(false)

const instructions = computed(
    () =>
        ({
            totp: t('Scan the QR code with your authenticator app, or enter the secret by hand, then type the code it shows.'),
            email: t('We sent a 6-digit verification code to your email. Enter it below to enable two-factor authentication.'),
            sms: t('We sent a 6-digit verification code by SMS. Enter it below to enable two-factor authentication.'),
        })[props.method],
)

const options = {
    preserveScroll: true,
    onStart: () => (busy.value = true),
    onFinish: () => (busy.value = false),
}

function post(url: string) {
    router.post(url, {}, options)
}

function destroy(url: string, question: string) {
    if (window.confirm(question)) {
        router.delete(url, options)
    }
}

function enable() {
    enableForm.post(props.endpoints.enable, {
        preserveScroll: true,
        onFinish: () => enableForm.reset('code'),
    })
}

async function copyRecoveryCodes() {
    await navigator.clipboard.writeText(props.recoveryCodes.join('\n'))

    copied.value = true
    setTimeout(() => (copied.value = false), 2000)
}
</script>

<template>
    <AuthLayout
        :title="t('Two-factor authentication')"
        :description="enabled ? t('Two-factor authentication is enabled for your account.') : t('Two-factor authentication is disabled.')"
        :status="status"
        wide
    >
        <Alert v-if="secretUnreadable" variant="error">
            {{ t('We could not verify your two-factor secret. Please disable and set up two-factor authentication again.') }}
        </Alert>

        <template v-if="enabled">
            <section v-if="recoveryCodes.length > 0" class="space-y-3">
                <h2 class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ t('Recovery codes') }}</h2>
                <p class="text-sm text-zinc-600 dark:text-zinc-400">
                    {{ t('Store them safely: each one can be used once if you lose access to your authenticator.') }}
                </p>
                <ul class="grid gap-2 rounded-md bg-zinc-100 p-3 font-mono text-sm text-zinc-900 sm:grid-cols-2 dark:bg-zinc-800 dark:text-zinc-100">
                    <li v-for="code in recoveryCodes" :key="code">{{ code }}</li>
                </ul>
                <Button variant="secondary" @click="copyRecoveryCodes">{{ copied ? t('Copied!') : t('Copy codes') }}</Button>
            </section>

            <p v-else-if="canManageRecoveryCodes" class="text-sm text-zinc-600 dark:text-zinc-400">
                {{ t('Recovery codes are configured for your account (:count available).', { count: recoveryCodesCount }) }}
            </p>

            <div class="flex flex-wrap gap-3">
                <Button v-if="awaitingContinueAfterSetup" :processing="busy" @click="post(endpoints.continue)">{{ t('Continue') }}</Button>

                <template v-else>
                    <Button
                        v-if="recoveryCodes.length > 0 || canManageRecoveryCodes"
                        variant="secondary"
                        :processing="busy"
                        @click="post(endpoints['recovery-codes'])"
                    >
                        {{ t('Regenerate recovery codes') }}
                    </Button>

                    <Button
                        v-if="canDisable"
                        variant="danger"
                        :processing="busy"
                        @click="destroy(endpoints.disable, t('Disable two-factor authentication?'))"
                    >
                        {{ t('Disable two-factor') }}
                    </Button>
                </template>
            </div>

            <section v-if="trustedDevices.length > 0" class="space-y-3">
                <h2 class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ t('Trusted devices') }}</h2>

                <div class="overflow-x-auto rounded-md border border-zinc-200 dark:border-zinc-700">
                    <table class="min-w-full divide-y divide-zinc-200 text-left text-sm dark:divide-zinc-700">
                        <thead class="bg-zinc-50 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                            <tr>
                                <th class="px-3 py-2 font-medium">{{ t('Device name') }}</th>
                                <th class="px-3 py-2 font-medium">{{ t('IP address') }}</th>
                                <th class="px-3 py-2 font-medium">{{ t('Last used') }}</th>
                                <th class="px-3 py-2" />
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 text-zinc-900 dark:divide-zinc-700 dark:text-zinc-100">
                            <tr v-for="device in trustedDevices" :key="device.id">
                                <td class="px-3 py-2">{{ device.name ?? t('Trusted device') }}</td>
                                <td class="px-3 py-2">{{ device.ip_address ?? t('Unknown IP') }}</td>
                                <td class="px-3 py-2">{{ device.last_used_at ?? t('Never used') }}</td>
                                <td class="px-3 py-2 text-right">
                                    <button
                                        type="button"
                                        :disabled="busy"
                                        class="text-red-600 underline underline-offset-4 hover:no-underline disabled:opacity-60 dark:text-red-400"
                                        @click="destroy(device.revokeUrl, t('Revoke this device?'))"
                                    >
                                        {{ t('Revoke') }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Button
                    variant="secondary"
                    :processing="busy"
                    @click="destroy(endpoints['trusted-devices.destroy-all'], t('Revoke all trusted devices?'))"
                >
                    {{ t('Revoke all trusted devices') }}
                </Button>
            </section>
        </template>

        <template v-else>
            <Button v-if="!secret" :processing="busy" @click="post(endpoints.prepare)">{{ t('Generate setup secret') }}</Button>

            <template v-else>
                <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ instructions }}</p>

                <div v-if="method === 'totp'" class="grid items-start gap-6 md:grid-cols-2">
                    <!-- Generated by the server from the secret, so it is safe to inject. -->
                    <!-- eslint-disable vue/no-v-html -->
                    <div
                        v-if="qrSvg"
                        class="mx-auto max-w-[14rem] rounded-md bg-white p-2"
                        role="img"
                        :aria-label="t('QR code for authenticator app setup')"
                        v-html="qrSvg"
                    />
                    <!-- eslint-enable vue/no-v-html -->

                    <div class="space-y-3">
                        <div>
                            <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ t('Secret') }}</p>
                            <pre class="whitespace-pre-wrap break-all rounded-md bg-zinc-100 p-3 text-sm text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100">{{ secret }}</pre>
                        </div>
                        <div v-if="uri">
                            <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ t('OTPAuth URI') }}</p>
                            <pre class="whitespace-pre-wrap break-all rounded-md bg-zinc-100 p-3 text-sm text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100">{{ uri }}</pre>
                        </div>
                    </div>
                </div>

                <form class="space-y-4" @submit.prevent="enable">
                    <Field
                        v-model="enableForm.code"
                        name="code"
                        :label="t('Verification code')"
                        autocomplete="one-time-code"
                        inputmode="numeric"
                        :error="enableForm.errors.code"
                        autofocus
                    />

                    <Button type="submit" :processing="enableForm.processing">{{ t('Enable two-factor') }}</Button>
                </form>
            </template>
        </template>
    </AuthLayout>
</template>
