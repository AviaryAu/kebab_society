<script setup>
import AppHeader from '../Components/AppHeader.vue';
import AppFooter from '../Components/AppFooter.vue';

defineProps({
    /** Full-bleed pages (the map) manage their own scrolling. */
    fullBleed: { type: Boolean, default: false },
});
</script>

<template>
    <div :class="fullBleed ? 'flex h-dvh flex-col overflow-hidden paper' : 'flex min-h-dvh flex-col paper'">
        <AppHeader />

        <main :class="fullBleed ? 'flex min-h-0 flex-1 flex-col' : 'flex-1'">
            <slot />
        </main>

        <AppFooter v-if="!fullBleed" />
    </div>
</template>
