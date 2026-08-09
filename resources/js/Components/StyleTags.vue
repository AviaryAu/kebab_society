<script setup>
defineProps({
    styles: { type: Array, default: () => [] },
    limit: { type: Number, default: 4 },
});
</script>

<template>
    <ul v-if="styles.length" class="flex flex-wrap gap-1.5">
        <li
            v-for="style in styles.slice(0, limit)"
            :key="style.slug"
            class="label-caps border border-ink/40 px-1.5 py-1"
            :class="style.is_signature ? 'bg-ink text-garlic' : 'bg-cream-deep text-ink/80'"
            :title="style.description || style.name"
        >
            {{ style.name }}
        </li>
        <li v-if="styles.length > limit" class="label-caps px-1.5 py-1 text-ink/50">
            +{{ styles.length - limit }}
        </li>
    </ul>
</template>
