<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import SocietyLayout from '../../Layouts/SocietyLayout.vue';
import KebabMap from '../../Components/KebabMap.vue';
import SuburbSearch from '../../Components/SuburbSearch.vue';
import FilterBar from '../../Components/FilterBar.vue';
import RestaurantCard from '../../Components/RestaurantCard.vue';
import RestaurantPreviewDialog from '../../Components/RestaurantPreviewDialog.vue';
import KebabEmergencyButton from '../../Components/KebabEmergencyButton.vue';
import MapPinIcon from '../../Components/Icons/MapPinIcon.vue';
import TrophyIcon from '../../Components/Icons/TrophyIcon.vue';
import ArrowRightIcon from '../../Components/Icons/ArrowRightIcon.vue';
import SlidersIcon from '../../Components/Icons/SlidersIcon.vue';

const props = defineProps({
    restaurants: { type: Array, default: () => [] },
    filters: { type: Object, required: true },
    styles: { type: Array, default: () => [] },
    suburbs: { type: Array, default: () => [] },
    tiers: { type: Array, default: () => [] },
    map: { type: Object, required: true },
    leaderboards: { type: Array, default: () => [] },
});

const SEARCH_DEBOUNCE_MS = 300;

const mapRef = ref(null);
const selected = ref(null);
const mobileView = ref('map');
const filtersOpen = ref(false);
const filterRoot = ref(null);
const search = ref(props.filters.search ?? '');

/**
 * Filters are held locally as well as on the server so that tapping several in
 * quick succession composes correctly instead of racing the round-trip.
 */
const activeFilters = ref({ ...props.filters });

let searchTimer = null;

const openCount = computed(() => props.restaurants.filter((restaurant) => restaurant.is_open_now).length);
const certifiedCount = computed(() => props.restaurants.filter((restaurant) => restaurant.society_approved).length);

const activeFilterCount = computed(() => {
    const { open_now, late_night, society_certified, min_rating, styles, suburb } = activeFilters.value;

    return [open_now, late_night, society_certified, min_rating > 0, styles?.length > 0, !!suburb].filter(Boolean)
        .length;
});

/** Push the current filter state to the server and let it do the filtering. */
function applyFilters(changes = {}) {
    activeFilters.value = { ...activeFilters.value, search: search.value, ...changes };

    router.get('/', pruneEmpty(activeFilters.value), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['restaurants', 'filters'],
    });
}

function pruneEmpty(filters) {
    return Object.fromEntries(
        Object.entries({
            search: filters.search || undefined,
            styles: filters.styles?.length ? filters.styles : undefined,
            // Booleans travel as 1 so the query string stays valid and shareable.
            open_now: filters.open_now ? 1 : undefined,
            late_night: filters.late_night ? 1 : undefined,
            society_certified: filters.society_certified ? 1 : undefined,
            min_rating: filters.min_rating || undefined,
            suburb: filters.suburb || undefined,
        }).filter(([, value]) => value !== undefined),
    );
}

function resetFilters() {
    search.value = '';
    activeFilters.value = {
        search: '',
        styles: [],
        open_now: false,
        late_night: false,
        society_certified: false,
        min_rating: 0,
        suburb: null,
    };

    router.get('/', {}, { preserveState: true, preserveScroll: true, replace: true, only: ['restaurants', 'filters'] });
}

function onSuburbSelected(suburb) {
    search.value = '';
    mapRef.value?.flyTo(suburb.latitude, suburb.longitude, 14);
    applyFilters({ search: '', suburb: suburb.slug });
    mobileView.value = 'map';
}

function focusRestaurant(restaurant) {
    mobileView.value = 'map';
    mapRef.value?.flyTo(restaurant.latitude, restaurant.longitude, 16);
}

function openPreview(restaurant) {
    selected.value = restaurant;
}

/** The filter menu is a popover, so it closes on outside click and on Escape. */
function onDocumentPointerDown(event) {
    if (filtersOpen.value && filterRoot.value && !filterRoot.value.contains(event.target)) {
        filtersOpen.value = false;
    }
}

function onDocumentKeydown(event) {
    if (event.key === 'Escape') {
        filtersOpen.value = false;
    }
}

