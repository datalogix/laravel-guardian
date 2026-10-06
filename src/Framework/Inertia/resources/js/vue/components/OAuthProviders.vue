<script setup lang="ts">
import type { OAuthProvider } from './types'
import { useTranslator } from './translate'

const t = useTranslator()

defineProps<{ providers: OAuthProvider[]; label?: string }>()

const title = (name: string) => name.charAt(0).toUpperCase() + name.slice(1)
</script>

<template>
    <div v-if="providers.length > 0">
        <div class="relative">
            <div class="absolute inset-0 flex items-center" aria-hidden="true">
                <div class="w-full border-t border-zinc-200 dark:border-zinc-700" />
            </div>
            <div class="relative flex justify-center text-xs uppercase">
                <span class="bg-white px-2 text-zinc-500 dark:bg-zinc-900 dark:text-zinc-400">{{ label ?? t('Or continue with') }}</span>
            </div>
        </div>

        <!-- Plain links, not Inertia visits: the browser has to follow the redirect to the provider. -->
        <div class="mt-4 grid gap-2">
            <a
                v-for="provider in providers"
                :key="provider.name"
                :href="provider.url"
                class="inline-flex items-center justify-center rounded-md border border-zinc-300 bg-white px-4 py-2 text-sm font-medium text-zinc-900 hover:bg-zinc-50 focus:outline-none focus:ring-2 focus:ring-zinc-900/20 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:hover:bg-zinc-800"
            >
                {{ title(provider.name) }}
            </a>
        </div>
    </div>
</template>
