<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import AuthLayout from './components/AuthLayout.vue'
import Button from './components/Button.vue'
import Field from './components/Field.vue'
import { useTranslator } from './components/translate'

const t = useTranslator()

const props = defineProps<{
    status: string | null
    endpoints: { submit: string }
}>()

const form = useForm({ password: '' })

function submit() {
    form.post(props.endpoints.submit, { onFinish: () => form.reset('password') })
}
</script>

<template>
    <AuthLayout
        :title="t('Confirm your password')"
        :description="t('This is a secure area. Please confirm your password before continuing.')"
        :status="status"
    >
        <form class="space-y-4" @submit.prevent="submit">
            <Field
                v-model="form.password"
                name="password"
                :label="t('Password')"
                type="password"
                autocomplete="current-password"
                :error="form.errors.password"
                autofocus
            />

            <Button type="submit" full :processing="form.processing">{{ t('Confirm') }}</Button>
        </form>
    </AuthLayout>
</template>
