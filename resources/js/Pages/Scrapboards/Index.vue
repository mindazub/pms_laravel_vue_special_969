<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ScrapboardGrid from '@/Components/Scrapboards/ScrapboardGrid.vue';
import ScrapboardHeader from '@/Components/Scrapboards/ScrapboardHeader.vue';
import { useScrapboardWorkbook } from '@/Composables/useScrapboardWorkbook';
import {
    createWorkbookFromMatrices,
    readSpreadsheetFile,
    saveSheetAsCsv,
    saveWorkbookAsXlsx,
} from '@/Utils/scrapboardSpreadsheet';
import { confirmDangerAction, promptForName } from '@/Utils/scrapboardDialogs';

const props = defineProps({
    scrapboards: {
        type: Array,
        default: () => [],
    },
    selectedScrapboard: {
        type: Object,
        default: null,
    },
});

const managedScrapboards = ref(props.scrapboards ?? []);
const activeScrapboard = ref(props.selectedScrapboard ?? null);
const isDirty = ref(false);
const isSaving = ref(false);
const isSheetMaximized = ref(false);
const importFileInput = ref(null);
const statusMessage = ref('');
const statusTone = ref('info');

const {
    workbook,
    activeSheet,
    selectedSheetId,
    selectedCell,
    selectedCellKey,
    isCellSelected,
    loadScrapboard,
    replaceWorkbook,
    setActiveSheet,
    selectCell,
    beginRangeSelection,
    extendRangeSelection,
    finishRangeSelection,
    updateCell,
    pasteClipboardText,
    copySelectedRangeToText,
    applyCellStyle,
    clearSelectedCell,
    addRow,
    addColumn,
    setColumnWidth,
    addSheet,
    renameActiveSheet,
    removeActiveSheet,
    serializableWorkbook,
} = useScrapboardWorkbook(props.selectedScrapboard);

watch(
    () => props.scrapboards,
    (scrapboards) => {
        managedScrapboards.value = scrapboards ?? [];
    },
    { deep: true, immediate: true },
);

watch(
    () => props.selectedScrapboard,
    (scrapboard) => {
        activeScrapboard.value = scrapboard ?? null;
        loadScrapboard(scrapboard);
        isDirty.value = false;
    },
    { deep: true, immediate: true },
);

watch(isSheetMaximized, (value) => {
    if (typeof document === 'undefined') {
        return;
    }

    document.body.style.overflow = value ? 'hidden' : '';
});

onBeforeUnmount(() => {
    if (typeof document !== 'undefined') {
        document.body.style.overflow = '';
    }
});

const setStatus = (message, tone = 'info') => {
    statusMessage.value = message;
    statusTone.value = tone;
};

const statusClasses = computed(() => {
    if (statusTone.value === 'success') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-700';
    }

    if (statusTone.value === 'warning') {
        return 'border-amber-200 bg-amber-50 text-amber-700';
    }

    if (statusTone.value === 'error') {
        return 'border-rose-200 bg-rose-50 text-rose-700';
    }

    return 'border-slate-200 bg-slate-50 text-slate-600';
});

const currentScrapboardName = computed(() => activeScrapboard.value?.name ?? 'Untitled scrapboard');

const workbookStats = computed(() => ({
    sheetCount: workbook.value?.sheets?.length ?? 0,
    rowCount: activeSheet.value?.rows ?? 0,
    columnCount: activeSheet.value?.columns ?? 0,
}));

const selectedCellLabel = computed(() => {
    const [rowIndex, columnIndex] = String(selectedCellKey.value ?? '0:0').split(':').map(Number);
    const columnName = String.fromCharCode(65 + (columnIndex ?? 0));

    return `${columnName}${(rowIndex ?? 0) + 1}`;
});

const selectionText = computed(() => copySelectedRangeToText());

const markDirty = () => {
    isDirty.value = true;
    setStatus('Unsaved changes in the current scrapboard.', 'warning');
};

