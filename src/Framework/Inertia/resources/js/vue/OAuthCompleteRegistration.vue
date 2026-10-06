<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { computed } from 'vue'
import AuthLayout from './components/AuthLayout.vue'
import Button from './components/Button.vue'
import Field from './components/Field.vue'
import { identifierField } from './components/identifier'
import type { IdentifierKey } from './components/types'
import { useTranslator } from './components/translate'

const t = useTranslator()

const props = defineProps<{
    identifierKey: IdentifierKey
    provider: string | null
    email: string | null
    status: string | null
    endpoints: { submit: string }
}>()

const identifier = identifierField(props.identifierKey, t)
const form = useForm({ login: '' })

const providerName = computed(() => (props.provider ? props.provider.charAt(0).toUpperCase() + props.provider.slice(1) : 'your provider'))

function submit() {
    form.post(props.endpoints.submit)
}
</script>

<template>
    <AuthLayout
        :title="t('Complete your registration')"
        :description="`You signed in with ${providerName}${email ? ` as ${email}` : ''}. One more detail and your account is ready.`"
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

            <Button type="submit" full :processing="form.processing">{{ t('Complete registration') }}</Button>
        </form>
    </AuthLayout>
</template>
