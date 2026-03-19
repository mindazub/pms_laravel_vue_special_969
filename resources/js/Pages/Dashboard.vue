<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-bold text-brand-900">
                Dashboard
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- Welcome card -->
                <div class="overflow-hidden rounded-xl border border-brand-100 bg-white shadow-card-md">
                    <!-- Top accent bar -->
                    <div class="h-1.5 w-full bg-gradient-to-r from-brand-900 to-brand-500"></div>

                    <div class="flex flex-col items-start gap-6 p-8 sm:flex-row sm:items-center">
                        <!-- Logo mark -->
                        <div class="flex h-16 w-16 flex-shrink-0 items-center justify-center rounded-xl bg-brand-900">
                            <img src="/brand/favicon.svg" alt="EDIS Lab" class="h-10 w-10" />
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold text-brand-900">
                                Welcome to EDIS Lab Portal
                            </h1>
                            <p class="mt-1 text-slate-500">
                                Track your projects and tasks. Use the Projects section to manage your work.
                            </p>
                            <div class="mt-4 flex flex-wrap gap-3">
                                <Link
                                    :href="route('projects.index')"
                                    class="btn-primary"
                                >
                                    <svg class="me-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                    </svg>
                                    Go to Projects
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