const refreshCurrentPage = (fallbackId = null) => {
    const targetId = fallbackId ?? activeScrapboard.value?.id;

    if (targetId) {
        router.visit(route('scrapboards.show', targetId));
        return;
    }

    router.visit(route('scrapboards.index'));
};

const openScrapboard = (scrapboard) => {
    if (!scrapboard?.id) {
        router.visit(route('scrapboards.index'));
        return;
    }

    router.visit(route('scrapboards.show', scrapboard.id));
};

const createScrapboard = async () => {
    const name = await promptForName({
        title: 'Create scrapboard',
        inputLabel: 'Scrapboard name',
        inputValue: `Scrapboard ${managedScrapboards.value.length + 1}`,
        placeholder: 'Enter a scrapboard name',
        confirmButtonText: 'Create',
    });

    if (!name) {
        return;
    }

    try {
        const response = await window.axios.post(route('scrapboards.store'), {
            'name': name.trim(),
        });

        setStatus('New scrapboard created.', 'success');
        router.visit(route('scrapboards.show', response.data.id));
    } catch (error) {
        setStatus(error?.response?.data?.message ?? 'Unable to create a scrapboard right now.', 'error');
    }
};

const renameScrapboard = async (scrapboard) => {
    const name = await promptForName({
        title: 'Rename scrapboard',
        inputLabel: 'Scrapboard name',
        inputValue: scrapboard?.name ?? '',
        placeholder: 'Enter a new scrapboard name',
        confirmButtonText: 'Rename',
    });

    if (!name || !scrapboard?.id) {
        return;
    }

    try {
        await window.axios.put(route('scrapboards.update', scrapboard.id), {
            'name': name.trim(),
        });

        setStatus('Scrapboard renamed.', 'success');
        refreshCurrentPage(scrapboard.id === activeScrapboard.value?.id ? scrapboard.id : undefined);
    } catch (error) {
        setStatus(error?.response?.data?.message ?? 'Unable to rename this scrapboard.', 'error');
    }
};

const deleteScrapboard = async (scrapboard) => {
    if (!scrapboard?.id) {
        return;
    }

    const confirmed = await confirmDangerAction({
        title: 'Delete scrapboard?',
        text: `${scrapboard.name} and all sheet data inside it will be removed.`,
        confirmButtonText: 'Delete board',
    });

    if (!confirmed) {
        return;
    }

    try {
        await window.axios.delete(route('scrapboards.destroy', scrapboard.id));

        const fallbackBoard = managedScrapboards.value.find((item) => item.id !== scrapboard.id);
        setStatus('Scrapboard deleted.', 'success');
        refreshCurrentPage(fallbackBoard?.id ?? null);
    } catch (error) {
        setStatus(error?.response?.data?.message ?? 'Unable to delete this scrapboard.', 'error');
    }
};

const saveWorkbook = async () => {
    if (!activeScrapboard.value?.id) {
        return;
    }

    isSaving.value = true;

    try {
        const response = await window.axios.put(route('scrapboards.update', activeScrapboard.value.id), {
            'name': activeScrapboard.value.name,
            'workbook': serializableWorkbook.value,
        });

        activeScrapboard.value = response.data;
        isDirty.value = false;
        setStatus('Scrapboard saved successfully.', 'success');
        router.reload({ only: ['scrapboards', 'selectedScrapboard'] });
    } catch (error) {
        setStatus(error?.response?.data?.message ?? 'Unable to save the current scrapboard.', 'error');
    } finally {
        isSaving.value = false;
    }
};

const handleCellUpdate = (payload) => {
    updateCell(payload);
    markDirty();
};

const handleApplyStyle = (stylePatch) => {
    applyCellStyle(stylePatch);
    markDirty();
};

const handleAddRow = () => {
    addRow();
    markDirty();
};

const handleAddColumn = () => {
    addColumn();
    markDirty();
};

const handleResizeColumn = (payload) => {
    setColumnWidth(payload);
    markDirty();
};

const handleAddSheet = async () => {
    const name = await promptForName({
        title: 'Add new sheet',
        inputLabel: 'Sheet name',
        inputValue: `Sheet ${workbook.value.sheets.length + 1}`,
        placeholder: 'Enter a sheet name',
        confirmButtonText: 'Add sheet',
    });

    if (!name) {
        return;
    }

    addSheet(name);
    markDirty();
};

