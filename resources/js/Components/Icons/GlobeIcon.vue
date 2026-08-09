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
        <g class="ks-i-sweep">
            <ellipse cx="12" cy="12" rx="4" ry="9" />
        </g>
        <line x1="3.2" y1="9.5" x2="20.8" y2="9.5" />
        <line x1="3.2" y1="14.5" x2="20.8" y2="14.5" />
    </svg>
</template>
