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
        <g class="ks-i-rise">
            <path d="M7 3.5h10v5a5 5 0 0 1-10 0v-5Z" />
            <path d="M7 5H4.5v1.5A3.5 3.5 0 0 0 8 10" />
            <path d="M17 5h2.5v1.5A3.5 3.5 0 0 1 16 10" />
            <line x1="12" y1="13.5" x2="12" y2="17" />
            <path d="M8.5 20.5h7l-1-3.5h-5l-1 3.5Z" />
        </g>
    </svg>
</template>
