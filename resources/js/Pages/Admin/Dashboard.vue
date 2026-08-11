<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import ArrowRightIcon from '../../Components/Icons/ArrowRightIcon.vue';

defineProps({
    stats: { type: Object, required: true },
    upcoming: { type: Array, default: () => [] },
    needsAttention: { type: Object, required: true },
});

const SECTIONS = [
    {
        key: 'events',
        title: 'Events',
        href: '/admin/events',
        create: '/admin/events/create',
        metrics: [
            ['Total', 'total'],
            ['Published', 'published'],
            ['Upcoming', 'upcoming'],
            ['Drafts', 'drafts'],
        ],
    },
    {
        key: 'venues',
        title: 'Venues',
        href: '/admin/venues',
        create: '/admin/venues/create',
        metrics: [
            ['Total', 'total'],
            ['Published', 'published'],
            ['Drafts', 'drafts'],
            ['No coords', 'without_coordinates'],
        ],
    },
    {
        key: 'pages',
        title: 'Pages & guides',
        href: '/admin/pages',
        create: '/admin/pages/create',
        metrics: [
            ['Total', 'total'],
            ['Published', 'published'],
            ['Drafts', 'drafts'],
            ['Guides', 'guides'],
        ],
    },
];
</script>

<template>
    <Head>
        <title>Admin — Keep Sydney Live</title>
        <meta name="robots" content="noindex, nofollow" />
    </Head>

    <AdminLayout title="Overview">
        <div class="grid gap-4 lg:grid-cols-3">
            <section v-for="section in SECTIONS" :key="section.key" class="border-2 border-ink bg-garlic p-5">
                <div class="flex items-start justify-between gap-3">
                    <h2 class="font-display text-2xl leading-none">{{ section.title }}</h2>
                    <Link
                        :href="section.create"
                        class="ks-anim label-caps border-2 border-ink px-2.5 py-1.5 transition-colors hover:bg-ink hover:text-garlic"
                    >
                        New
                    </Link>
                </div>

                <dl class="mt-4 grid grid-cols-2 gap-3">
                    <div v-for="[label, key] in section.metrics" :key="key" class="border-t border-ink/15 pt-2">
                        <dt class="label-caps text-ink/45">{{ label }}</dt>
                        <dd class="font-display text-2xl tabular-nums">{{ stats[section.key][key] }}</dd>
                    </div>
                </dl>

                <Link :href="section.href" class="ks-anim label-caps mt-4 inline-flex items-center gap-1.5">
                    Manage {{ section.title.toLowerCase() }}
                    <ArrowRightIcon :size="14" :stroke-width="2.4" />
                </Link>
            </section>
        </div>

        <div class="mt-5 grid gap-4 lg:grid-cols-[1.6fr_1fr]">
            <section class="border-2 border-ink bg-garlic">
                <div class="flex items-center justify-between border-b-2 border-ink px-5 py-3">
                    <h2 class="label-caps text-ink/45">Next up</h2>
                    <Link href="/admin/events" class="ks-anim label-caps">All events</Link>
                </div>

                <ul v-if="upcoming.length" class="divide-y divide-ink/10">
                    <li v-for="event in upcoming" :key="event.id" class="flex items-center gap-4 px-5 py-3">
                        <div class="min-w-0 flex-1">
                            <Link :href="event.edit_url" class="ks-link font-semibold">{{ event.title }}</Link>
                            <p class="text-xs text-ink/50">
                                {{ event.venue ?? 'No venue' }} &middot; {{ event.suburb }}
                            </p>
                        </div>
                        <span class="label-caps shrink-0 text-ink/60">{{ event.starts }}</span>
                        <span
                            class="label-caps shrink-0 border px-1.5 py-1"
                            :class="
                                event.status === 'published'
                                    ? 'border-ink bg-seafoam text-ink'
                                    : 'border-ink/30 bg-cream-deep text-ink/60'
                            "
                        >
                            {{ event.status }}
                        </span>
                    </li>
                </ul>

                <p v-else class="px-5 py-10 text-center text-ink/55">
                    Nothing scheduled. Sydney is waiting —
                    <Link href="/admin/events/create" class="ks-link">add an event</Link>.
                </p>
            </section>

            <section class="border-2 border-ink bg-garlic p-5">
                <h2 class="label-caps text-ink/45">Needs attention</h2>

                <ul class="mt-3 space-y-3 text-sm">
                    <li class="flex items-baseline justify-between gap-3 border-t border-ink/15 pt-3">
                        <span>Published events already past</span>
                        <span class="font-display text-xl tabular-nums">{{ needsAttention.past_published }}</span>
                    </li>
                    <li class="flex items-baseline justify-between gap-3 border-t border-ink/15 pt-3">
                        <span>Events without a venue</span>
                        <span class="font-display text-xl tabular-nums">{{ needsAttention.events_without_venue }}</span>
                    </li>
                    <li class="flex items-baseline justify-between gap-3 border-t border-ink/15 pt-3">
                        <span>Unpublished pages</span>
                        <span class="font-display text-xl tabular-nums">{{ needsAttention.draft_pages }}</span>
                    </li>
                </ul>
            </section>
        </div>
    </AdminLayout>
</template>
