<script setup lang="ts">
defineProps<{ name: string }>()

const model = defineModel<boolean>({ required: true })
</script>

<template>
    <label :for="name" class="flex items-start gap-2 text-sm text-zinc-700 dark:text-zinc-300">
        <input
            :id="name"
            v-model="model"
            type="checkbox"
            :name="name"
            class="mt-0.5 h-4 w-4 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-900/30 dark:border-zinc-600 dark:bg-zinc-950"
        />
        <span><slot /></span>
    </label>
</template>
