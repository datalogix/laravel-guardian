<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3'
import AuthLayout from './components/AuthLayout.vue'
import Button from './components/Button.vue'
import Checkbox from './components/Checkbox.vue'
import Field from './components/Field.vue'
import { identifierField } from './components/identifier'
import OAuthProviders from './components/OAuthProviders.vue'
import type { IdentifierKey, OAuthProvider } from './components/types'
import { useTranslator } from './components/translate'

const t = useTranslator()

const props = defineProps<{
    identifierKey: IdentifierKey
    loginUrl: string | null
    termsUrl: string | null
    oauthProviders: OAuthProvider[]
    status: string | null
    endpoints: { submit: string }
}>()

const identifier = identifierField(props.identifierKey, t)
const needsEmail = props.identifierKey !== 'email'
const form = useForm({ name: '', login: '', email: '', password: '', password_confirmation: '', terms: false })

function submit() {
    form.transform(({ email, ...data }) => (needsEmail ? { ...data, email } : data)).post(props.endpoints.submit, {
        onFinish: () => form.reset('password', 'password_confirmation'),
    })
}
</script>

<template>
    <AuthLayout :title="t('Create an account')" :description="t('Enter your details to get started')" :status="status">
        <form class="space-y-4" @submit.prevent="submit">
            <Field v-model="form.name" name="name" :label="t('Name')" autocomplete="name" :error="form.errors.name" autofocus />

            <Field
                v-model="form.login"
                name="login"
                :label="identifier.label"
                :type="identifier.type"
                :autocomplete="identifier.autocomplete"
                :inputmode="identifier.inputmode"
                :placeholder="identifier.placeholder"
                :error="form.errors.login"
            />

            <Field
                v-if="needsEmail"
                v-model="form.email"
                name="email"
                :label="t('Email')"
                type="email"
                autocomplete="email"
                inputmode="email"
                placeholder="email@example.com"
                :error="form.errors.email"
            />

            <Field
                v-model="form.password"
                name="password"
                :label="t('Password')"
                type="password"
                autocomplete="new-password"
                :error="form.errors.password"
            />

            <Field
                v-model="form.password_confirmation"
                name="password_confirmation"
                :label="t('Confirm password')"
                type="password"
                autocomplete="new-password"
                :error="form.errors.password_confirmation"
            />

            <div v-if="termsUrl" class="space-y-1.5">
                <Checkbox v-model="form.terms" name="terms">{{ t('I accept the terms and conditions') }}</Checkbox>
                <a :href="termsUrl" target="_blank" rel="noopener" class="text-sm font-medium text-zinc-900 underline underline-offset-4 hover:no-underline dark:text-zinc-100">
                    {{ t('Read the terms and conditions') }}
                </a>
                <p v-if="form.errors.terms" role="alert" class="text-sm text-red-600 dark:text-red-400">{{ form.errors.terms }}</p>
            </div>

            <Button type="submit" full :processing="form.processing">{{ t('Create account') }}</Button>
        </form>

        <OAuthProviders :providers="oauthProviders" :label="t('Or sign up with')" />

        <template #footer>
            <template v-if="loginUrl">
                {{ t('Already have an account?') }}
                <Link :href="loginUrl" class="font-medium text-zinc-900 underline underline-offset-4 hover:no-underline dark:text-zinc-100">
                    {{ t('Sign in') }}
                </Link>
            </template>
        </template>
    </AuthLayout>
</template>