onMounted(() => {
    document.addEventListener('pointerdown', onDocumentPointerDown);
    document.addEventListener('keydown', onDocumentKeydown);
});

onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', onDocumentPointerDown);
    document.removeEventListener('keydown', onDocumentKeydown);
});

watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => applyFilters(), SEARCH_DEBOUNCE_MS);
});

// Re-sync once the server confirms what it actually applied.
watch(
    () => props.filters,
    (filters) => {
        activeFilters.value = { ...filters };
    },
);
</script>

<template>
    <Head>
        <title>Sydney Kebab Map</title>
        <meta
            name="description"
            content="Every kebab in Sydney, ranked, mapped and judged by the Kebab Society. Find what is open now, what is worth the trip, and what the Society has concerns about."
        />
    </Head>

    <SocietyLayout full-bleed>
        <div class="flex min-h-0 flex-1 flex-col lg:flex-row">
            <!-- CONTROLS -->
            <aside
                class="z-20 flex shrink-0 flex-col border-ink bg-cream lg:w-[400px] lg:border-r-2 xl:w-[440px]"
                :class="mobileView === 'list' ? 'min-h-0 flex-1 border-b-2' : 'border-b-2'"
            >
                <div class="space-y-3 border-b-2 border-ink/10 p-4">
                    <div ref="filterRoot" class="relative flex items-start gap-2">
                        <div class="min-w-0 flex-1">
                            <SuburbSearch
                                v-model="search"
                                :suburbs="suburbs"
                                :active-suburb="activeFilters.suburb"
                                @select-suburb="onSuburbSelected"
                                @clear="resetFilters"
                            />
                        </div>

                        <!-- Filters live behind this icon so the map keeps the screen. -->
                        <button
                            type="button"
                            class="ks-anim relative flex h-12 w-12 shrink-0 items-center justify-center border-2 border-ink transition-colors"
                            :class="filtersOpen ? 'bg-ink text-garlic' : 'bg-garlic hover:bg-cream-deep'"
                            :aria-expanded="filtersOpen"
                            aria-controls="ks-filters"
                            aria-haspopup="dialog"
                            :aria-label="
                                activeFilterCount ? `Filters, ${activeFilterCount} active` : 'Filters'
                            "
                            @click="filtersOpen = !filtersOpen"
                        >
                            <SlidersIcon :size="18" :stroke-width="2.4" />
                            <span
                                v-if="activeFilterCount"
                                class="label-caps absolute -right-2 -top-2 border-2 border-ink bg-tomato px-1.5 py-0.5 text-garlic"
                            >
                                {{ activeFilterCount }}
                            </span>
                        </button>

                        <Transition
                            enter-active-class="transition duration-150 ease-out"
                            enter-from-class="-translate-y-1 opacity-0"
                            leave-active-class="transition duration-100 ease-in"
                            leave-to-class="opacity-0"
                        >
                            <div
                                v-if="filtersOpen"
                                id="ks-filters"
                                role="dialog"
                                aria-label="Filters"
                                class="absolute right-0 top-full z-40 mt-2 w-[min(24rem,calc(100vw-2rem))] border-2 border-ink bg-garlic p-4 stamped-sm"
                            >
                                <FilterBar
                                    :filters="activeFilters"
                                    :styles="styles"
                                    :result-count="restaurants.length"
                                    @update="applyFilters"
                                    @reset="resetFilters"
                                />
                            </div>
                        </Transition>
                    </div>

                    <KebabEmergencyButton @locate="focusRestaurant" @select="openPreview" />
                </div>

                <!-- Results list: always visible on desktop, a tab on mobile. -->
                <div
                    class="min-h-0 flex-1 overflow-y-auto p-4"
                    :class="mobileView === 'list' ? 'block' : 'hidden lg:block'"
                >
                    <div class="mb-3 flex items-baseline justify-between gap-2">
                        <h2 class="text-sm">The register</h2>
                        <p class="label-caps text-ink/45">{{ openCount }} open now</p>
                    </div>

                    <div v-if="restaurants.length" class="space-y-3">
                        <RestaurantCard
                            v-for="restaurant in restaurants"
                            :key="restaurant.id"
                            :restaurant="restaurant"
                            @locate="focusRestaurant"
                        />
                    </div>

                    <div v-else class="border-2 border-dashed border-ink/30 p-6 text-center">
                        <p class="font-display text-lg font-black">No kebabs meet these standards.</p>
                        <p class="mt-1 text-sm text-ink/60">
                            The Society suggests relaxing at least one of your requirements.
                        </p>
                        <button
                            type="button"
                            class="ks-anim mt-4 inline-flex items-center gap-1.5 border-2 border-ink bg-garlic px-3 py-2 hover:bg-cream-deep"
                            @click="resetFilters"
                        >
                            <span class="label-caps">Clear all filters</span>
                        </button>
                    </div>

                    <div class="mt-5 border-t-2 border-ink/10 pt-4">
                        <p class="label-caps text-ink/45">Also settled by the Society</p>
                        <ul class="mt-2 space-y-1.5">
                            <li v-for="board in leaderboards" :key="board.key">
                                <Link
                                    :href="`/leaderboard/${board.key}`"
                                    class="ks-anim group flex items-center gap-2 border-2 border-ink bg-garlic px-3 py-2 transition-colors hover:bg-cream-deep"
                                >
                                    <TrophyIcon :size="15" :stroke-width="2.4" class="shrink-0 text-gold" />
                                    <span class="truncate text-sm font-semibold">{{ board.title }}</span>
                                    <ArrowRightIcon :size="14" :stroke-width="2.4" class="ml-auto shrink-0 text-tomato" />
                                </Link>
                            </li>
                        </ul>
                    </div>
                </div>
            </aside>

            <!-- MAP -->
            <div class="relative min-h-0 flex-1" :class="mobileView === 'list' ? 'hidden lg:block' : 'block'">
                <KebabMap
                    ref="mapRef"
                    :restaurants="restaurants"
                    :config="map"
                    :selected-id="selected?.id ?? null"
                    @select="openPreview"
                />

                <!-- Legend: what the markers mean. -->
                <div
                    class="pointer-events-none absolute left-3 top-3 hidden border-2 border-ink bg-garlic/95 p-2.5 stamped-sm md:block"
                >
                    <p class="label-caps text-ink/50">Society rating</p>
                    <ul class="mt-2 space-y-1">
                        <li
                            v-for="tier in [...tiers].reverse()"
                            :key="tier.key"
                            class="flex items-center gap-2 text-[11px] leading-none"
                        >
                            <span class="inline-block h-2.5 w-2.5 border border-ink" :style="{ background: tier.colour }" />
                            <span class="font-bold tracking-wide">{{ tier.label }}</span>
                            <span class="ml-auto text-ink/45">
                                {{ tier.stars ? `${tier.min.toFixed(1)}+` : 'no rating' }}
                            </span>
                        </li>
                    </ul>
                </div>

                <div
                    class="pointer-events-none absolute right-3 top-3 border-2 border-ink bg-char/95 px-3 py-2 text-garlic"
                >
                    <p class="label-caps text-gold">{{ restaurants.length }} on the map</p>
                    <p class="mt-1 text-[11px] text-garlic/70">
                        {{ certifiedCount }} Society Certified · {{ openCount }} open now
                    </p>
                </div>
            </div>

            <!-- MOBILE VIEW SWITCH -->
            <div class="z-30 flex shrink-0 border-t-2 border-ink bg-garlic lg:hidden">
                <button
                    type="button"
                    class="ks-anim flex flex-1 items-center justify-center gap-2 py-3 transition-colors"
                    :class="mobileView === 'map' ? 'bg-ink text-garlic' : ''"
                    @click="mobileView = 'map'"
                >
                    <MapPinIcon :size="16" :stroke-width="2.4" />
                    <span class="label-caps">Map</span>
                </button>
                <button
                    type="button"
                    class="ks-anim flex flex-1 items-center justify-center gap-2 border-l-2 border-ink py-3 transition-colors"
                    :class="mobileView === 'list' ? 'bg-ink text-garlic' : ''"
                    @click="mobileView = 'list'"
                >
                    <span class="label-caps">List ({{ restaurants.length }})</span>
                </button>
            </div>
        </div>

        <RestaurantPreviewDialog :restaurant="selected" @close="selected = null" />
    </SocietyLayout>
</template>
