<script setup>
/**
 * KEEP SYDNEY LIVE — LOGO SYSTEM
 *
 * One brand, six lockups. SYDNEY is always the hero; KEEP and LIVE sit above
 * and below it as letterspaced supporting words, centred on the hero.
 *
 *   stacked   KEEP / SYDNEY / LIVE   — primary lockup
 *   inline    KEEP SYDNEY LIVE       — one line, tight spaces
 *   wordmark  Keep SYDNEY LIVE       — short brand, used in navigation
 *   stack     KS over rule over LIVE — compact secondary
 *   seal      circular KEEP SYDNEY LIVE · SYDNEY · EST. 2026
 *   monogram  KS                     — favicon, marker, avatar
 */
import KsMark from './KsMark.vue';

defineProps({
    variant: {
        type: String,
        default: 'stacked',
        validator: (value) => ['stacked', 'inline', 'wordmark', 'stack', 'seal', 'monogram'].includes(value),
    },
    /** `square` and `circle` give the monogram its container. */
    shape: {
        type: String,
        default: 'bare',
        validator: (value) => ['bare', 'square', 'circle'].includes(value),
    },
    /** Places the KSL pictogram to the left of the wordmark. It inherits currentColor. */
    mark: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <!-- PRIMARY: the full lockup. -->
    <span v-if="variant === 'stacked'" class="ks-logo ks-logo--stacked">
        <span class="ks-logo__support">KEEP</span>
        <span class="ks-logo__hero">SYDNEY</span>
        <span class="ks-logo__support">LIVE</span>
    </span>

    <!-- INLINE: same hierarchy, one line. -->
    <span v-else-if="variant === 'inline'" class="ks-logo ks-logo--inline">
        <span class="ks-logo__support">KEEP</span>
        <span class="ks-logo__hero">SYDNEY</span>
        <span class="ks-logo__support">LIVE</span>
    </span>

    <!-- SHORT BRAND: navigation and compact placements. -->
    <span v-else-if="variant === 'wordmark'" class="ks-logo ks-logo--wordmark">
        <KsMark v-if="mark" class="ks-logo__mark" />
        <span class="ks-logo__lines">
            <span class="ks-logo__keep">Keep</span><span class="ks-logo__ks">Sydney <span class="ks-logo__live">LIVE</span></span>
        </span>
    </span>

    <!-- COMPACT SECONDARY: KS over a rule over LIVE. -->
    <span v-else-if="variant === 'stack'" class="ks-logo ks-logo--stack">
        <span class="ks-logo__ks">KS</span>
        <span class="ks-logo__support ks-logo__stack-live">LIVE</span>
    </span>

    <!-- SEAL: the editorial stamp. -->
    <span v-else-if="variant === 'seal'" class="ks-logo ks-logo--seal">
        <span class="ks-logo__seal-inner">
            <span class="ks-logo__support">KEEP</span>
            <span class="ks-logo__hero">SYDNEY</span>
            <span class="ks-logo__support">LIVE</span>
            <span class="ks-logo__seal-foot">SYDNEY &middot; EST. 2026</span>
        </span>
    </span>

    <!-- MONOGRAM: smallest usable mark. -->
    <span
        v-else
        class="ks-logo ks-logo--monogram"
        :class="{ 'ks-logo--square': shape === 'square', 'ks-logo--circle': shape === 'circle' }"
    >
        <span class="ks-logo__ks">KS</span>
    </span>
</template>

<style scoped>
/*
 * Everything scales from the element's own font-size, so a lockup is sized by
 * setting `text-[28px]` (or similar) on the element that renders it.
 */
.ks-logo {
    display: flex;
    flex-direction: column;
    color: currentColor;
    gap: 0.2em;
    line-height: 1;
    user-select: none;
    text-transform: uppercase;
}

.ks-logo__hero {
    font-family: var(--font-logo);
    font-size: 1em;
    letter-spacing: 0.005em;
    line-height: 0.82;
}

.ks-logo__support {
    font-family: var(--font-logo-support);
    font-weight: 600;
    font-size: 0.235em;
    line-height: 1;
    /* The trailing letter-space is trimmed so the word optically centres. */
    letter-spacing: 0.34em;
    text-indent: 0.34em;
    text-align: center;
}

/* Supporting words centre on SYDNEY rather than stretching to the container. */
.ks-logo--stacked {
    flex-direction: column;
    align-items: center;
    gap: 0.1em;
}

.ks-logo--inline {
    align-items: baseline;
    gap: 0.16em;
}

/* Wordmark: pictogram, then light Keep, heavy SYDNEY, light LIVE. */
.ks-logo--wordmark {
    flex-direction: row;
    align-items: center;
    gap: 0.34em;
}

.ks-logo__lines {
    display: flex;
    flex-direction: column;
    gap: 0.2em;
}

/* Sized to the wordmark's own height rather than a fixed pixel value. */
.ks-logo__mark {
    height: 1.32em;
}

.ks-logo__ks {
    font-family: var(--font-logo);
    font-size: 1em;
    letter-spacing: -0.005em;
    line-height: 1;
}

.ks-logo__keep {
    font-family: var(--font-logo-support);
    font-weight: 500;
    font-size: 0.5em;
    letter-spacing: 0.2em;
    text-transform: none;
    line-height: 1;
}

.ks-logo__live {
    font-family: var(--font-logo-support);
    font-weight: 500;
    font-size: 0.5em;
    letter-spacing: 0.2em;
    text-transform: none;
    line-height: 1;
}

.ks-logo--stack {
    flex-direction: column;
    align-items: stretch;
}

.ks-logo__stack-live {
    margin-top: 0.1em;
    padding-top: 0.1em;
    border-top: 0.06em solid currentColor;
    font-size: 0.28em;
}

/* SEAL */
.ks-logo--seal {
    aspect-ratio: 1;
    align-items: center;
    justify-content: center;
    border: 0.05em solid currentColor;
    border-radius: 9999px;
    padding: 0.22em;
}

.ks-logo__seal-inner {
    display: flex;
    width: 100%;
    flex-direction: column;
    align-items: stretch;
    gap: 0.06em;
}

.ks-logo--seal .ks-logo__hero {
    font-size: 0.5em;
    text-align: center;
}

.ks-logo--seal .ks-logo__support {
    font-size: 0.12em;
}

.ks-logo__seal-foot {
    margin-top: 0.06em;
    font-family: var(--font-logo-support);
    font-weight: 500;
    font-size: 0.085em;
    letter-spacing: 0.22em;
    text-align: center;
}

/* MONOGRAM */
.ks-logo--monogram {
    align-items: center;
    justify-content: center;
}

.ks-logo--square,
.ks-logo--circle {
    aspect-ratio: 1;
    width: 1.62em;
    background: var(--color-ink);
    color: var(--color-warm-white);
}

.ks-logo--square {
    border-radius: 0.22em;
}

.ks-logo--circle {
    border-radius: 9999px;
}

.ks-logo--square .ks-logo__ks,
.ks-logo--circle .ks-logo__ks {
    font-size: 0.82em;
}
</style>
