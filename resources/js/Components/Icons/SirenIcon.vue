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
        <path d="M5 19.5h14a1 1 0 0 0 1-1v-.5a8 8 0 0 0-16 0v.5a1 1 0 0 0 1 1Z" />
        <g class="ks-i-flicker">
            <line x1="12" y1="2" x2="12" y2="4.5" />
            <line x1="4.2" y1="5.2" x2="5.9" y2="6.9" />
            <line x1="19.8" y1="5.2" x2="18.1" y2="6.9" />
        </g>
    </svg>
</template>
