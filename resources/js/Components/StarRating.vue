<script setup>
import { computed } from 'vue';

/**
 * Five star rating, drawn with partial fill so 4.3 does not read as 4.
 */
const props = defineProps({
    rating: { type: Number, default: null },
    size: { type: Number, default: 18 },
    colour: { type: String, default: 'currentColor' },
});

const stars = [0, 1, 2, 3, 4];

const percentages = computed(() =>
    stars.map((index) => {
        if (!Number.isFinite(props.rating)) {
            return 0;
        }

        return Math.max(0, Math.min(1, props.rating - index)) * 100;
    }),
);

const STAR_PATH = 'm12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1-5.4-2.9-5.4 2.9 1-6.1L3.2 9.5l6.1-.9L12 3Z';
</script>

<template>
    <span
        class="inline-flex items-center gap-0.5"
        role="img"
        :aria-label="Number.isFinite(rating) ? `${rating} out of 5 stars` : 'Not yet rated'"
    >
        <svg
            v-for="(fill, index) in percentages"
            :key="index"
            :width="size"
            :height="size"
            viewBox="0 0 24 24"
            aria-hidden="true"
            class="shrink-0"
        >
            <defs>
                <linearGradient :id="`ks-star-${index}-${size}-${Math.round(fill)}`" x1="0" x2="1" y1="0" y2="0">
                    <stop :offset="`${fill}%`" :stop-color="colour" />
                    <stop :offset="`${fill}%`" stop-color="transparent" />
                </linearGradient>
            </defs>
            <path
                :d="STAR_PATH"
                :fill="`url(#ks-star-${index}-${size}-${Math.round(fill)})`"
                :stroke="colour"
                stroke-width="1.5"
                stroke-linejoin="round"
                :opacity="fill > 0 ? 1 : 0.35"
            />
        </svg>
    </span>
</template>
