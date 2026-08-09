<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import SearchIcon from './Icons/SearchIcon.vue';
import CloseIcon from './Icons/CloseIcon.vue';
import MapPinIcon from './Icons/MapPinIcon.vue';

/**
 * Suburb search.
 *
 * Typing filters the map; picking a suburb from the list flies the map to it.
 * Minimal typing is a hard requirement — most people are using this in the
 * street, at night, with one hand.
 */
const props = defineProps({
    suburbs: { type: Array, default: () => [] },
    modelValue: { type: String, default: '' },
    activeSuburb: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue', 'select-suburb', 'clear']);

const MAX_SUGGESTIONS = 7;

const input = ref(null);
const isOpen = ref(false);
const highlighted = ref(-1);

const query = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

const matches = computed(() => {
    const term = props.modelValue.trim().toLowerCase();

    if (term.length < 1) {
        return [];
    }

    return props.suburbs
        .filter(
            (suburb) =>
                suburb.name.toLowerCase().includes(term) ||
                suburb.region.toLowerCase().includes(term) ||
                suburb.postcode.startsWith(term),
        )
        .slice(0, MAX_SUGGESTIONS);
});

const activeSuburbName = computed(
    () => props.suburbs.find((suburb) => suburb.slug === props.activeSuburb)?.name ?? null,
);

watch(matches, () => {
    highlighted.value = -1;
});

function open() {
    isOpen.value = true;
}

function close() {
    isOpen.value = false;
    highlighted.value = -1;
}

function choose(suburb) {
    emit('select-suburb', suburb);
    close();
}

function onKeydown(event) {
    if (event.key === 'Escape') {
        close();
        return;
    }

    if (!matches.value.length) {
        return;
    }

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        isOpen.value = true;
        highlighted.value = (highlighted.value + 1) % matches.value.length;
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        highlighted.value = (highlighted.value - 1 + matches.value.length) % matches.value.length;
    } else if (event.key === 'Enter' && highlighted.value >= 0) {
        event.preventDefault();
        choose(matches.value[highlighted.value]);
    }
}

async function clear() {
    emit('clear');
    close();
    await nextTick();
    input.value?.focus();
}
</script>

<template>
    <div class="relative">
        <div class="ks-anim flex items-center gap-2 border-2 border-ink bg-garlic px-3 py-2.5 focus-within:stamped-sm">
            <SearchIcon :size="18" :stroke-width="2.4" class="shrink-0 text-ink/60" />
            <input
                ref="input"
                v-model="query"
                type="search"
                inputmode="search"
                autocomplete="off"
                enterkeyhint="search"
                class="w-full min-w-0 bg-transparent text-base outline-none placeholder:text-ink/40"
                placeholder="Search a suburb, shop or postcode"
                aria-label="Search kebab shops by suburb, name or postcode"
                role="combobox"
                :aria-expanded="isOpen && matches.length > 0"
                aria-controls="ks-suburb-list"
                @focus="open"
                @input="open"
                @keydown="onKeydown"
                @blur="close"
            />
            <button
                v-if="modelValue || activeSuburb"
                type="button"
                class="ks-anim shrink-0 text-ink/50 transition-colors hover:text-tomato"
                aria-label="Clear search"
                @mousedown.prevent="clear"
            >
                <CloseIcon :size="16" :stroke-width="2.5" />
            </button>
        </div>

        <p v-if="activeSuburbName" class="label-caps mt-1.5 text-tomato">
            Patrolling {{ activeSuburbName }}
        </p>

        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="-translate-y-1 opacity-0"
            leave-active-class="transition duration-100 ease-in"
            leave-to-class="opacity-0"
        >
            <ul
                v-if="isOpen && matches.length"
                id="ks-suburb-list"
                role="listbox"
                class="absolute z-30 mt-1 w-full border-2 border-ink bg-garlic stamped-sm"
            >
                <li
                    v-for="(suburb, index) in matches"
                    :key="suburb.slug"
                    role="option"
                    :aria-selected="index === highlighted"
                    class="ks-anim flex cursor-pointer items-center gap-2 border-b border-ink/10 px-3 py-2 last:border-b-0"
                    :class="index === highlighted ? 'bg-tomato text-garlic' : 'hover:bg-cream-deep'"
                    @mousedown.prevent="choose(suburb)"
                    @mouseenter="highlighted = index"
                >
                    <MapPinIcon :size="15" :stroke-width="2.4" class="shrink-0 opacity-70" />
                    <span class="truncate text-sm font-semibold">{{ suburb.name }}</span>
                    <span class="ml-auto shrink-0 text-xs opacity-60">{{ suburb.region }} {{ suburb.postcode }}</span>
                </li>
            </ul>
        </Transition>
    </div>
</template>
