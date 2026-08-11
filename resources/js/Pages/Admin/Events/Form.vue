<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import RichTextEditor from '../../../Components/Admin/RichTextEditor.vue';
import ImageField from '../../../Components/Admin/ImageField.vue';
import ArrowRightIcon from '../../../Components/Icons/ArrowRightIcon.vue';

const props = defineProps({
    event: { type: Object, default: null },
    options: { type: Object, required: true },
});

const isNew = computed(() => props.event === null);

const form = useForm({
    title: props.event?.title ?? '',
    slug: props.event?.slug ?? '',
    description: props.event?.description ?? '',
    body: props.event?.body ?? '',
    start_datetime: props.event?.start_datetime ?? '',
    end_datetime: props.event?.end_datetime ?? '',
    venue_id: props.event?.venue_id ?? null,
    suburb: props.event?.suburb ?? '',
    category_slug: props.event?.category_slug ?? props.options.categories[0]?.slug ?? 'music',
    image: props.event?.image ?? '',
    price: props.event?.price ?? '',
    ticket_url: props.event?.ticket_url ?? '',
    latitude: props.event?.latitude ?? null,
    longitude: props.event?.longitude ?? null,
    featured: props.event?.featured ?? false,
    status: props.event?.status ?? 'draft',
    meta_title: props.event?.meta_title ?? '',
    meta_description: props.event?.meta_description ?? '',
});

const slugTouched = ref(! isNew.value);

