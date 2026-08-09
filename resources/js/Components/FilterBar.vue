<script setup>
import { computed } from 'vue';
import ClockIcon from './Icons/ClockIcon.vue';
import MoonIcon from './Icons/MoonIcon.vue';
import TrophyIcon from './Icons/TrophyIcon.vue';
import CheckIcon from './Icons/CheckIcon.vue';
import SlidersIcon from './Icons/SlidersIcon.vue';

/**
 * Map filters.
 *
 * Large targets, one tap each. No dropdowns inside dropdowns.
 */
const props = defineProps({
    filters: { type: Object, required: true },
    styles: { type: Array, default: () => [] },
    resultCount: { type: Number, default: 0 },
});

const emit = defineEmits(['update', 'reset']);

/** Rating at which the Society considers a kebab "top rated". */
const TOP_RATED = 4.0;

const isTopRated = computed(() => props.filters.min_rating >= TOP_RATED);

const activeCount = computed(() => {
    const { open_now, late_night, society_certified, min_rating, styles } = props.filters;

    return [open_now, late_night, society_certified, min_rating > 0, styles.length > 0].filter(Boolean).length;
});

function toggle(key) {
    emit('update', { [key]: !props.filters[key] });
}

function toggleTopRated() {
    emit('update', { min_rating: isTopRated.value ? 0 : TOP_RATED });
}

function toggleStyle(slug) {
    const styles = props.filters.styles.includes(slug)
        ? props.filters.styles.filter((value) => value !== slug)
        : [...props.filters.styles, slug];

    emit('update', { styles });
}
</script>

<template>
    <div>
        <div class="flex items-center justify-between gap-3">
            <p class="label-caps flex items-center gap-1.5 text-ink/55">
                <SlidersIcon :size="14" :stroke-width="2.4" />
                Filters
                <span v-if="activeCount" class="bg-tomato px-1.5 py-0.5 text-garlic">{{ activeCount }}</span>
            </p>
            <button
                v-if="activeCount"
                type="button"
                class="label-caps text-tomato underline underline-offset-2"
                @click="emit('reset')"
            >
                Clear all
            </button>
        </div>

        <div class="mt-2 flex flex-wrap gap-1.5">
            <button
                type="button"
                class="ks-anim inline-flex items-center gap-1.5 border-2 border-ink px-2.5 py-1.5 transition-colors"
                :class="filters.open_now ? 'bg-lettuce text-garlic' : 'bg-garlic hover:bg-cream-deep'"
                :aria-pressed="filters.open_now"
                @click="toggle('open_now')"
            >
                <ClockIcon :size="14" :stroke-width="2.4" />
                <span class="label-caps">Open now</span>
            </button>

            <button
                type="button"
                class="ks-anim inline-flex items-center gap-1.5 border-2 border-ink px-2.5 py-1.5 transition-colors"
                :class="filters.late_night ? 'bg-char text-garlic' : 'bg-garlic hover:bg-cream-deep'"
                :aria-pressed="filters.late_night"
                @click="toggle('late_night')"
            >
                <MoonIcon :size="14" :stroke-width="2.4" />
                <span class="label-caps">Late night</span>
            </button>

            <button
                type="button"
                class="ks-anim inline-flex items-center gap-1.5 border-2 border-ink px-2.5 py-1.5 transition-colors"
                :class="isTopRated ? 'bg-gold text-ink' : 'bg-garlic hover:bg-cream-deep'"
                :aria-pressed="isTopRated"
                @click="toggleTopRated"
            >
                <TrophyIcon :size="14" :stroke-width="2.4" />
                <span class="label-caps">4 stars +</span>
            </button>

            <button
                type="button"
                class="ks-anim inline-flex items-center gap-1.5 border-2 border-ink px-2.5 py-1.5 transition-colors"
                :class="filters.society_certified ? 'bg-tomato text-garlic' : 'bg-garlic hover:bg-cream-deep'"
                :aria-pressed="filters.society_certified"
                @click="toggle('society_certified')"
            >
                <CheckIcon :size="14" :stroke-width="2.4" />
                <span class="label-caps">Society certified</span>
            </button>
        </div>

        <div class="mt-3 flex flex-wrap gap-1.5">
            <button
                v-for="style in styles"
                :key="style.slug"
                type="button"
                class="border border-ink/35 px-2 py-1 transition-colors"
                :class="
                    filters.styles.includes(style.slug)
                        ? 'border-ink bg-ink text-garlic'
                        : 'bg-cream text-ink/75 hover:border-ink hover:bg-cream-deep'
                "
                :aria-pressed="filters.styles.includes(style.slug)"
                :title="style.description || style.name"
                @click="toggleStyle(style.slug)"
            >
                <span class="label-caps">{{ style.name }}</span>
            </button>
        </div>

        <p class="mt-3 border-t-2 border-ink/10 pt-2 text-sm text-ink/60">
            <span class="font-display text-base font-black text-ink">{{ resultCount }}</span>
            {{ resultCount === 1 ? 'kebab meets' : 'kebabs meet' }} the Society's criteria.
        </p>
    </div>
</template>
