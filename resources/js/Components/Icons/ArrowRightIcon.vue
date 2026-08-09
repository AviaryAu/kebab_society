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
        <g class="ks-i-nudge">
            <line x1="4" y1="12" x2="19" y2="12" />
            <polyline points="13,6 19,12 13,18" />
        </g>
    </svg>
</template>
