<script setup>
import AppHeader from '../Components/AppHeader.vue';
import AppFooter from '../Components/AppFooter.vue';

defineProps({
    /** Full-bleed pages (the map) manage their own scrolling. */
    fullBleed: { type: Boolean, default: false },
});
</script>

<template>
    <div :class="fullBleed ? 'flex h-dvh flex-col overflow-hidden bg-paper' : 'flex min-h-dvh flex-col bg-paper'">
        <aside class="z-50 flex min-h-11 items-center justify-center gap-x-3 gap-y-1 border-b border-ink bg-butter px-4 py-2 text-center" aria-label="Domain sale notice">
            <span class="label-caps text-ink">This domain is for sale</span>
            <a
                class="label-caps border-b border-ink text-ink transition-opacity hover:opacity-60"
                href="https://www.godaddy.com/forsale/kslive.au?utm_source=TDFS_BINNS2&utm_medium=parkedpages&utm_campaign=x_corp_tdfs-binns2_base"
                target="_blank"
                rel="noopener noreferrer"
            >
                Click to buy <span aria-hidden="true">↗</span>
            </a>
        </aside>
        <AppHeader />

        <main :class="fullBleed ? 'flex min-h-0 flex-1 flex-col' : 'flex-1'">
            <slot />
        </main>

        <AppFooter v-if="!fullBleed" />
    </div>
</template>
