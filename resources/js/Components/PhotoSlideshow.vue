<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import ArrowRightIcon from './Icons/ArrowRightIcon.vue';

/**
 * Photograph slideshow.
 *
 * Keyboard navigable, swipeable, and quiet when a restaurant has no photos yet.
 */
const props = defineProps({
    photos: { type: Array, default: () => [] },
    format: { type: String, default: 'card' },
    heightClass: { type: String, default: 'h-56 sm:h-64' },
    alt: { type: String, default: 'Restaurant photograph' },
});

const index = ref(0);
const touchStartX = ref(null);

const count = computed(() => props.photos.length);
const current = computed(() => props.photos[index.value] ?? null);

function go(step) {
    if (count.value === 0) {
        return;
    }

    index.value = (index.value + step + count.value) % count.value;
}

function onKeydown(event) {
    if (count.value < 2) {
        return;
    }

    if (event.key === 'ArrowRight') {
        event.preventDefault();
        go(1);
    } else if (event.key === 'ArrowLeft') {
        event.preventDefault();
        go(-1);
    }
}

function onTouchStart(event) {
    touchStartX.value = event.changedTouches[0]?.clientX ?? null;
}

function onTouchEnd(event) {
    if (touchStartX.value === null) {
        return;
    }

    const delta = (event.changedTouches[0]?.clientX ?? 0) - touchStartX.value;
    touchStartX.value = null;

    if (Math.abs(delta) > 40) {
        go(delta < 0 ? 1 : -1);
    }
}

watch(() => props.photos, () => {
    index.value = 0;
});

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <div
        v-if="count"
        class="relative overflow-hidden border-b-2 border-ink bg-ink"
        :class="heightClass"
        @touchstart.passive="onTouchStart"
        @touchend.passive="onTouchEnd"
    >
        <Transition
            mode="out-in"
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 scale-[1.02]"
            leave-active-class="transition duration-150 ease-in"
            leave-to-class="opacity-0"
        >
            <img
                :key="current.id"
                :src="current[format] || current.card || current.hero"
                :alt="current.caption || alt"
                class="h-full w-full object-cover"
                loading="lazy"
                draggable="false"
            />
        </Transition>

        <p
            v-if="current.caption"
            class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-ink/85 to-transparent px-3 pb-2 pt-6 text-xs text-garlic"
        >
            {{ current.caption }}
            <span v-if="current.credit" class="text-garlic/60"> · {{ current.credit }}</span>
        </p>

        <template v-if="count > 1">
            <button
                type="button"
                class="ks-anim absolute left-2 top-1/2 -translate-y-1/2 border-2 border-ink bg-garlic/90 p-1.5 text-ink transition-colors hover:bg-garlic"
                aria-label="Previous photograph"
                @click.stop="go(-1)"
            >
                <ArrowRightIcon :size="16" :stroke-width="2.5" class="rotate-180" />
            </button>
            <button
                type="button"
                class="ks-anim absolute right-2 top-1/2 -translate-y-1/2 border-2 border-ink bg-garlic/90 p-1.5 text-ink transition-colors hover:bg-garlic"
                aria-label="Next photograph"
                @click.stop="go(1)"
            >
                <ArrowRightIcon :size="16" :stroke-width="2.5" />
            </button>

            <div class="absolute right-2 top-2 flex gap-1">
                <button
                    v-for="(photo, position) in photos"
                    :key="photo.id"
                    type="button"
                    class="h-1.5 w-4 border border-ink transition-colors"
                    :class="position === index ? 'bg-tomato' : 'bg-garlic/70 hover:bg-garlic'"
                    :aria-label="`Photograph ${position + 1} of ${count}`"
                    :aria-current="position === index"
                    @click.stop="index = position"
                />
            </div>
        </template>
    </div>
</template>
