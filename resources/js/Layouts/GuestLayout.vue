<script setup>
import { Link } from '@inertiajs/vue3';
import EdisLogo from '@/Components/EdisLogo.vue';
</script>

<template>
    <div class="flex min-h-screen flex-col items-center bg-gradient-to-br from-slate-900 to-slate-700 pt-6 sm:justify-center sm:pt-0">
        <div class="mb-2">
            <Link href="/" class="inline-flex items-center">
                <EdisLogo variant="white" height="40" />
            </Link>
        </div>

        <div class="mt-4 w-full overflow-hidden rounded-xl bg-white px-8 py-6 shadow-xl sm:max-w-md">
            <slot />
        </div>
    </div>
</template>
