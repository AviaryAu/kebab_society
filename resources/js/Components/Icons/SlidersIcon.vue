<script setup>
defineProps({
    size: { type: [Number, String], default: 20 },
    strokeWidth: { type: [Number, String], default: 2 },
});
</script>

<template>
    <svg
        class="ks-icon"
        :width="size"
        :height="size"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        :stroke-width="strokeWidth"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
    >
        <line x1="3" y1="7" x2="21" y2="7" />
        <line x1="3" y1="12" x2="21" y2="12" />
        <line x1="3" y1="17" x2="21" y2="17" />
        <circle class="ks-i-sweep" cx="9" cy="7" r="2.4" fill="currentColor" />
        <circle class="ks-i-sweep" cx="15" cy="12" r="2.4" fill="currentColor" />
        <circle class="ks-i-sweep" cx="8" cy="17" r="2.4" fill="currentColor" />
    </svg>
</template>
