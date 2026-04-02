<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    currentCellLabel: {
        type: String,
        default: 'A1',
    },
    currentCellValue: {
        type: [String, Number],
        default: '',
    },
    sheetName: {
        type: String,
        default: 'Sheet 1',
    },
    sheetCount: {
        type: Number,
        default: 1,
    },
    isSaving: {
        type: Boolean,
        default: false,
    },
    isDirty: {
        type: Boolean,
        default: false,
    },
    isSheetMaximized: {
        type: Boolean,
        default: false,
    },
    selectedCell: {
        type: Object,
        default: () => ({
            background: '#ffffff',
            fontWeight: '400',
            textAlign: 'left',
        }),
    },
});

const emit = defineEmits([
    'add-sheet',
    'remove-sheet',
    'open-file',
    'save-xlsx',
    'save-csv',
    'toggle-maximize',
    'save-board',
    'add-row',
    'add-column',
    'clear-cell',
    'apply-style',
]);

const ribbonTabs = ['File', 'Home', 'Insert', 'Page Layout', 'Formulas', 'Data', 'Review', 'View', 'Help'];
const activeTab = ref('Home');
const highlightColors = ['#ffffff', '#fef3c7', '#bfdbfe', '#bbf7d0', '#fecaca', '#e9d5ff'];

const formulaPreview = computed(() => {
    const value = String(props.currentCellValue ?? '').trim();

    return value === ''
        ? 'Use the selected cell to type or paste information from Excel.'
        : value;
});

const saveStatus = computed(() => {
    if (props.isSaving) {
        return 'Saving changes...';
    }

    return props.isDirty ? 'Unsaved changes' : 'All changes saved';
});

const setWindowMode = (shouldMaximize) => {
    if (props.isSheetMaximized === shouldMaximize) {
        return;
    }

    emit('toggle-maximize');
};

const ribbonGroups = computed(() => {
    const actionableGroups = {
        File: [
            {
                title: 'Workbook',
                items: [
                    { label: 'Open file', action: 'open-file' },
                    { label: 'Save board', action: 'save-board' },
                    { label: 'Save XLSX', action: 'save-xlsx' },
                    { label: 'Save CSV', action: 'save-csv' },
                ],
            },
        ],
        Home: [
            {
                title: 'Workbook',
                items: [
                    { label: 'Open CSV/XLSX', action: 'open-file' },
                    { label: 'Save board', action: 'save-board' },
                ],
            },
            {
                title: 'Export',
                items: [
                    { label: 'Save XLSX', action: 'save-xlsx' },
                    { label: 'Save CSV', action: 'save-csv' },
                ],
            },
        ],
        Insert: [
            { title: 'Sheets', items: [{ label: 'Add sheet', action: 'add-sheet' }, { label: 'Remove sheet', action: 'remove-sheet' }] },
        ],
        'Page Layout': [
            { title: 'Page setup', items: [{ label: 'Margins' }, { label: 'Orientation' }, { label: 'Scale' }] },
        ],
        Formulas: [
            { title: 'Formula tools', items: [{ label: 'Sum' }, { label: 'Average' }, { label: 'Count' }] },
        ],
        Data: [
            { title: 'Data tools', items: [{ label: 'Sort' }, { label: 'Filter' }, { label: 'Validation' }] },
        ],
        Review: [
            { title: 'Review', items: [{ label: 'Comments' }, { label: 'Protect' }, { label: 'History' }] },
        ],
        View: [
            {
                title: 'Grid',
                items: [
                    { label: 'Add row', action: 'add-row' },
                    { label: 'Add column', action: 'add-column' },
                    { label: 'Add sheet', action: 'add-sheet' },
                    { label: 'Clear cell', action: 'clear-cell' },
                ],
            },
            {
                title: 'Text tools',
                items: [
                    { label: 'Bold', action: 'apply-style', payload: { fontWeight: props.selectedCell.fontWeight === '700' ? '400' : '700' } },
                    { label: 'Left', action: 'apply-style', payload: { textAlign: 'left' } },
                    { label: 'Center', action: 'apply-style', payload: { textAlign: 'center' } },
                    { label: 'Right', action: 'apply-style', payload: { textAlign: 'right' } },
                ],
            },
            {
                title: 'Highlight',
                items: highlightColors.map((color) => ({
                    label: color,
                    type: 'color',
                    action: 'apply-style',
                    payload: { background: color },
                })),
            },
        ],
        Help: [
            { title: 'Help', items: [{ label: 'Shortcuts' }, { label: 'Guides' }, { label: 'Feedback' }] },
        ],
    };

    return actionableGroups[activeTab.value] ?? actionableGroups.Home;
});

