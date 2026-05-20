<script setup>
import { Head, Link } from '@inertiajs/vue3';

const projects = [
    {
        name: 'Warehouse Visibility Program',
        summary: 'A digitization initiative to connect warehouse devices, stock flow, and operations dashboards.',
    },
    {
        name: 'ERP Acceleration Initiative',
        summary: 'Development and rollout of custom ERP modules for planning, reporting, and process automation.',
    },
    {
        name: 'Cross-System Data Exchange',
        summary: 'Integration layer for secure data exchange between internal systems and partner platforms.',
    },
];
</script>

<template>
    <Head title="EU Projects - Generic Company" />

    <div class="min-h-screen bg-slate-950 px-6 py-12 text-white">
        <div class="mx-auto max-w-5xl">
            <header class="mb-12 flex flex-wrap items-center justify-between gap-4 border-b border-white/10 pb-6">
                <Link href="/" class="inline-flex items-center gap-3">
                    <img src="/img/Generic_company_logo.png" alt="Generic Company" class="h-10 w-auto" />
                </Link>
                <nav class="flex items-center gap-5 text-sm text-white/70">
                    <Link :href="route('marketing.services')" class="transition hover:text-white">Services</Link>
                    <Link :href="route('marketing.about')" class="transition hover:text-white">About</Link>
                    <Link :href="route('marketing.eu-projects')" class="font-semibold text-white">EU Projects</Link>
                </nav>
            </header>

            <section>
                <p class="mb-3 text-xs font-semibold uppercase tracking-[0.3em] text-cyan-300">EU Projects</p>
                <h1 class="mb-6 text-4xl font-bold tracking-tight sm:text-5xl">Current and Planned Initiatives</h1>
                <p class="mb-8 max-w-3xl text-base text-white/75">
                    Generic Company participates in practical projects that improve digital infrastructure, operational efficiency, and cross-system collaboration.
                </p>

                <div class="space-y-4">
                    <article
                        v-for="project in projects"
                        :key="project.name"
                        class="rounded-xl border border-white/10 bg-white/5 p-6"
                    >
                        <h2 class="mb-2 text-lg font-semibold">{{ project.name }}</h2>
                        <p class="text-sm text-white/70">{{ project.summary }}</p>
                    </article>
                </div>
            </section>
        </div>
    </div>
</template>
