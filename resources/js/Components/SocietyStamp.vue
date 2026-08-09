<script setup>
/**
 * The Society's approval stamp.
 *
 * Only shown on restaurants an editor has approved in person. It lands rather
 * than fades, because a stamp should sound like something.
 */
defineProps({
    size: { type: Number, default: 96 },
    animated: { type: Boolean, default: true },
    label: { type: String, default: 'Society Approved' },
});
</script>

<template>
    <img
        src="/images/brand/society-approved-stamp.png"
        :alt="label"
        :title="label"
        :width="size"
        :style="{ width: `${size}px` }"
        class="pointer-events-none select-none"
        :class="animated ? 'ks-stamp' : 'ks-stamp-static'"
        draggable="false"
    />
</template>

<style scoped>
.ks-stamp {
    animation: ks-stamp-in 520ms cubic-bezier(0.2, 0.9, 0.3, 1.2) both;
    transform: rotate(-9deg);
}

.ks-stamp-static {
    transform: rotate(-9deg);
}
</style>
