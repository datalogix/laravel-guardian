<script setup lang="ts">
withDefaults(defineProps<{ variant?: 'info' | 'error' }>(), { variant: 'info' })
</script>

<template>
    <div
        :role="variant === 'error' ? 'alert' : 'status'"
        class="rounded-md border px-3 py-2 text-sm"
        :class="
            variant === 'error'
                ? 'border-red-200 bg-red-50 text-red-700 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300'
                : 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-300'
        "
    >
        <slot />
    </div>
</template>
