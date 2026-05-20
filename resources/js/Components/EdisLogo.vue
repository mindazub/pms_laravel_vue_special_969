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
        :src="'/img/Generic_company_logo.png'"
        :height="height"
        :style="{ height: height + 'px', width: 'auto' }"
        alt="Company"
        class="block"
    />
    <img
        v-else
        :src="'/img/Generic_company_logo.png'"
        :height="height"
        :style="{ height: height + 'px', width: 'auto' }"
        alt="Company"
        class="block"
    />
</template>
