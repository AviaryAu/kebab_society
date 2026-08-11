<script setup>
import { computed, nextTick, onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import SocietyLayout from '../../Layouts/SocietyLayout.vue';
import LiveMap from '../../Components/LiveMap.vue';
import SearchIcon from '../../Components/Icons/SearchIcon.vue';

const props = defineProps({
    map: { type: Object, required: true },
    items: { type: Array, default: () => [] },
});

const FILTERS = [
    { key: 'all', label: 'All' },
    { key: 'event', label: 'Events' },
    { key: 'venue', label: 'Venues' },
];

const mapRef = ref(null);
const filter = ref('all');
const search = ref('');
const selected = ref(null);
const mobileView = ref('map');

const visibleItems = computed(() => {
    const term = search.value.trim().toLowerCase();

    return props.items.filter((item) => {
        if (filter.value !== 'all' && item.type !== filter.value) {
            return false;
        }

        if (!term) {
            return true;
        }

        return [item.name, item.suburb, item.category]
            .filter(Boolean)
            .some((field) => field.toLowerCase().includes(term));
    });
});

const eventCount = computed(() => visibleItems.value.filter((item) => item.type === 'event').length);

function focusItem(item) {
    selected.value = item;
    mobileView.value = 'map';
    mapRef.value?.flyTo(item.latitude, item.longitude, 15);
}

function setFilter(key) {
    filter.value = key;
    selected.value = null;
    nextTick(() => mapRef.value?.fitToItems(visibleItems.value));
}

function resetView() {
    search.value = '';
    filter.value = 'all';
    selected.value = null;
    nextTick(() => mapRef.value?.fitToItems(props.items));
}

onMounted(() => {
    nextTick(() => mapRef.value?.fitToItems(props.items));
});
</script>

<template>
    <Head>
        <title>The Map — Sydney Events & Venues</title>
        <meta
            name="description"
            content="An editorial map of Sydney. Every event and venue on Keep Sydney Live, filtered by night, suburb or section."
        />
        <link rel="canonical" href="https://kslive.au/map" />
    </Head>

    <SocietyLayout full-bleed>
        <div class="flex min-h-0 flex-1 flex-col lg:flex-row">
            <aside
                class="z-20 flex shrink-0 flex-col border-ink bg-paper lg:w-[380px] lg:border-r xl:w-[420px]"
                :class="mobileView === 'list' ? 'min-h-0 flex-1 border-b' : 'border-b'"
            >
                <div class="space-y-6 border-b border-ink/15 px-6 py-6">
                    <div>
                        <p class="label-caps text-charcoal">The Map</p>
                        <h1 class="mt-2 text-4xl leading-none">Sydney, live.</h1>
                    </div>

                    <label class="relative block">
                        <span class="sr-only">Search events and venues by name or suburb</span>
                        <SearchIcon
                            :size="16"
                            :stroke-width="1.75"
                            class="pointer-events-none absolute left-0 top-1/2 -translate-y-1/2 text-charcoal"
                        />
                        <input
                            v-model="search"
                            type="search"
                            placeholder="Search a venue, suburb or section"
                            class="w-full border-0 border-b border-ink bg-transparent py-2.5 pl-7 text-base outline-none placeholder:text-charcoal/60 focus:border-ink"
                        />
                    </label>

                    <div class="flex flex-wrap gap-x-6 gap-y-2">
                        <button
                            v-for="option in FILTERS"
                            :key="option.key"
                            type="button"
                            class="label-caps transition-opacity hover:opacity-60"
                            :class="filter === option.key ? 'text-ink underline underline-offset-4' : 'text-charcoal'"
                            @click="setFilter(option.key)"
                        >
                            {{ option.label }}
                        </button>
                    </div>
                </div>

                <div
                    class="min-h-0 flex-1 overflow-y-auto px-6 py-4"
                    :class="mobileView === 'list' ? 'block' : 'hidden lg:block'"
                >
                    <p class="label-caps py-2 text-charcoal">{{ visibleItems.length }} places</p>

                    <ul v-if="visibleItems.length">
                        <li v-for="item in visibleItems" :key="item.id" class="border-t border-ink/15">
                            <button type="button" class="block w-full py-4 text-left" @click="focusItem(item)">
                                <span
                                    class="label-caps block"
                                    :class="selected?.id === item.id ? 'text-ink' : 'text-charcoal'"
                                >
                                    {{ item.type === 'event' ? item.category : 'Venue' }}
                                </span>
                                <span class="mt-1.5 block font-display text-xl leading-tight">{{ item.name }}</span>
                                <span class="mt-1 block text-sm text-charcoal">
                                    {{ item.suburb }}<template v-if="item.time"> &middot; {{ item.time }}</template>
                                </span>
                            </button>
                        </li>
                    </ul>

                    <div v-else class="border-t border-ink/15 py-10">
                        <p class="font-display text-2xl">Nothing matches that.</p>
                        <p class="mt-2 text-sm text-charcoal">Try a different suburb, venue or section.</p>
                        <button type="button" class="ks-link label-caps mt-5" @click="resetView">Reset the map</button>
                    </div>
                </div>
            </aside>

            <div class="relative min-h-0 flex-1" :class="mobileView === 'list' ? 'hidden lg:block' : 'block'">
                <LiveMap
                    ref="mapRef"
                    :items="visibleItems"
                    :config="map"
                    :selected-id="selected?.id ?? null"
                    @select="focusItem"
                />

                <div class="pointer-events-none absolute left-4 top-4 border border-ink bg-warm-white px-4 py-3">
                    <p class="label-caps">{{ visibleItems.length }} on the map</p>
                    <p class="mt-1.5 text-xs text-charcoal">{{ eventCount }} events happening</p>
                </div>

                <div v-if="selected" class="absolute bottom-6 left-4 max-w-xs border border-ink bg-warm-white p-5">
                    <p class="label-caps text-charcoal">
                        {{ selected.type === 'event' ? selected.category : 'Venue' }}
                    </p>
                    <p class="mt-2 font-display text-2xl leading-tight">{{ selected.name }}</p>
                    <p class="mt-1.5 text-sm text-charcoal">
                        {{ selected.suburb }}<template v-if="selected.time"> &middot; {{ selected.time }}</template>
                    </p>
                    <Link :href="selected.url" class="ks-link label-caps mt-4 inline-block">Open page</Link>
                </div>
            </div>

            <div class="z-30 flex shrink-0 border-t border-ink bg-warm-white lg:hidden">
                <button
                    type="button"
                    class="label-caps flex-1 py-4 transition-colors"
                    :class="mobileView === 'map' ? 'bg-ink text-warm-white' : ''"
                    @click="mobileView = 'map'"
                >
                    Map
                </button>
                <button
                    type="button"
                    class="label-caps flex-1 border-l border-ink py-4 transition-colors"
                    :class="mobileView === 'list' ? 'bg-ink text-warm-white' : ''"
                    @click="mobileView = 'list'"
                >
                    List ({{ visibleItems.length }})
                </button>
            </div>
        </div>
    </SocietyLayout>
</template>
