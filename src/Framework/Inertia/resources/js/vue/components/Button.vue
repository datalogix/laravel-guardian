<script setup lang="ts">
withDefaults(
    defineProps<{
        type?: 'submit' | 'button'
        variant?: 'primary' | 'secondary' | 'danger'
        processing?: boolean
        full?: boolean
    }>(),
    { type: 'button', variant: 'primary', processing: false, full: false },
)
</script>

<template>
    <button
        :type="type"
        :disabled="processing"
        class="inline-flex items-center justify-center rounded-md px-4 py-2 text-sm font-medium focus:outline-none focus:ring-2 disabled:cursor-not-allowed disabled:opacity-60"
        :class="[
            full ? 'w-full' : '',
            variant === 'primary' &&
                'bg-zinc-900 text-white hover:bg-zinc-800 focus:ring-zinc-900/40 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200 dark:focus:ring-white/40',
            variant === 'secondary' &&
                'border border-zinc-300 bg-white text-zinc-900 hover:bg-zinc-50 focus:ring-zinc-900/20 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:hover:bg-zinc-800 dark:focus:ring-white/20',
            variant === 'danger' && 'bg-red-600 text-white hover:bg-red-500 focus:ring-red-600/40',
        ]"
    >
        <slot />
    </button>
</template>
