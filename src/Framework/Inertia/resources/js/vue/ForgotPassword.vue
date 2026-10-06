<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3'
import AuthLayout from './components/AuthLayout.vue'
import Button from './components/Button.vue'
import Field from './components/Field.vue'
import { identifierField } from './components/identifier'
import type { IdentifierKey } from './components/types'
import { useTranslator } from './components/translate'

const t = useTranslator()

const props = defineProps<{
    identifierKey: IdentifierKey
    loginUrl: string | null
    status: string | null
    endpoints: { submit: string }
}>()

const identifier = identifierField(props.identifierKey, t)
const form = useForm({ login: '' })

function submit() {
    form.post(props.endpoints.submit)
}
</script>

<template>
    <AuthLayout
        :title="t('Forgot your password?')"
        :description="`Enter your ${identifier.label} and we'll send you a password reset link.`"
        :status="status"
    >
        <form class="space-y-4" @submit.prevent="submit">
            <Field
                v-model="form.login"
                name="login"
                :label="identifier.label"
                :type="identifier.type"
                :autocomplete="identifier.autocomplete"
                :inputmode="identifier.inputmode"
                :placeholder="identifier.placeholder"
                :error="form.errors.login"
                autofocus
            />

            <Button type="submit" full :processing="form.processing">{{ t('Send reset link') }}</Button>
        </form>

        <template #footer>
            <template v-if="loginUrl">
                {{ t('Or, return to') }}
                <Link :href="loginUrl" class="font-medium text-zinc-900 underline underline-offset-4 hover:no-underline dark:text-zinc-100">
                    {{ t('sign in') }}
                </Link>
            </template>
        </template>
    </AuthLayout>
</template>
