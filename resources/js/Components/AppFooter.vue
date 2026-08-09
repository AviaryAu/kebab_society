<script setup>
import { Link } from '@inertiajs/vue3';
import SocietyLogo from './SocietyLogo.vue';

const year = new Date().getFullYear();
</script>

<template>
    <footer class="border-t-2 border-ink bg-char text-garlic">
        <div class="mx-auto grid max-w-[1700px] gap-8 px-4 py-10 sm:px-6 md:grid-cols-[auto_1fr_auto] md:items-start">
            <div class="h-24">
                <SocietyLogo variant="seal" />
            </div>

            <div class="max-w-xl">
                <h2 class="text-lg text-garlic">The Society</h2>
                <p class="mt-2 text-sm leading-relaxed text-garlic/70">
                    Kebab Society is an independent record of Sydney's kebabs. We rank them, we argue about them, and we
                    publish the results. The Kebab Society Score is ours alone and is not a Google rating.
                </p>
                <p class="mt-3 text-xs leading-relaxed text-garlic/45">
                    Every shop currently listed is sample data used while the Society builds its register. Nothing here
                    describes a real business yet.
                </p>
            </div>

            <nav class="flex flex-col gap-2" aria-label="Footer">
                <Link href="/" class="ks-link-underline label-caps w-fit text-garlic/80">The Map</Link>
                <Link href="/leaderboard" class="ks-link-underline label-caps w-fit text-garlic/80">Leaderboard</Link>
            </nav>
        </div>

        <div class="border-t border-garlic/15 px-4 py-4 sm:px-6">
            <p class="mx-auto max-w-[1700px] text-[11px] tracking-wide text-garlic/45">
                © {{ year }} Kebab Society · Sydney, Australia · Map data © OpenStreetMap contributors, tiles © CARTO
            </p>
        </div>
    </footer>
</template>
