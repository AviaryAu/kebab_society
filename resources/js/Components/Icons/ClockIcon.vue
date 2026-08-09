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
        <circle cx="12" cy="12" r="9" />
        <!-- Minute hand sweeps around the dial while hovered. -->
        <line class="ks-i-tick" x1="12" y1="12" x2="12" y2="6.5" />
        <line x1="12" y1="12" x2="15.5" y2="14" />
    </svg>
</template>