const triggerAction = (item = null) => {
    if (!item?.action) {
        return;
    }

    if (item.payload) {
        emit(item.action, item.payload);
        return;
    }

    emit(item.action);
};
</script>

<template>
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="bg-emerald-700 px-4 py-3 text-white sm:px-5">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h3 class="text-lg font-semibold">Scrapboard workspace</h3>
                    <p class="text-sm text-emerald-50">Excel-style navigation shell for <span class="font-semibold">{{ sheetName }}</span>.</p>
                </div>

                <div class="flex items-center gap-2 self-start lg:self-auto">
                    <button
                        type="button"
                        class="flex h-10 w-10 items-center justify-center rounded-[18px] border border-emerald-200 bg-emerald-100 text-emerald-800 shadow-sm transition hover:bg-emerald-200"
                        :title="isSheetMaximized ? 'Minimize scrapboard' : 'Maximize scrapboard'"
                        @click="setWindowMode(!isSheetMaximized)"
                    >
                        <svg v-if="isSheetMaximized" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                            <path d="M9.25 3.75v5h-5" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M14.75 3.75v5h5" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M9.25 20.25v-5h-5" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M14.75 20.25v-5h5" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <svg v-else class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                            <path d="M10 3.75H4.5v5.5" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M14 3.75h5.5v5.5" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M10 20.25H4.5v-5.5" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M14 20.25h5.5v-5.5" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>

                    <span class="rounded-full bg-white/15 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-white">{{ saveStatus }}</span>
                </div>
            </div>

            <div class="mt-3 flex flex-wrap gap-1">
                <button
                    v-for="tab in ribbonTabs"
                    :key="tab"
                    type="button"
                    class="rounded-md px-3 py-1.5 text-sm font-medium transition"
                    :class="activeTab === tab ? 'bg-white text-emerald-700 shadow-sm' : 'text-white/90 hover:bg-white/10'"
                    @click="activeTab = tab"
                >
                    {{ tab }}
                </button>
            </div>
        </div>

        <div class="border-b border-slate-200 bg-slate-50 px-4 py-3 sm:px-5">
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                <div v-for="group in ribbonGroups" :key="`${activeTab}-${group.title}`" class="rounded-xl border border-slate-200 bg-white p-3">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">{{ group.title }}</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <template v-for="item in group.items" :key="`${group.title}-${item.label}`">
                            <button
                                v-if="item.type === 'color'"
                                type="button"
                                class="h-7 w-7 rounded-full border-2 transition"
                                :class="selectedCell.background === item.payload.background ? 'border-slate-900 scale-105' : 'border-slate-200'"
                                :style="{ backgroundColor: item.payload.background }"
                                @click="triggerAction(item)"
                            />
                            <button
                                v-else
                                type="button"
                                class="rounded-md border px-2.5 py-1.5 text-xs font-semibold transition"
                                :class="item.action ? 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50' : 'border-dashed border-slate-200 bg-slate-50 text-slate-400'"
                                @click="triggerAction(item)"
                            >
                                {{ item.label }}
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex flex-col gap-3 px-4 py-3 sm:px-5 lg:flex-row lg:items-center">
            <div class="w-full rounded-md border border-slate-300 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700 lg:w-24">
                {{ currentCellLabel }}
            </div>
            <div class="flex-1 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700">
                {{ formulaPreview }}
            </div>
            <div class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                {{ sheetCount }} sheets
            </div>
        </div>
    </section>
</template>
