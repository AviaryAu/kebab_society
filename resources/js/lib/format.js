/**
 * Presentation helpers.
 *
 * Formatting only — no scoring, ranking or business logic lives on the client.
 */

export function formatDistance(km) {
    if (!Number.isFinite(km)) {
        return null;
    }

    return km < 1 ? `${Math.round(km * 1000)}m away` : `${km.toFixed(1)}km away`;
}

export function formatPriceLevel(level) {
    if (!Number.isFinite(level) || level < 1) {
        return null;
    }

    return '$'.repeat(Math.min(4, level));
}

export function formatCount(value) {
    if (!Number.isFinite(value)) {
        return '0';
    }

    return new Intl.NumberFormat('en-AU').format(value);
}

export function pluralise(count, singular, plural = `${singular}s`) {
    return count === 1 ? singular : plural;
}
