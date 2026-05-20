<script setup>
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="About - Generic Company" />

    <div class="min-h-screen bg-slate-950 px-6 py-12 text-white">
        <div class="mx-auto max-w-4xl">
            <header class="mb-12 flex flex-wrap items-center justify-between gap-4 border-b border-white/10 pb-6">
                <Link href="/" class="inline-flex items-center gap-3">
                    <img src="/img/Generic_company_logo.png" alt="Generic Company" class="h-10 w-auto" />
                </Link>
                <nav class="flex items-center gap-5 text-sm text-white/70">
                    <Link :href="route('marketing.services')" class="transition hover:text-white">Services</Link>
                    <Link :href="route('marketing.about')" class="font-semibold text-white">About</Link>
                    <Link :href="route('marketing.eu-projects')" class="transition hover:text-white">EU Projects</Link>
                </nav>
            </header>

            <section class="rounded-2xl border border-white/10 bg-white/5 p-8 sm:p-10">
                <p class="mb-3 text-xs font-semibold uppercase tracking-[0.3em] text-cyan-300">About</p>
                <h1 class="mb-5 text-4xl font-bold tracking-tight sm:text-5xl">Generic Company</h1>
                <p class="mb-4 text-base leading-relaxed text-white/80">
                    Generic Company delivers practical digital solutions for teams that need dependable software and smooth business operations.
                </p>
                <p class="text-base leading-relaxed text-white/70">
                    Our focus includes web development, Laravel engineering, warehouse system integrations, and ERP system development that scales with your organization.
                </p>
                <p class="text-base leading-relaxed text-white/70">
                    Address: Vasiliausko g. 55 - 604, Kaunas
                </p>
            </section>
        </div>
    </div>
</template>
