<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import OpenStatus from './OpenStatus.vue';
import StyleTags from './StyleTags.vue';
import StarRating from './StarRating.vue';
import ArrowRightIcon from './Icons/ArrowRightIcon.vue';
import MapPinIcon from './Icons/MapPinIcon.vue';
import { formatDistance, formatPriceLevel } from '../lib/format';

const props = defineProps({
    restaurant: { type: Object, required: true },
    rank: { type: Number, default: null },
    compact: { type: Boolean, default: false },
});

defineEmits(['locate']);

const distance = computed(() => formatDistance(props.restaurant.distance_km));
const price = computed(() => formatPriceLevel(props.restaurant.price_level));
const photo = computed(() => props.restaurant.photos?.[0] ?? null);
</script>

<template>
    <article
        class="ks-anim group relative flex gap-3 border-2 border-ink bg-garlic p-3 transition-transform duration-200 hover:-translate-y-0.5 hover:stamped-sm"
    >
        <div v-if="rank !== null" class="flex w-10 shrink-0 flex-col items-center">
            <span class="font-display text-2xl font-black leading-none tabular-nums">{{ rank }}</span>
            <span class="label-caps mt-1 text-[9px] text-ink/45">Rank</span>
        </div>

        <div class="relative shrink-0 self-start">
            <img
                :src="restaurant.marker_icon"
                :alt="`${restaurant.tier.label} kebab marker`"
                class="h-16 w-auto transition-transform duration-300 group-hover:-rotate-6 group-hover:scale-110"
                draggable="false"
            />
            <img
                v-if="photo"
                :src="photo.thumb"
                :alt="photo.caption || restaurant.name"
                class="mt-2 h-12 w-16 border-2 border-ink object-cover"
                loading="lazy"
                draggable="false"
            />
        </div>

        <div class="min-w-0 flex-1">
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <h3 class="truncate text-base leading-tight">
                        <Link :href="restaurant.url" class="ks-link-underline">{{ restaurant.name }}</Link>
                    </h3>
                    <p class="mt-0.5 truncate text-xs text-ink/60">
                        {{ restaurant.suburb?.name }}
                        <span v-if="price"> · {{ price }}</span>
                        <span v-if="distance"> · {{ distance }}</span>
                    </p>
                </div>

                <div class="shrink-0 text-right">
                    <div
                        class="font-display text-2xl font-black leading-none tabular-nums"
                        :style="{ color: restaurant.tier.colour }"
                    >
                        {{ restaurant.is_rated ? restaurant.kebab_rating.toFixed(1) : '—' }}
                    </div>
                    <StarRating
                        :rating="restaurant.kebab_rating"
                        :size="11"
                        :colour="restaurant.tier.colour"
                        class="mt-1 justify-end"
                    />
                </div>
            </div>

            <div class="mt-2 flex flex-wrap items-center gap-2">
                <OpenStatus v-if="restaurant.has_hours" :restaurant="restaurant" />
                <span v-else class="label-caps border-2 border-dashed border-ink/25 px-2 py-1 text-ink/40">
                    Hours unknown
                </span>
                <img
                    v-if="restaurant.society_approved"
                    src="/images/brand/society-approved-stamp-sm.png"
                    alt="Society Approved"
                    title="Society Approved"
                    class="h-7 w-auto -rotate-6"
                    draggable="false"
                />
            </div>

            <StyleTags v-if="!compact" :styles="restaurant.styles" class="mt-2" />

            <div class="mt-2 flex items-center gap-3 text-xs">
                <button
                    type="button"
                    class="ks-anim inline-flex items-center gap-1 font-semibold text-char hover:text-tomato"
                    @click="$emit('locate', restaurant)"
                >
                    <MapPinIcon :size="14" :stroke-width="2.4" />
                    Show on map
                </button>
                <Link
                    :href="restaurant.url"
                    class="ks-anim ml-auto inline-flex items-center gap-1 font-semibold text-tomato"
                >
                    Full report
                    <ArrowRightIcon :size="14" :stroke-width="2.4" />
                </Link>
            </div>
        </div>
    </article>
</template>