const handleRemoveSheet = () => {
    const didRemove = removeActiveSheet();

    if (!didRemove) {
        setStatus('Each scrapboard must keep at least one sheet.', 'warning');
        return;
    }

    markDirty();
};

const handleRenameSheet = async (sheet = activeSheet.value) => {
    if (!sheet) {
        return;
    }

    const name = await promptForName({
        title: 'Rename sheet',
        inputLabel: 'Sheet name',
        inputValue: sheet.name ?? '',
        placeholder: 'Enter a sheet name',
        confirmButtonText: 'Rename',
    });

    if (!name) {
        return;
    }

    if (sheet.id && sheet.id !== activeSheet.value?.id) {
        setActiveSheet(sheet.id);
    }

    renameActiveSheet(name);
    markDirty();
};

const handleClearCell = () => {
    clearSelectedCell();
    markDirty();
};

const handlePasteGrid = (payload) => {
    pasteClipboardText(payload);
    markDirty();
    setStatus('Excel range pasted into the current sheet.', 'success');
};

const openImportDialog = () => {
    importFileInput.value?.click();
};

const handleImportFile = async (event) => {
    const file = event.target?.files?.[0];

    if (!file) {
        return;
    }

    try {
        const matrices = await readSpreadsheetFile(file);
        const importedWorkbook = createWorkbookFromMatrices(matrices);

        replaceWorkbook(importedWorkbook);
        markDirty();
        setStatus(`${file.name} loaded. Click save board to keep the imported spreadsheet.`, 'success');
    } catch (error) {
        setStatus('Unable to open that spreadsheet file. Please use a valid CSV, XLS, or XLSX file.', 'error');
    } finally {
        if (event.target) {
            event.target.value = '';
        }
    }
};

const exportAsXlsx = () => {
    saveWorkbookAsXlsx(serializableWorkbook.value, `${currentScrapboardName.value}.xlsx`, selectedSheetId.value);
    setStatus('Workbook exported as XLSX with all populated sheet data.', 'success');
};

const exportAsCsv = () => {
    if (!activeSheet.value) {
        setStatus('Select a sheet before exporting CSV.', 'warning');
        return;
    }

    saveSheetAsCsv(activeSheet.value, `${currentScrapboardName.value} - ${activeSheet.value.name}.csv`);
    setStatus('Current sheet exported as CSV.', 'success');
};

const handleDeleteCurrentBoard = () => {
    if (activeScrapboard.value) {
        void deleteScrapboard(activeScrapboard.value);
    }
};

const handleRenameCurrentBoard = () => {
    if (activeScrapboard.value) {
        void renameScrapboard(activeScrapboard.value);
    }
};

const toggleSheetMaximized = () => {
    isSheetMaximized.value = !isSheetMaximized.value;
};
</script>

