<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="flex min-h-screen flex-col items-center bg-gradient-to-br from-brand-900 to-brand-700 pt-6 sm:justify-center sm:pt-0">
        <div class="mb-2">
            <Link href="/">
                <img src="/brand/logo-white.svg" alt="EDIS Lab" class="h-12" />
            </Link>
        </div>

        <div class="mt-4 w-full overflow-hidden rounded-xl bg-white px-8 py-6 shadow-xl sm:max-w-md">
            <slot />
        </div>
    </div>
</template>
