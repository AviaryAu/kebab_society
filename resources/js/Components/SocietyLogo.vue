<script setup>
defineProps({
    variant: {
        type: String,
        default: 'horizontal',
        validator: (value) => ['horizontal', 'stacked', 'seal', 'icon'].includes(value),
    },
});
</script>

<template>
    <span
        v-if="variant === 'icon'"
        class="inline-flex h-10 w-10 items-center justify-center border-2 border-ink bg-ink font-display text-xl font-black text-paper"
    >
        KS
    </span>

    <span v-else-if="variant === 'seal'" class="inline-flex flex-col border-2 border-ink bg-paper px-3 py-2">
        <span class="font-display text-2xl font-black leading-none">KSLive</span>
        <span class="label-caps mt-1 text-harbour">Keep Sydney Live</span>
    </span>

    <span v-else-if="variant === 'stacked'" class="inline-flex flex-col leading-none">
        <span class="font-display text-3xl font-black">KSLive</span>
        <span class="label-caps mt-1 text-harbour">Keep Sydney Live</span>
    </span>

    <span v-else class="inline-flex items-baseline gap-2 leading-none">
        <span class="font-display text-3xl font-black">KSLive</span>
        <span class="label-caps text-harbour">Keep Sydney Live</span>
    </span>
</template>