function slugify(value) {
    return value
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

watch(
    () => form.title,
    (title) => {
        if (! slugTouched.value) {
            form.slug = slugify(title);
        }
    },
);

// A venue knows where it is; an event at that venue usually agrees.
watch(
    () => form.venue_id,
    (id) => {
        const venue = props.options.venues.find((item) => item.id === id);

        if (! venue) {
            return;
        }

        if (! form.suburb) {
            form.suburb = venue.suburb;
        }

        if (form.latitude === null || form.latitude === '') {
            form.latitude = venue.latitude;
            form.longitude = venue.longitude;
        }
    },
);

function submit() {
    if (isNew.value) {
        form.post('/admin/events');

        return;
    }

    form.put(`/admin/events/${props.event.slug}`, { preserveScroll: true });
}

function destroy() {
    if (! window.confirm(`Delete "${props.event.title}"? This cannot be undone.`)) {
        return;
    }

    router.delete(`/admin/events/${props.event.slug}`);
}
</script>

<template>
    <Head>
        <title>{{ isNew ? 'New event' : event.title }} — Admin</title>
        <meta name="robots" content="noindex, nofollow" />
    </Head>

    <AdminLayout :title="isNew ? 'New event' : event.title">
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <Link href="/admin/events" class="ks-anim label-caps inline-flex items-center gap-1.5 text-ink/55">
                <ArrowRightIcon :size="14" :stroke-width="2.4" class="rotate-180" />
                All events
            </Link>
            <a
                v-if="! isNew"
                :href="event.public_url"
                target="_blank"
                rel="noopener"
                class="ks-anim label-caps ml-auto inline-flex items-center gap-1.5"
            >
                View public page
                <ArrowRightIcon :size="14" :stroke-width="2.4" />
            </a>
        </div>

        <form class="grid gap-5 xl:grid-cols-[1fr_340px]" @submit.prevent="submit">
            <div class="space-y-5">
                <section class="border-2 border-ink bg-garlic p-5">
                    <h2 class="label-caps text-ink/45">The billing</h2>

                    <label class="mt-3 block">
                        <span class="label-caps text-ink/55">Title</span>
                        <input v-model="form.title" type="text" class="ks-field" required />
                        <span v-if="form.errors.title" class="ks-error">{{ form.errors.title }}</span>
                    </label>

                    <label class="mt-4 block">
                        <span class="label-caps text-ink/55">Slug</span>
                        <input v-model="form.slug" type="text" class="ks-field" required @input="slugTouched = true" />
                        <span class="mt-1 block text-xs text-ink/45">/events/{{ form.slug || '…' }}</span>
                        <span v-if="form.errors.slug" class="ks-error">{{ form.errors.slug }}</span>
                    </label>

                    <label class="mt-4 block">
                        <span class="label-caps text-ink/55">Short description</span>
                        <textarea v-model="form.description" rows="3" class="ks-field" />
                        <span class="mt-1 block text-xs text-ink/45">
                            One or two lines. Used on cards, listings and search results.
                        </span>
                        <span v-if="form.errors.description" class="ks-error">{{ form.errors.description }}</span>
                    </label>
                </section>

                <section class="border-2 border-ink bg-garlic p-5">
                    <h2 class="label-caps text-ink/45">Full write-up</h2>
                    <p class="mt-1 text-xs text-ink/50">Optional. Shown on the event page below the details.</p>

                    <div class="mt-3">
                        <RichTextEditor v-model="form.body" placeholder="Who is playing, what to expect, how to get in…" />
                        <span v-if="form.errors.body" class="ks-error">{{ form.errors.body }}</span>
                    </div>
                </section>

                <section class="border-2 border-ink bg-garlic p-5">
                    <h2 class="label-caps text-ink/45">When</h2>

                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                        <label class="block">
                            <span class="label-caps text-ink/55">Starts</span>
                            <input v-model="form.start_datetime" type="datetime-local" class="ks-field" required />
                            <span v-if="form.errors.start_datetime" class="ks-error">
                                {{ form.errors.start_datetime }}
                            </span>
                        </label>

                        <label class="block">
                            <span class="label-caps text-ink/55">Ends</span>
                            <input v-model="form.end_datetime" type="datetime-local" class="ks-field" />
                            <span v-if="form.errors.end_datetime" class="ks-error">{{ form.errors.end_datetime }}</span>
                        </label>
                    </div>
                </section>

                <section class="border-2 border-ink bg-garlic p-5">
                    <h2 class="label-caps text-ink/45">Where</h2>

                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                        <label class="block">
                            <span class="label-caps text-ink/55">Venue</span>
                            <select v-model.number="form.venue_id" class="ks-field">
                                <option :value="null">No venue</option>
                                <option v-for="venue in options.venues" :key="venue.id" :value="venue.id">
                                    {{ venue.name }} — {{ venue.suburb }}
                                </option>
                            </select>
                            <span v-if="form.errors.venue_id" class="ks-error">{{ form.errors.venue_id }}</span>
                        </label>

                        <label class="block">
                            <span class="label-caps text-ink/55">Suburb</span>
                            <input v-model="form.suburb" type="text" list="ks-suburbs" class="ks-field" required />
                            <datalist id="ks-suburbs">
                                <option v-for="suburb in options.suburbs" :key="suburb" :value="suburb" />
                            </datalist>
                            <span v-if="form.errors.suburb" class="ks-error">{{ form.errors.suburb }}</span>
                        </label>

                        <label class="block">
                            <span class="label-caps text-ink/55">Latitude</span>
                            <input v-model.number="form.latitude" type="number" step="0.0000001" class="ks-field" />
                            <span v-if="form.errors.latitude" class="ks-error">{{ form.errors.latitude }}</span>
                        </label>

                        <label class="block">
                            <span class="label-caps text-ink/55">Longitude</span>
                            <input v-model.number="form.longitude" type="number" step="0.0000001" class="ks-field" />
                            <span v-if="form.errors.longitude" class="ks-error">{{ form.errors.longitude }}</span>
                        </label>
                    </div>
                </section>

                <section class="border-2 border-ink bg-garlic p-5">
                    <h2 class="label-caps text-ink/45">Tickets & artwork</h2>

                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                        <label class="block">
                            <span class="label-caps text-ink/55">Price</span>
                            <input v-model="form.price" type="text" class="ks-field" placeholder="$38 or Free" />
                            <span v-if="form.errors.price" class="ks-error">{{ form.errors.price }}</span>
                        </label>

                        <label class="block">
                            <span class="label-caps text-ink/55">Ticket URL</span>
                            <input v-model="form.ticket_url" type="url" class="ks-field" placeholder="https://" />
                            <span v-if="form.errors.ticket_url" class="ks-error">{{ form.errors.ticket_url }}</span>
                        </label>
                    </div>

                    <div class="mt-4">
                        <ImageField v-model="form.image" label="Hero image" :error="form.errors.image" />
                    </div>
                </section>

                <section class="border-2 border-ink bg-garlic p-5">
                    <h2 class="label-caps text-ink/45">Search</h2>

                    <label class="mt-3 block">
                        <span class="label-caps text-ink/55">Meta title</span>
                        <input v-model="form.meta_title" type="text" class="ks-field" :placeholder="form.title" />
                        <span v-if="form.errors.meta_title" class="ks-error">{{ form.errors.meta_title }}</span>
                    </label>

                    <label class="mt-4 block">
                        <span class="label-caps text-ink/55">Meta description</span>
                        <textarea v-model="form.meta_description" rows="2" class="ks-field" />
                        <span v-if="form.errors.meta_description" class="ks-error">
                            {{ form.errors.meta_description }}
                        </span>
                    </label>
                </section>
            </div>

            <aside class="space-y-4 xl:sticky xl:top-5 xl:self-start">
                <section class="border-2 border-ink bg-garlic p-5">
                    <h2 class="label-caps text-ink/45">Publishing</h2>

                    <label class="mt-3 block">
                        <span class="label-caps text-ink/55">Status</span>
                        <select v-model="form.status" class="ks-field">
                            <option v-for="status in options.statuses" :key="status" :value="status">
                                {{ status }}
                            </option>
                        </select>
                    </label>

                    <label class="mt-4 block">
                        <span class="label-caps text-ink/55">Category</span>
                        <select v-model="form.category_slug" class="ks-field">
                            <option v-for="category in options.categories" :key="category.slug" :value="category.slug">
                                {{ category.name }}
                            </option>
                        </select>
                        <span v-if="form.errors.category_slug" class="ks-error">{{ form.errors.category_slug }}</span>
                    </label>

                    <label class="mt-4 flex items-center gap-2.5">
                        <input v-model="form.featured" type="checkbox" class="size-4 accent-ink" />
                        <span class="text-sm">Feature on the homepage</span>
                    </label>

                    <button
                        type="submit"
                        class="ks-anim mt-5 w-full border-2 border-ink bg-ink px-4 py-3 text-garlic transition-colors hover:bg-garlic hover:text-ink disabled:opacity-50"
                        :disabled="form.processing"
                    >
                        <span class="label-caps">{{ isNew ? 'Create event' : 'Save event' }}</span>
                    </button>

                    <button
                        v-if="! isNew"
                        type="button"
                        class="ks-anim mt-2 w-full border-2 border-ink/25 px-4 py-2.5 text-ink/60 transition-colors hover:border-alert hover:text-alert"
                        @click="destroy"
                    >
                        <span class="label-caps">Delete event</span>
                    </button>
                </section>
            </aside>
        </form>
    </AdminLayout>
</template>
