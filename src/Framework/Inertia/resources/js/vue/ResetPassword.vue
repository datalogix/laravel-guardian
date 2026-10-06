<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import Alert from './components/Alert.vue'
import AuthLayout from './components/AuthLayout.vue'
import Button from './components/Button.vue'
import Field from './components/Field.vue'
import { identifierField } from './components/identifier'
import type { IdentifierKey } from './components/types'
import { useTranslator } from './components/translate'

const t = useTranslator()

const props = defineProps<{
    identifierKey: IdentifierKey
    token: string
    login: string
    status: string | null
    endpoints: { submit: string }
}>()

const identifier = identifierField(props.identifierKey, t)
const form = useForm({ token: props.token, login: props.login, password: '', password_confirmation: '' })

function submit() {
    form.post(props.endpoints.submit, { onFinish: () => form.reset('password', 'password_confirmation') })
}
</script>

<template>
    <AuthLayout :title="t('Reset your password')" :description="t('Choose a new password for your account')" :status="status">
        <form class="space-y-4" @submit.prevent="submit">
            <Alert v-if="form.errors.token" variant="error">{{ form.errors.token }}</Alert>

            <Field
                v-model="form.login"
                name="login"
                :label="identifier.label"
                :type="identifier.type"
                :autocomplete="identifier.autocomplete"
                :inputmode="identifier.inputmode"
                :error="form.errors.login"
            />

            <Field
                v-model="form.password"
                name="password"
                :label="t('New password')"
                type="password"
                autocomplete="new-password"
                :error="form.errors.password"
                autofocus
            />

            <Field
                v-model="form.password_confirmation"
                name="password_confirmation"
                :label="t('Confirm new password')"
                type="password"
                autocomplete="new-password"
                :error="form.errors.password_confirmation"
            />

            <Button type="submit" full :processing="form.processing">{{ t('Reset password') }}</Button>
        </form>
    </AuthLayout>
</template>
