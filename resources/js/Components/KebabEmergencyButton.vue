<script setup>
import { ref } from 'vue';
import SirenIcon from './Icons/SirenIcon.vue';
import CloseIcon from './Icons/CloseIcon.vue';
import { formatDistance } from '../lib/format';

/**
 * KEBAB EMERGENCY
 *
 * One button. Finds the nearest kebab that is actually open.
 */
const emit = defineEmits(['select', 'locate']);

const GEOLOCATION_TIMEOUT = 10000;

const state = ref('idle'); // idle | locating | searching | done | error
const error = ref(null);
const result = ref(null);

function currentPosition() {
    return new Promise((resolve, reject) => {
        if (!navigator.geolocation) {
            reject(new Error('This device will not share its location.'));
            return;
        }

        navigator.geolocation.getCurrentPosition(resolve, reject, {
            enableHighAccuracy: true,
            timeout: GEOLOCATION_TIMEOUT,
            maximumAge: 60000,
        });
    });
}

async function declareEmergency() {
    state.value = 'locating';
    error.value = null;
    result.value = null;

    try {
        const position = await currentPosition();
        state.value = 'searching';

        const params = new URLSearchParams({
            latitude: position.coords.latitude,
            longitude: position.coords.longitude,
            limit: '3',
        });

        const response = await fetch(`/api/kebab-emergency?${params}`, {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            throw new Error('The Society could not be reached.');
        }

        result.value = await response.json();
        state.value = 'done';
    } catch (exception) {
        error.value =
            exception?.code === 1
                ? 'Location denied. Search a suburb instead.'
                : (exception?.message ?? 'Something went wrong.');
        state.value = 'error';
    }
}

function dismiss() {
    state.value = 'idle';
    result.value = null;
    error.value = null;
}

function choose(restaurant) {
    emit('locate', restaurant);
    emit('select', restaurant);
}
</script>

<template>
    <div>
        <button
            type="button"
            class="ks-anim group flex w-full items-center justify-center gap-2.5 border-2 border-ink bg-tomato px-4 py-3.5 text-garlic transition-all hover:bg-tomato-deep hover:stamped-sm disabled:opacity-70"
            :disabled="state === 'locating' || state === 'searching'"
            @click="declareEmergency"
        >
            <SirenIcon :size="20" :stroke-width="2.4" />
            <span class="label-caps text-[13px]">
                <template v-if="state === 'locating'">Finding you…</template>
                <template v-else-if="state === 'searching'">Assessing the situation…</template>
                <template v-else>I need a kebab</template>
            </span>
        </button>

        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="-translate-y-1 opacity-0"
            leave-active-class="transition duration-150 ease-in"
            leave-to-class="opacity-0"
        >
            <div v-if="state === 'done' && result" class="mt-2 border-2 border-ink bg-char p-3 text-garlic">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="label-caps text-gold">
                            {{ result.any_open ? 'You are in kebab danger' : 'Nothing is open' }}
                        </p>
                        <p class="mt-1 text-sm leading-snug">
                            <template v-if="result.any_open">
                                {{ result.within_one_km }}
                                {{ result.within_one_km === 1 ? 'kebab is' : 'kebabs are' }} within 1km and trading.
                            </template>
                            <template v-else>
                                The Society is sorry. These are the closest, for when they reopen.
                            </template>
                        </p>
                    </div>
                    <button
                        type="button"
                        class="ks-anim shrink-0 opacity-70 hover:opacity-100"
                        aria-label="Dismiss"
                        @click="dismiss"
                    >
                        <CloseIcon :size="16" :stroke-width="2.5" />
                    </button>
                </div>

                <ul class="mt-3 space-y-1.5">
                    <li v-for="restaurant in result.results" :key="restaurant.id">
                        <button
                            type="button"
                            class="ks-anim flex w-full items-center gap-2.5 border border-garlic/25 bg-garlic/5 p-2 text-left transition-colors hover:bg-garlic/15"
                            @click="choose(restaurant)"
                        >
                            <img :src="restaurant.marker_icon" alt="" class="h-9 w-auto shrink-0" draggable="false" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold">{{ restaurant.name }}</span>
                                <span class="block truncate text-xs opacity-70">
                                    {{ formatDistance(restaurant.distance_km) }} ·
                                    {{ restaurant.suburb?.name }}
                                </span>
                            </span>
                            <span class="font-display text-lg font-black tabular-nums text-gold">
                                {{ restaurant.is_rated ? restaurant.kebab_rating.toFixed(1) : '—' }}
                            </span>
                        </button>
                    </li>
                </ul>
            </div>

            <p v-else-if="state === 'error'" class="mt-2 border-2 border-ink bg-cream-deep p-3 text-sm">
                {{ error }}
            </p>
        </Transition>
    </div>
</template>
