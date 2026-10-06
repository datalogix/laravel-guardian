<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import Alert from './Alert.vue'

withDefaults(
    defineProps<{
        title: string
        description?: string
        status?: string | null
        wide?: boolean
    }>(),
    { wide: false },
)
</script>

<template>
    <Head :title="title" />

    <div class="flex min-h-screen flex-col items-center justify-center bg-zinc-50 px-4 py-10 dark:bg-zinc-950">
        <div class="w-full" :class="wide ? 'max-w-2xl' : 'max-w-sm'">
            <div class="mb-6 text-center">
                <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-50">{{ title }}</h1>
                <p v-if="description" class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{{ description }}</p>
            </div>

            <Alert v-if="status" class="mb-4">{{ status }}</Alert>

            <div class="space-y-6 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <slot />
            </div>

            <div v-if="$slots.footer" class="mt-4 text-center text-sm text-zinc-600 dark:text-zinc-400">
                <slot name="footer" />
            </div>
        </div>
    </div>
</template>
