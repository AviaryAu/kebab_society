<script setup>
import { ref } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    label: { type: String, default: 'Image' },
    uploadUrl: { type: String, default: '/admin/media' },
    error: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);

const fileInput = ref(null);
const uploading = ref(false);
const uploadError = ref(null);

function csrfToken() {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

async function upload(event) {
    const file = event.target.files?.[0];

    if (!file) {
        return;
    }

    uploading.value = true;
    uploadError.value = null;

    try {
        const body = new FormData();
        body.append('image', file);

        const response = await fetch(props.uploadUrl, {
            method: 'POST',
            body,
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-XSRF-TOKEN': csrfToken() },
        });

        if (!response.ok) {
            throw new Error('Upload failed');
        }

        const { url } = await response.json();
        emit('update:modelValue', url);
    } catch {
        uploadError.value = 'That image could not be uploaded.';
    } finally {
        uploading.value = false;

        if (fileInput.value) {
            fileInput.value.value = '';
        }
    }
}
</script>

<template>
    <div>
        <span class="label-caps text-ink/55">{{ label }}</span>

        <div class="mt-2 flex gap-4">
            <div class="ks-media aspect-[4/3] w-40 shrink-0 border border-ink/20">
                <img v-if="modelValue" :src="modelValue" alt="" />
                <div v-else class="flex h-full items-center justify-center bg-cream-deep text-xs text-ink/40">
                    No image
                </div>
            </div>

            <div class="min-w-0 flex-1">
                <input
                    :value="modelValue"
                    type="text"
                    class="ks-field !mt-0"
                    placeholder="https:// or /media/…"
                    @input="emit('update:modelValue', $event.target.value)"
                />

                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        class="ks-anim border border-ink px-3 py-1.5 text-xs uppercase tracking-wide transition-colors hover:bg-ink hover:text-warm-white disabled:opacity-50"
                        :disabled="uploading"
                        @click="fileInput?.click()"
                    >
                        {{ uploading ? 'Uploading…' : 'Upload' }}
                    </button>
                    <button
                        v-if="modelValue"
                        type="button"
                        class="ks-anim border border-ink/30 px-3 py-1.5 text-xs uppercase tracking-wide text-ink/60 transition-colors hover:border-alert hover:text-alert"
                        @click="emit('update:modelValue', '')"
                    >
                        Remove
                    </button>
                </div>

                <span v-if="uploadError" class="ks-error">{{ uploadError }}</span>
                <span v-if="error" class="ks-error">{{ error }}</span>
            </div>
        </div>

        <input ref="fileInput" type="file" accept="image/*" class="hidden" @change="upload" />
    </div>
</template>
