<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import OpenStatus from './OpenStatus.vue';
import StyleTags from './StyleTags.vue';
import SocietyStamp from './SocietyStamp.vue';
import PhotoSlideshow from './PhotoSlideshow.vue';
import StarRating from './StarRating.vue';
import CloseIcon from './Icons/CloseIcon.vue';
import RouteIcon from './Icons/RouteIcon.vue';
import PhoneIcon from './Icons/PhoneIcon.vue';
import ArrowRightIcon from './Icons/ArrowRightIcon.vue';
import StarIcon from './Icons/StarIcon.vue';
import { formatCount, formatDistance, formatPriceLevel } from '../lib/format';

const props = defineProps({
    restaurant: { type: Object, default: null },
});

const emit = defineEmits(['close']);

const panel = ref(null);
const closeButton = ref(null);

const distance = computed(() => formatDistance(props.restaurant?.distance_km));
const price = computed(() => formatPriceLevel(props.restaurant?.price_level));

watch(
    () => props.restaurant,
    async (restaurant) => {
        if (restaurant) {
            await nextTick();
            closeButton.value?.focus();
        }
    },
);

function onKeydown(event) {
    if (event.key === 'Escape') {
        emit('close');
    }
}
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            leave-active-class="transition duration-150 ease-in"
            leave-to-class="opacity-0"
        >
            <div
                v-if="restaurant"
                class="fixed inset-0 z-50 flex items-end justify-center bg-ink/55 p-0 sm:items-center sm:p-6"
                role="dialog"
                aria-modal="true"
                :aria-label="`${restaurant.name} preview`"
                @click.self="emit('close')"
                @keydown="onKeydown"
            >
                <Transition
                    appear
                    enter-active-class="transition duration-250 ease-out"
                    enter-from-class="translate-y-6 opacity-0 sm:translate-y-3 sm:scale-95"
                    leave-active-class="transition duration-150 ease-in"
                    leave-to-class="translate-y-4 opacity-0"
                >
                    <div
                        ref="panel"
                        class="relative max-h-[92dvh] w-full max-w-lg overflow-y-auto border-t-4 border-ink bg-garlic sm:border-4 sm:stamped"
                    >
                        <button
                            ref="closeButton"
                            type="button"
                            class="ks-anim absolute right-3 top-3 z-10 border-2 border-ink bg-cream p-1.5 text-ink transition-colors hover:bg-tomato hover:text-garlic"
                            aria-label="Close preview"
                            @click="emit('close')"
                        >
                            <CloseIcon :size="16" :stroke-width="2.5" />
                        </button>

                        <PhotoSlideshow
                            :photos="restaurant.photos ?? []"
                            :alt="restaurant.name"
                            height-class="h-52 sm:h-60"
                        />

                        <div class="flex items-start gap-4 border-b-2 border-ink bg-cream p-4 pr-14">
                            <img
                                :src="restaurant.marker_icon"
                                :alt="`${restaurant.tier.label} kebab marker`"
                                class="h-20 w-auto shrink-0"
                                draggable="false"
                            />

                            <div class="min-w-0 flex-1">
                                <p class="label-caps text-tomato">
                                    {{ restaurant.suburb?.name }}
                                    <span v-if="restaurant.suburb?.region" class="text-ink/45">
                                        · {{ restaurant.suburb.region }}
                                    </span>
                                </p>
                                <h2 class="mt-1 text-xl leading-tight">{{ restaurant.name }}</h2>
                                <p class="mt-1 text-sm text-ink/65">
                                    {{ restaurant.address_line }}
                                    <span v-if="distance"> · {{ distance }}</span>
                                    <span v-if="price"> · {{ price }}</span>
                                </p>
                            </div>

                            <SocietyStamp v-if="restaurant.society_approved" :size="72" class="-mr-1 hidden sm:block" />
                        </div>

                        <div class="space-y-4 p-4">
                            <div class="flex items-end gap-3">
                                <p
                                    class="font-display text-5xl font-black leading-none tabular-nums"
                                    :style="{ color: restaurant.tier.colour }"
                                >
                                    {{ restaurant.is_rated ? restaurant.kebab_rating.toFixed(1) : '—' }}
                                </p>
                                <div class="pb-1">
                                    <StarRating
                                        :rating="restaurant.kebab_rating"
                                        :size="18"
                                        :colour="restaurant.tier.colour"
                                    />
                                    <p class="label-caps mt-1.5" :style="{ color: restaurant.tier.colour }">
                                        {{ restaurant.tier.label }}
                                    </p>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div class="border-2 border-ink/15 bg-cream p-2.5">
                                    <p class="label-caps text-ink/45">Google rating</p>
                                    <p class="mt-1 flex items-center gap-1.5 font-display text-lg font-black">
                                        <StarIcon :size="15" filled :stroke-width="1.5" class="text-gold" />
                                        {{ restaurant.google_rating ?? '—' }}
                                        <span class="text-xs font-normal text-ink/50">
                                            ({{ formatCount(restaurant.google_review_count) }})
                                        </span>
                                    </p>
                                </div>
                                <div class="border-2 border-ink/15 bg-cream p-2.5">
                                    <p class="label-caps text-ink/45">Society reviews</p>
                                    <p class="mt-1 font-display text-lg font-black">
                                        {{ formatCount(restaurant.society_review_count) }}
                                        <span class="text-xs font-normal text-ink/50">
                                            · {{ formatCount(restaurant.check_in_count) }} check-ins
                                        </span>
                                    </p>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <OpenStatus v-if="restaurant.has_hours" :restaurant="restaurant" />
                                <span
                                    v-else
                                    class="label-caps border-2 border-dashed border-ink/25 px-2 py-1 text-ink/45"
                                >
                                    Trading hours not yet held
                                </span>
                                <span
                                    v-if="restaurant.trades_late_night"
                                    class="label-caps border-2 border-ink bg-char px-2 py-1 text-garlic"
                                >
                                    Late night
                                </span>
                            </div>

                            <StyleTags :styles="restaurant.styles" :limit="8" />
                        </div>

                        <div class="grid grid-cols-3 border-t-2 border-ink">
                            <a
                                :href="restaurant.directions_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="ks-anim flex flex-col items-center gap-1 border-r-2 border-ink p-3 text-ink transition-colors hover:bg-cream-deep"
                            >
                                <RouteIcon :size="18" />
                                <span class="label-caps text-[10px]">Directions</span>
                            </a>
                            <a
                                :href="restaurant.phone ? `tel:${restaurant.phone.replace(/\s/g, '')}` : null"
                                class="ks-anim flex flex-col items-center gap-1 border-r-2 border-ink p-3 text-ink transition-colors hover:bg-cream-deep"
                                :class="{ 'pointer-events-none opacity-35': !restaurant.phone }"
                            >
                                <PhoneIcon :size="18" />
                                <span class="label-caps text-[10px]">Call</span>
                            </a>
                            <Link
                                :href="restaurant.url"
                                class="ks-anim flex flex-col items-center gap-1 bg-tomato p-3 text-garlic transition-colors hover:bg-tomato-deep"
                            >
                                <ArrowRightIcon :size="18" />
                                <span class="label-caps text-[10px]">Full report</span>
                            </Link>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
