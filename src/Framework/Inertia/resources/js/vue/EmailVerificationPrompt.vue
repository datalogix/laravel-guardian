<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3'
import AuthLayout from './components/AuthLayout.vue'
import Button from './components/Button.vue'
import { useTranslator } from './components/translate'

const t = useTranslator()

const props = defineProps<{
    logoutUrl: string | null
    status: string | null
    endpoints: { submit: string }
}>()

const form = useForm({})

function resend() {
    form.post(props.endpoints.submit)
}
</script>

<template>
    <AuthLayout
        :title="t('Verify your email')"
        :description="t('Click the link we emailed you to verify your address. Didn\'t get it? We can send you another.')"
        :status="status"
    >
        <form class="space-y-4" @submit.prevent="resend">
            <Button type="submit" full :processing="form.processing">{{ t('Resend verification email') }}</Button>
        </form>

        <template #footer>
            <Link
                v-if="logoutUrl"
                :href="logoutUrl"
                method="post"
                as="button"
                type="button"
                class="font-medium text-zinc-900 underline underline-offset-4 hover:no-underline dark:text-zinc-100"
            >
                {{ t('Log out') }}
            </Link>
        </template>
    </AuthLayout>
</template>
