<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    href: {
        type: String,
        required: true,
    },
    active: {
        type: Boolean,
    },
});

const classes = computed(() =>
    props.active
        ? 'inline-flex items-center px-3 py-1.5 rounded-md text-sm font-semibold text-white bg-white/20 border border-white/30 transition duration-150 ease-in-out focus:outline-none'
        : 'inline-flex items-center px-3 py-1.5 rounded-md text-sm font-medium text-white/80 transition duration-150 ease-in-out hover:text-white hover:bg-white/10 focus:outline-none focus:text-white',
);
</script>

<template>
    <Link :href="href" :class="classes">
        <slot />
    </Link>
</template>
