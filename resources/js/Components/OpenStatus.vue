<script setup>
import { computed } from 'vue';
import ClockIcon from './Icons/ClockIcon.vue';

const props = defineProps({
    restaurant: { type: Object, required: true },
});

const state = computed(() => {
    if (props.restaurant.status === 'temporarily_closed') {
        return { text: 'Temporarily closed', tone: 'muted' };
    }

    if (props.restaurant.is_open_now) {
        return {
            text: props.restaurant.closes_at ? `Open until ${props.restaurant.closes_at}` : 'Open now',
            tone: 'open',
        };
    }

    return { text: props.restaurant.opens_at ? `Opens ${props.restaurant.opens_at}` : 'Closed', tone: 'closed' };
});

const tone = computed(
    () =>
        ({
            open: 'bg-lettuce text-garlic',
            closed: 'bg-ink text-garlic',
            muted: 'bg-cream-deep text-ink',
        })[state.value.tone],
);
</script>

<template>
    <span class="ks-anim inline-flex items-center gap-1.5 border-2 border-ink px-2 py-1" :class="tone">
        <ClockIcon :size="13" :stroke-width="2.4" />
        <span class="label-caps">{{ state.text }}</span>
    </span>
</template>
