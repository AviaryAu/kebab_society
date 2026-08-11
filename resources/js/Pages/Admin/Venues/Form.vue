<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import RichTextEditor from '../../../Components/Admin/RichTextEditor.vue';
import ImageField from '../../../Components/Admin/ImageField.vue';
import ArrowRightIcon from '../../../Components/Icons/ArrowRightIcon.vue';

const props = defineProps({
    venue: { type: Object, default: null },
    options: { type: Object, required: true },
});

const isNew = computed(() => props.venue === null);

const form = useForm({
    name: props.venue?.name ?? '',
    slug: props.venue?.slug ?? '',
    suburb: props.venue?.suburb ?? '',
    address: props.venue?.address ?? '',
    description: props.venue?.description ?? '',
    body: props.venue?.body ?? '',
    image: props.venue?.image ?? '',
    website: props.venue?.website ?? '',
    social_url: props.venue?.social_url ?? '',
    phone: props.venue?.phone ?? '',
    transport: props.venue?.transport ?? '',
    latitude: props.venue?.latitude ?? null,
    longitude: props.venue?.longitude ?? null,
    status: props.venue?.status ?? 'draft',
    featured: props.venue?.featured ?? false,
    meta_title: props.venue?.meta_title ?? '',
    meta_description: props.venue?.meta_description ?? '',
});

const slugTouched = ref(! isNew.value);

function slugify(value) {
    return value
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

watch(
    () => form.name,
    (name) => {
        if (! slugTouched.value) {
            form.slug = slugify(name);
        }
    },
);

function submit() {
    if (isNew.value) {
        form.post('/admin/venues');

        return;
    }

    form.put(`/admin/venues/${props.venue.slug}`, { preserveScroll: true });
}

function destroy() {
    if (! window.confirm(`Delete "${props.venue.name}"? Its events will be left without a venue.`)) {
        return;
    }

    router.delete(`/admin/venues/${props.venue.slug}`);
}
</script>

<template>
    <Head>
        <title>{{ isNew ? 'New venue' : venue.name }} — Admin</title>
        <meta name="robots" content="noindex, nofollow" />
    </Head>

    <AdminLayout :title="isNew ? 'New venue' : venue.name">
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <Link href="/admin/venues" class="ks-anim label-caps inline-flex items-center gap-1.5 text-ink/55">
                <ArrowRightIcon :size="14" :stroke-width="2.4" class="rotate-180" />
                All venues
            </Link>
            <a
                v-if="! isNew"
                :href="venue.public_url"
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
                    <h2 class="label-caps text-ink/45">Identity</h2>

                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                        <label class="block">
                            <span class="label-caps text-ink/55">Name</span>
                            <input v-model="form.name" type="text" class="ks-field" required />
                            <span v-if="form.errors.name" class="ks-error">{{ form.errors.name }}</span>
                        </label>

                        <label class="block">
                            <span class="label-caps text-ink/55">Slug</span>
                            <input
                                v-model="form.slug"
                                type="text"
                                class="ks-field"
                                required
                                @input="slugTouched = true"
                            />
                            <span class="mt-1 block text-xs text-ink/45">/venues/{{ form.slug || '…' }}</span>
                            <span v-if="form.errors.slug" class="ks-error">{{ form.errors.slug }}</span>
                        </label>
                    </div>

                    <label class="mt-4 block">
                        <span class="label-caps text-ink/55">Short description</span>
                        <textarea v-model="form.description" rows="3" class="ks-field" />
                        <span v-if="form.errors.description" class="ks-error">{{ form.errors.description }}</span>
                    </label>
                </section>

                <section class="border-2 border-ink bg-garlic p-5">
                    <h2 class="label-caps text-ink/45">The long version</h2>
                    <p class="mt-1 text-xs text-ink/50">Optional. History, room notes, what the place is actually like.</p>

                    <div class="mt-3">
                        <RichTextEditor v-model="form.body" placeholder="Tell people what this room is like…" />
                        <span v-if="form.errors.body" class="ks-error">{{ form.errors.body }}</span>
                    </div>
                </section>

                <section class="border-2 border-ink bg-garlic p-5">
                    <h2 class="label-caps text-ink/45">Location</h2>

                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                        <label class="block sm:col-span-2">
                            <span class="label-caps text-ink/55">Address</span>
                            <input v-model="form.address" type="text" class="ks-field" />
                            <span v-if="form.errors.address" class="ks-error">{{ form.errors.address }}</span>
                        </label>

                        <label class="block">
                            <span class="label-caps text-ink/55">Suburb</span>
                            <input v-model="form.suburb" type="text" list="ks-venue-suburbs" class="ks-field" required />
                            <datalist id="ks-venue-suburbs">
                                <option v-for="suburb in options.suburbs" :key="suburb" :value="suburb" />
                            </datalist>
                            <span v-if="form.errors.suburb" class="ks-error">{{ form.errors.suburb }}</span>
                        </label>

                        <label class="block">
                            <span class="label-caps text-ink/55">Phone</span>
                            <input v-model="form.phone" type="text" class="ks-field" />
                            <span v-if="form.errors.phone" class="ks-error">{{ form.errors.phone }}</span>
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

                        <label class="block sm:col-span-2">
                            <span class="label-caps text-ink/55">Getting there</span>
                            <textarea v-model="form.transport" rows="2" class="ks-field" />
                            <span v-if="form.errors.transport" class="ks-error">{{ form.errors.transport }}</span>
                        </label>
                    </div>

                    <p v-if="form.latitude === null || form.longitude === null" class="mt-3 text-xs text-alert">
                        Without coordinates this venue will not appear on the map.
                    </p>
                </section>

                <section class="border-2 border-ink bg-garlic p-5">
                    <h2 class="label-caps text-ink/45">Elsewhere & artwork</h2>

                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                        <label class="block">
                            <span class="label-caps text-ink/55">Website</span>
                            <input v-model="form.website" type="url" class="ks-field" placeholder="https://" />
                            <span v-if="form.errors.website" class="ks-error">{{ form.errors.website }}</span>
                        </label>

                        <label class="block">
                            <span class="label-caps text-ink/55">Social</span>
                            <input v-model="form.social_url" type="url" class="ks-field" placeholder="https://" />
                            <span v-if="form.errors.social_url" class="ks-error">{{ form.errors.social_url }}</span>
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
                        <input v-model="form.meta_title" type="text" class="ks-field" :placeholder="form.name" />
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

                    <label class="mt-4 flex items-center gap-2.5">
                        <input v-model="form.featured" type="checkbox" class="size-4 accent-ink" />
                        <span class="text-sm">Feature this venue</span>
                    </label>

                    <p v-if="! isNew" class="mt-4 border-t border-ink/15 pt-3 text-sm text-ink/55">
                        {{ venue.events_count }} event{{ venue.events_count === 1 ? '' : 's' }} listed here.
                    </p>

                    <button
                        type="submit"
                        class="ks-anim mt-5 w-full border-2 border-ink bg-ink px-4 py-3 text-garlic transition-colors hover:bg-garlic hover:text-ink disabled:opacity-50"
                        :disabled="form.processing"
                    >
                        <span class="label-caps">{{ isNew ? 'Create venue' : 'Save venue' }}</span>
                    </button>

                    <button
                        v-if="! isNew"
                        type="button"
                        class="ks-anim mt-2 w-full border-2 border-ink/25 px-4 py-2.5 text-ink/60 transition-colors hover:border-alert hover:text-alert"
                        @click="destroy"
                    >
                        <span class="label-caps">Delete venue</span>
                    </button>
                </section>
            </aside>
        </form>
    </AdminLayout>
</template>
