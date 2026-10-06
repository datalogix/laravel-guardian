<script setup lang="ts">
withDefaults(
    defineProps<{
        label: string
        name: string
        type?: string
        error?: string
        autocomplete?: string
        inputmode?: 'email' | 'numeric' | 'text'
        placeholder?: string
        hint?: string
        required?: boolean
        autofocus?: boolean
        readonly?: boolean
    }>(),
    { type: 'text', required: true, autofocus: false, readonly: false },
)

const model = defineModel<string>({ required: true })
</script>

<template>
    <div class="space-y-1.5">
        <label :for="name" class="block text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ label }}</label>

        <input
            :id="name"
            v-model="model"
            :name="name"
            :type="type"
            :autocomplete="autocomplete"
            :inputmode="inputmode"
            :placeholder="placeholder"
            :required="required"
            :autofocus="autofocus"
            :readonly="readonly"
            :aria-invalid="error ? 'true' : undefined"
            :aria-describedby="error ? `${name}-error` : hint ? `${name}-hint` : undefined"
            class="block w-full rounded-md border bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm placeholder:text-zinc-400 focus:outline-none focus:ring-2 read-only:bg-zinc-100 dark:bg-zinc-950 dark:text-zinc-100 dark:read-only:bg-zinc-900"
            :class="
                error
                    ? 'border-red-500 focus:ring-red-500/30'
                    : 'border-zinc-300 focus:ring-zinc-900/20 dark:border-zinc-700 dark:focus:ring-white/20'
            "
        />

        <p v-if="hint && !error" :id="`${name}-hint`" class="text-xs text-zinc-500 dark:text-zinc-400">{{ hint }}</p>
        <p v-if="error" :id="`${name}-error`" role="alert" class="text-sm text-red-600 dark:text-red-400">{{ error }}</p>
    </div>
</template>
