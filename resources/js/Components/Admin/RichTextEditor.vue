<script setup>
/**
 * The Keep Sydney Live editor.
 *
 * TipTap (ProseMirror) rather than a contenteditable free-for-all: the schema
 * is an allow-list, so writers cannot paste in markup the public templates are
 * not styled for. The server sanitises the same shape again on save.
 */
import { onBeforeUnmount, ref, watch } from 'vue';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';
import { Placeholder } from '@tiptap/extension-placeholder';

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: 'Write something worth leaving the house for…' },
    uploadUrl: { type: String, default: '/admin/media' },
});

const emit = defineEmits(['update:modelValue']);

const fileInput = ref(null);
const uploading = ref(false);
const uploadError = ref(null);

const editor = useEditor({
    content: props.modelValue || '',
    extensions: [
        StarterKit.configure({
            heading: { levels: [2, 3, 4] },
            link: {
                openOnClick: false,
                autolink: true,
                protocols: ['http', 'https', 'mailto', 'tel'],
                HTMLAttributes: { rel: 'noopener noreferrer' },
            },
        }),
        Image.configure({ HTMLAttributes: { loading: 'lazy' } }),
        Placeholder.configure({ placeholder: props.placeholder }),
    ],
    editorProps: {
        attributes: { class: 'ks-prose' },
    },
    onUpdate: ({ editor: instance }) => {
        const html = instance.getHTML();
        emit('update:modelValue', html === '<p></p>' ? '' : html);
    },
});

// Server-side changes (a fresh load, a reset) should reach the editor.
watch(
    () => props.modelValue,
    (value) => {
        if (!editor.value || editor.value.getHTML() === (value || '')) {
            return;
        }

        editor.value.commands.setContent(value || '', { emitUpdate: false });
    },
);

onBeforeUnmount(() => editor.value?.destroy());

const BLOCKS = [
    { label: 'P', title: 'Paragraph', name: 'paragraph', attrs: {}, run: (chain) => chain.setParagraph() },
    { label: 'H2', title: 'Heading 2', name: 'heading', attrs: { level: 2 }, run: (chain) => chain.toggleHeading({ level: 2 }) },
    { label: 'H3', title: 'Heading 3', name: 'heading', attrs: { level: 3 }, run: (chain) => chain.toggleHeading({ level: 3 }) },
    { label: 'H4', title: 'Heading 4', name: 'heading', attrs: { level: 4 }, run: (chain) => chain.toggleHeading({ level: 4 }) },
];

const MARKS = [
    { label: 'B', title: 'Bold', name: 'bold', run: (chain) => chain.toggleBold(), class: 'font-bold' },
    { label: 'I', title: 'Italic', name: 'italic', run: (chain) => chain.toggleItalic(), class: 'italic' },
    { label: 'U', title: 'Underline', name: 'underline', run: (chain) => chain.toggleUnderline(), class: 'underline' },
    { label: 'S', title: 'Strikethrough', name: 'strike', run: (chain) => chain.toggleStrike(), class: 'line-through' },
];

const LISTS = [
    { label: 'List', title: 'Bullet list', name: 'bulletList', run: (chain) => chain.toggleBulletList() },
    { label: '1. List', title: 'Numbered list', name: 'orderedList', run: (chain) => chain.toggleOrderedList() },
    { label: 'Quote', title: 'Blockquote', name: 'blockquote', run: (chain) => chain.toggleBlockquote() },
];

function isActive(name, attributes = {}) {
    return editor.value?.isActive(name, attributes) ?? false;
}

function run(command) {
    if (!editor.value) {
        return;
    }

    command(editor.value.chain().focus()).run();
}

function setLink() {
    const current = editor.value?.getAttributes('link').href ?? '';
    const url = window.prompt('Link URL', current);

    if (url === null) {
        return;
    }

    if (url === '') {
        editor.value?.chain().focus().extendMarkRange('link').unsetLink().run();

        return;
    }

    editor.value?.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
}

function csrfToken() {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

async function uploadImage(event) {
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
        editor.value?.chain().focus().setImage({ src: url }).run();
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
    <div class="ks-editor border border-ink bg-warm-white">
        <div v-if="editor" class="flex flex-wrap items-center gap-1 border-b border-ink/15 bg-cream-deep px-2 py-2">
            <button
                v-for="block in BLOCKS"
                :key="block.label"
                type="button"
                :title="block.title"
                class="ks-tool"
                :class="{ 'ks-tool--on': isActive(block.name, block.attrs) }"
                @click="run(block.run)"
            >
                {{ block.label }}
            </button>

            <span class="mx-1 h-5 w-px bg-ink/20"></span>

            <button
                v-for="mark in MARKS"
                :key="mark.label"
                type="button"
                :title="mark.title"
                class="ks-tool"
                :class="[mark.class, { 'ks-tool--on': isActive(mark.name) }]"
                @click="run(mark.run)"
            >
                {{ mark.label }}
            </button>

            <span class="mx-1 h-5 w-px bg-ink/20"></span>

            <button
                v-for="list in LISTS"
                :key="list.label"
                type="button"
                :title="list.title"
                class="ks-tool"
                :class="{ 'ks-tool--on': isActive(list.name) }"
                @click="run(list.run)"
            >
                {{ list.label }}
            </button>

            <span class="mx-1 h-5 w-px bg-ink/20"></span>

            <button
                type="button"
                title="Link"
                class="ks-tool"
                :class="{ 'ks-tool--on': isActive('link') }"
                @click="setLink"
            >
                Link
            </button>
            <button
                type="button"
                title="Horizontal rule"
                class="ks-tool"
                @click="run((chain) => chain.setHorizontalRule())"
            >
                Rule
            </button>
            <button type="button" title="Insert image" class="ks-tool" :disabled="uploading" @click="fileInput?.click()">
                {{ uploading ? 'Uploading…' : 'Image' }}
            </button>

            <span class="mx-1 h-5 w-px bg-ink/20"></span>

            <button
                type="button"
                title="Clear formatting"
                class="ks-tool"
                @click="run((chain) => chain.unsetAllMarks().clearNodes())"
            >
                Clear
            </button>

            <div class="ml-auto flex items-center gap-1">
                <button type="button" title="Undo" class="ks-tool" @click="run((chain) => chain.undo())">Undo</button>
                <button type="button" title="Redo" class="ks-tool" @click="run((chain) => chain.redo())">Redo</button>
            </div>
        </div>

        <input ref="fileInput" type="file" accept="image/*" class="hidden" @change="uploadImage" />

        <EditorContent :editor="editor" />

        <p v-if="uploadError" class="border-t border-ink/15 px-4 py-2 text-sm text-alert">{{ uploadError }}</p>
    </div>
</template>

<style scoped>
.ks-tool {
    border: 1px solid transparent;
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    line-height: 1;
    color: var(--color-charcoal);
    transition:
        background-color 150ms ease,
        color 150ms ease,
        border-color 150ms ease;
}

.ks-tool:hover:not(:disabled) {
    border-color: var(--color-ink);
    color: var(--color-ink);
}

.ks-tool:disabled {
    opacity: 0.5;
}

.ks-tool--on {
    background: var(--color-ink);
    border-color: var(--color-ink);
    color: var(--color-warm-white);
}
</style>
