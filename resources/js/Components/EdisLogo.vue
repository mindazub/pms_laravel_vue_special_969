<script setup>
defineProps({
    variant: {
        type: String,
        default: 'color', // 'color' | 'white'
    },
    height: {
        type: String,
        default: '36',
    },
});
</script>

<template>
    <img
        v-if="variant === 'white'"
        :src="'/brand/logo-white.svg'"
        :height="height"
        :style="{ height: height + 'px', width: 'auto' }"
        alt="EDIS Lab"
        class="block"
    />
    <img
        v-else
        :src="'/brand/logo-color.svg'"
        :height="height"
        :style="{ height: height + 'px', width: 'auto' }"
        alt="EDIS Lab"
        class="block"
    />
</template>
