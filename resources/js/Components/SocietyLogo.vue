<script setup>
import { computed } from 'vue';

const props = defineProps({
    variant: {
        type: String,
        default: 'horizontal',
        validator: (value) => ['horizontal', 'stacked', 'seal', 'icon'].includes(value),
    },
});

const sources = {
    horizontal: '/images/brand/logo-horizontal.png',
    stacked: '/images/brand/logo-stacked.png',
    seal: '/images/brand/logo-seal.png',
    icon: '/images/brand/logo-icon.png',
};

const src = computed(() => sources[props.variant]);
</script>

<template>
    <img :src="src" alt="Kebab Society — Sydney, Australia" class="block h-full w-auto select-none" draggable="false" />
</template>
