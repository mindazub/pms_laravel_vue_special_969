<script setup>
const props = defineProps({
    selectedCell: {
        type: Object,
        default: () => ({
            background: '#ffffff',
            fontWeight: '400',
            textAlign: 'left',
        }),
    },
    sheetName: {
        type: String,
        default: 'Sheet 1',
    },
    isDirty: {
        type: Boolean,
        default: false,
    },
    isSaving: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits([
    'save',
    'add-row',
    'add-column',
    'add-sheet',
    'remove-sheet',
    'rename-sheet',
    'clear-cell',
    'apply-style',
]);

const highlightColors = ['#ffffff', '#fef3c7', '#bfdbfe', '#bbf7d0', '#fecaca', '#e9d5ff'];
const alignments = [
    { label: 'Left', value: 'left' },
    { label: 'Center', value: 'center' },
    { label: 'Right', value: 'right' },
];
</script>

<template>
    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-lg font-semibold text-slate-900">Sheet controls</h3>
                <p class="mt-1 text-sm text-slate-500">Add rows, columns, sheets, and basic formatting like a light spreadsheet.</p>
            </div>

            <button
                type="button"
                class="rounded-md px-3 py-2 text-xs font-semibold uppercase tracking-wide transition"
                :class="isDirty ? 'border border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100' : 'border border-slate-200 bg-slate-50 text-slate-500'"
                :disabled="isSaving"
                @click="emit('save')"
            >
                {{ isSaving ? 'Saving...' : isDirty ? 'Save board' : 'Saved' }}
            </button>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-2">
            <button type="button" class="rounded-md border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="emit('add-row')">Add row</button>
            <button type="button" class="rounded-md border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="emit('add-column')">Add column</button>
            <button type="button" class="rounded-md border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="emit('add-sheet')">New sheet</button>
            <button type="button" class="rounded-md border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-600 hover:bg-rose-50" @click="emit('remove-sheet')">Remove sheet</button>
            <button type="button" class="rounded-md border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="emit('clear-cell')">Clear cell</button>
        </div>

        <div class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,220px)_1fr]">
            <label class="block text-sm font-medium text-slate-700">
                Active sheet name
                <input
                    type="text"
                    :value="sheetName"
                    class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    @input="emit('rename-sheet', $event.target.value)"
                >
            </label>

            <div class="space-y-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Highlight</p>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <button
                            v-for="color in highlightColors"
                            :key="color"
                            type="button"
                            class="h-7 w-7 rounded-full border-2 transition"
                            :class="selectedCell.background === color ? 'border-slate-900 scale-105' : 'border-slate-200'"
                            :style="{ backgroundColor: color }"
                            @click="emit('apply-style', { background: color })"
                        />
                    </div>
                </div>

                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Text tools</p>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            class="rounded-md border px-3 py-1.5 text-xs font-semibold transition"
                            :class="selectedCell.fontWeight === '700' ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 text-slate-700 hover:bg-slate-50'"
                            @click="emit('apply-style', { fontWeight: selectedCell.fontWeight === '700' ? '400' : '700' })"
                        >
                            Bold
                        </button>

                        <button
                            v-for="alignment in alignments"
                            :key="alignment.value"
                            type="button"
                            class="rounded-md border px-3 py-1.5 text-xs font-semibold transition"
                            :class="selectedCell.textAlign === alignment.value ? 'border-indigo-300 bg-indigo-50 text-indigo-700' : 'border-slate-200 text-slate-700 hover:bg-slate-50'"
                            @click="emit('apply-style', { textAlign: alignment.value })"
                        >
                            {{ alignment.label }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
