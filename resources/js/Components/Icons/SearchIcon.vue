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
        <circle class="ks-i-pop" cx="11" cy="11" r="6.5" />
        <line class="ks-i-nudge" x1="16.2" y1="16.2" x2="21" y2="21" />
    </svg>
</template>
