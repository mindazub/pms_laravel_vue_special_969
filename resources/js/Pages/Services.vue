<script setup>
import { Head, Link } from '@inertiajs/vue3';

const services = [
    {
        title: 'Web Development',
        description: 'Modern websites and web applications designed for speed, usability, and long-term maintainability.',
    },
    {
        title: 'Laravel Development',
        description: 'Custom Laravel applications, admin portals, API development, and feature expansion for business workflows.',
    },
    {
        title: 'Warehouse System Integrations',
        description: 'Reliable integrations between warehouse operations, scanners, shipping tools, and inventory data pipelines.',
    },
    {
        title: 'ERP System Development',
        description: 'ERP modules and end-to-end process automation for finance, operations, inventory, and reporting.',
    },
];
</script>

<template>
    <Head title="Services - Generic Company" />

    <div class="min-h-screen bg-slate-950 px-6 py-12 text-white">
        <div class="mx-auto max-w-5xl">
            <header class="mb-12 flex flex-wrap items-center justify-between gap-4 border-b border-white/10 pb-6">
                <Link href="/" class="inline-flex items-center gap-3">
                    <img src="/img/Generic_company_logo.png" alt="Generic Company" class="h-10 w-auto" />
                </Link>
                <nav class="flex items-center gap-5 text-sm text-white/70">
                    <Link :href="route('marketing.services')" class="font-semibold text-white">Services</Link>
                    <Link :href="route('marketing.about')" class="transition hover:text-white">About</Link>
                    <Link :href="route('marketing.eu-projects')" class="transition hover:text-white">EU Projects</Link>
                </nav>
            </header>

            <section>
                <p class="mb-3 text-xs font-semibold uppercase tracking-[0.3em] text-cyan-300">Services</p>
                <h1 class="mb-8 text-4xl font-bold tracking-tight sm:text-5xl">What Generic Company Builds</h1>
                <div class="grid gap-5 sm:grid-cols-2">
                    <article
                        v-for="service in services"
                        :key="service.title"
                        class="rounded-xl border border-white/10 bg-white/5 p-6"
                    >
                        <h2 class="mb-2 text-xl font-semibold">{{ service.title }}</h2>
                        <p class="text-sm text-white/70">{{ service.description }}</p>
                    </article>
                </div>
            </section>
        </div>
    </div>
</template>
