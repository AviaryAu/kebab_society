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
        <g class="ks-i-flicker">
            <path d="M12 2.5c3.4 3.1 6 6 6 9.6a6 6 0 0 1-12 0c0-2 .9-3.6 2.2-5 .3 1.2.9 2 1.8 2.4-.4-2.6.4-5 2-7Z" />
        </g>
    </svg>
</template>