<template>
    <Head title="Scrapboard" />

    <AuthenticatedLayout>
        <div class="space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <div v-if="!isSheetMaximized" class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-slate-900">Scrapboard</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Spreadsheet-style planning boards with multiple sheets, editable cells, highlighting, and quick CRUD actions.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <span class="rounded-full bg-slate-200 px-3 py-1">{{ workbookStats.sheetCount }} sheets</span>
                    <span class="rounded-full bg-slate-200 px-3 py-1">{{ workbookStats.rowCount }} rows</span>
                    <span class="rounded-full bg-slate-200 px-3 py-1">{{ workbookStats.columnCount }} columns</span>
                </div>
            </div>

            <div :class="isSheetMaximized ? 'fixed inset-0 z-50 flex flex-col gap-4 overflow-hidden bg-slate-50 p-3 sm:p-4' : 'space-y-6'">
                <div v-if="statusMessage" class="rounded-xl border px-3 py-2 text-sm" :class="statusClasses">
                    {{ statusMessage }}
                </div>

                <ScrapboardHeader
                    :current-cell-label="selectedCellLabel"
                    :current-cell-value="selectedCell?.value ?? ''"
                    :sheet-name="activeSheet?.name ?? 'Sheet 1'"
                    :sheet-count="workbookStats.sheetCount"
                    :is-saving="isSaving"
                    :is-dirty="isDirty"
                    :is-sheet-maximized="isSheetMaximized"
                    :selected-cell="selectedCell"
                    @add-sheet="handleAddSheet"
                    @remove-sheet="handleRemoveSheet"
                    @add-row="handleAddRow"
                    @add-column="handleAddColumn"
                    @clear-cell="handleClearCell"
                    @apply-style="handleApplyStyle"
                    @open-file="openImportDialog"
                    @save-xlsx="exportAsXlsx"
                    @save-csv="exportAsCsv"
                    @toggle-maximize="toggleSheetMaximized"
                    @save-board="saveWorkbook"
                />

                <input
                    ref="importFileInput"
                    type="file"
                    accept=".csv,.xls,.xlsx,text/csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                    class="hidden"
                    @change="handleImportFile"
                >

                <section
                    class="border border-slate-200 bg-slate-50 p-4 shadow-sm"
                    :class="isSheetMaximized ? 'flex min-h-0 flex-1 flex-col overflow-hidden rounded-2xl bg-white' : 'rounded-2xl'"
                >
                <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h3
                                class="cursor-text text-lg font-semibold text-slate-900"
                                title="Double-click to rename the current scrapboard"
                                @dblclick="handleRenameCurrentBoard"
                            >
                                {{ currentScrapboardName }}
                            </h3>
                            <button
                                type="button"
                                class="inline-flex items-center rounded-md border border-rose-200 bg-white px-2 py-1 text-[11px] font-semibold text-rose-600 transition hover:bg-rose-50"
                                title="Delete current scrapboard"
                                @click="handleDeleteCurrentBoard"
                            >
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M8.5 2a1 1 0 00-.894.553L7.382 3H5a1 1 0 000 2h.293l.88 9.142A2 2 0 008.163 16h3.674a2 2 0 001.99-1.858L14.707 5H15a1 1 0 100-2h-2.382l-.224-.447A1 1 0 0011.5 2h-3zM8 7a1 1 0 012 0v5a1 1 0 11-2 0V7zm4-1a1 1 0 00-1 1v5a1 1 0 102 0V7a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>
                        <p class="mt-1 text-sm text-slate-500">
                            Double-click the scrapboard title to rename it. Drag across cells to select a range and drag a column edge in the header to resize it.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="sheet in workbook.sheets"
                            :key="sheet.id"
                            type="button"
                            class="rounded-md px-3 py-1.5 text-xs font-semibold transition"
                            :class="selectedSheetId === sheet.id ? 'bg-indigo-600 text-white' : 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50'"
                            @click="setActiveSheet(sheet.id)"
                            @dblclick.prevent="handleRenameSheet(sheet)"
                        >
                            {{ sheet.name }}
                        </button>
                        <button
                            type="button"
                            class="rounded-md border border-dashed border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                            @click="handleAddSheet"
                        >
                            + Sheet
                        </button>
                    </div>
                </div>

                <div v-if="activeSheet" :class="isSheetMaximized ? 'min-h-0 flex-1' : ''">
                    <ScrapboardGrid
                        :sheet="activeSheet"
                        :selected-cell-key="selectedCellKey"
                        :is-maximized="isSheetMaximized"
                        :is-cell-selected="isCellSelected"
                        :selection-text="selectionText"
                        @select-cell="selectCell"
                        @start-range="beginRangeSelection"
                        @extend-range="extendRangeSelection"
                        @finish-range="finishRangeSelection"
                        @resize-column="handleResizeColumn"
                        @update-cell="handleCellUpdate"
                        @paste-grid="handlePasteGrid"
                    />
                </div>

                <div v-else class="rounded-xl border border-dashed border-slate-300 bg-white px-4 py-10 text-center text-sm text-slate-500">
                    Select or create a scrapboard to start entering data.
                </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
