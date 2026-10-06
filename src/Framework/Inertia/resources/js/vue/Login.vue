<script setup lang="ts">
import { Link, useForm, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import Alert from './components/Alert.vue'
import AuthLayout from './components/AuthLayout.vue'
import Button from './components/Button.vue'
import Checkbox from './components/Checkbox.vue'
import Field from './components/Field.vue'
import { identifierField } from './components/identifier'
import OAuthProviders from './components/OAuthProviders.vue'
import type { IdentifierKey, OAuthProvider, PageErrors } from './components/types'
import { useTranslator } from './components/translate'

const t = useTranslator()

const props = defineProps<{
    identifierKey: IdentifierKey
    forgotPasswordUrl: string | null
    signUpUrl: string | null
    oauthProviders: OAuthProvider[]
    status: string | null
    endpoints: { submit: string }
}>()

const identifier = identifierField(props.identifierKey, t)
const form = useForm({ login: '', password: '', remember: false })

const page = usePage<{ errors: PageErrors }>()
const providerError = computed(() => page.props.errors?.oauth)

function submit() {
    form.post(props.endpoints.submit, { onFinish: () => form.reset('password') })
}
</script>

<template>
    <AuthLayout :title="t('Sign in')" :description="t('Enter your details to access your account')" :status="status">
        <Alert v-if="providerError" variant="error">{{ providerError }}</Alert>

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

            <div class="space-y-1.5">
                <Field
                    v-model="form.password"
                    name="password"
                    :label="t('Password')"
                    type="password"
                    autocomplete="current-password"
                    :error="form.errors.password"
                />
                <div v-if="forgotPasswordUrl" class="text-right text-sm">
                    <Link :href="forgotPasswordUrl" class="text-zinc-600 underline underline-offset-4 hover:no-underline dark:text-zinc-400">
                        {{ t('Forgot your password?') }}
                    </Link>
                </div>
            </div>

            <Checkbox v-model="form.remember" name="remember">{{ t('Remember me') }}</Checkbox>

            <Button type="submit" full :processing="form.processing">{{ t('Sign in') }}</Button>
        </form>

        <OAuthProviders :providers="oauthProviders" />

        <template #footer>
            <template v-if="signUpUrl">
                {{ t("Don't have an account?") }}
                <Link :href="signUpUrl" class="font-medium text-zinc-900 underline underline-offset-4 hover:no-underline dark:text-zinc-100">
                    {{ t('Sign up') }}
                </Link>
            </template>
        </template>
    </AuthLayout>
</template>
