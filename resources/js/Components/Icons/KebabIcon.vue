<script setup>
defineProps({
    size: { type: [Number, String], default: 20 },
    strokeWidth: { type: [Number, String], default: 2 },
});
</script>

<template>
    <!-- A doner spit: the Society's unit of measurement. -->
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
        <line x1="12" y1="1.5" x2="12" y2="4" />
        <line x1="12" y1="20" x2="12" y2="22.5" />
        <g class="ks-i-wobble">
            <path d="M12 4c3.2 0 5 2.6 5 6.4 0 4.6-2.2 9.6-5 9.6s-5-5-5-9.6C7 6.6 8.8 4 12 4Z" />
            <line x1="7.6" y1="8.5" x2="16.4" y2="8.5" />
            <line x1="7.1" y1="12" x2="16.9" y2="12" />
            <line x1="8.2" y1="15.5" x2="15.8" y2="15.5" />
        </g>
    </svg>
</template>
