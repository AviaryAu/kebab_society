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
        <path class="ks-i-draw" style="--ks-dash: 40" stroke-dasharray="40" d="M6 19v-5a4 4 0 0 1 4-4h4a4 4 0 0 0 4-4V5" />
        <circle cx="6" cy="20" r="2.2" />
        <circle cx="18" cy="4" r="2.2" />
    </svg>
</template>
