<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import RichTextEditor from '../../../Components/Admin/RichTextEditor.vue';
import ImageField from '../../../Components/Admin/ImageField.vue';
import ArrowRightIcon from '../../../Components/Icons/ArrowRightIcon.vue';

const props = defineProps({
    page: { type: Object, default: null },
    options: { type: Object, required: true },
});

const isNew = computed(() => props.page === null);

const form = useForm({
    title: props.page?.title ?? '',
    slug: props.page?.slug ?? '',
    type: props.page?.type ?? 'guide',
    excerpt: props.page?.excerpt ?? '',
    body: props.page?.body ?? '',
    image: props.page?.image ?? '',
    status: props.page?.status ?? 'draft',
    published_at: props.page?.published_at ?? '',
    featured: props.page?.featured ?? false,
    sort_order: props.page?.sort_order ?? 0,
    meta_title: props.page?.meta_title ?? '',
    meta_description: props.page?.meta_description ?? '',
});

const slugTouched = ref(! isNew.value);

const publicPath = computed(() => (form.type === 'guide' ? `/guides/${form.slug || '…'}` : `/${form.slug || '…'}`));

const slugIsReserved = computed(
    () => form.type === 'page' && props.options.reserved_slugs.includes(form.slug),
);

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

function submit() {
    if (isNew.value) {
        form.post('/admin/pages');

        return;
    }

    form.put(`/admin/pages/${props.page.slug}`, { preserveScroll: true });
}

function destroy() {
    if (! window.confirm(`Delete "${props.page.title}"? This cannot be undone.`)) {
        return;
    }

    router.delete(`/admin/pages/${props.page.slug}`);
}
</script>

<template>
    <Head>
        <title>{{ isNew ? 'New page' : page.title }} — Admin</title>
        <meta name="robots" content="noindex, nofollow" />
    </Head>

    <AdminLayout :title="isNew ? 'New page' : page.title">
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <Link href="/admin/pages" class="ks-anim label-caps inline-flex items-center gap-1.5 text-ink/55">
                <ArrowRightIcon :size="14" :stroke-width="2.4" class="rotate-180" />
                All pages
            </Link>
            <a
                v-if="! isNew"
                :href="page.public_url"
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
                    <h2 class="label-caps text-ink/45">Headline</h2>

                    <label class="mt-3 block">
                        <span class="label-caps text-ink/55">Title</span>
                        <input v-model="form.title" type="text" class="ks-field" required />
                        <span v-if="form.errors.title" class="ks-error">{{ form.errors.title }}</span>
                    </label>

                    <label class="mt-4 block">
                        <span class="label-caps text-ink/55">Slug</span>
                        <input v-model="form.slug" type="text" class="ks-field" required @input="slugTouched = true" />
                        <span class="mt-1 block text-xs text-ink/45">{{ publicPath }}</span>
                        <span v-if="slugIsReserved" class="ks-error">
                            That slug belongs to a Keep Sydney Live route. Pick another.
                        </span>
                        <span v-if="form.errors.slug" class="ks-error">{{ form.errors.slug }}</span>
                    </label>

                    <label class="mt-4 block">
                        <span class="label-caps text-ink/55">Excerpt</span>
                        <textarea v-model="form.excerpt" rows="3" class="ks-field" />
                        <span class="mt-1 block text-xs text-ink/45">Shown on the guides index and in search results.</span>
                        <span v-if="form.errors.excerpt" class="ks-error">{{ form.errors.excerpt }}</span>
                    </label>
                </section>

                <section class="border-2 border-ink bg-garlic p-5">
                    <h2 class="label-caps text-ink/45">The piece</h2>

                    <div class="mt-3">
                        <RichTextEditor v-model="form.body" placeholder="Write the guide…" />
                        <span v-if="form.errors.body" class="ks-error">{{ form.errors.body }}</span>
                    </div>
                </section>

                <section class="border-2 border-ink bg-garlic p-5">
                    <h2 class="label-caps text-ink/45">Artwork</h2>

                    <div class="mt-3">
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
                        <span class="label-caps text-ink/55">Type</span>
                        <select v-model="form.type" class="ks-field">
                            <option v-for="type in options.types" :key="type" :value="type">{{ type }}</option>
                        </select>
                        <span class="mt-1 block text-xs text-ink/45">
                            Guides appear in The Guide. Pages sit at the top level.
                        </span>
                    </label>

                    <label class="mt-4 block">
                        <span class="label-caps text-ink/55">Status</span>
                        <select v-model="form.status" class="ks-field">
                            <option v-for="status in options.statuses" :key="status" :value="status">
                                {{ status }}
                            </option>
                        </select>
                    </label>

                    <label class="mt-4 block">
                        <span class="label-caps text-ink/55">Publish date</span>
                        <input v-model="form.published_at" type="datetime-local" class="ks-field" />
                        <span class="mt-1 block text-xs text-ink/45">Leave empty to publish immediately.</span>
                        <span v-if="form.errors.published_at" class="ks-error">{{ form.errors.published_at }}</span>
                    </label>

                    <label class="mt-4 block">
                        <span class="label-caps text-ink/55">Order</span>
                        <input v-model.number="form.sort_order" type="number" min="0" class="ks-field" />
                        <span v-if="form.errors.sort_order" class="ks-error">{{ form.errors.sort_order }}</span>
                    </label>

                    <label class="mt-4 flex items-center gap-2.5">
                        <input v-model="form.featured" type="checkbox" class="size-4 accent-ink" />
                        <span class="text-sm">Feature this piece</span>
                    </label>

                    <button
                        type="submit"
                        class="ks-anim mt-5 w-full border-2 border-ink bg-ink px-4 py-3 text-garlic transition-colors hover:bg-garlic hover:text-ink disabled:opacity-50"
                        :disabled="form.processing"
                    >
                        <span class="label-caps">{{ isNew ? 'Create page' : 'Save page' }}</span>
                    </button>

                    <button
                        v-if="! isNew"
                        type="button"
                        class="ks-anim mt-2 w-full border-2 border-ink/25 px-4 py-2.5 text-ink/60 transition-colors hover:border-alert hover:text-alert"
                        @click="destroy"
                    >
                        <span class="label-caps">Delete page</span>
                    </button>
                </section>
            </aside>
        </form>
    </AdminLayout>
</template>
