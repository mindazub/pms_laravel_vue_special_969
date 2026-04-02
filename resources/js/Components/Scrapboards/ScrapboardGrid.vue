<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    sheet: {
        type: Object,
        required: true,
    },
    selectedCellKey: {
        type: String,
        default: '0:0',
    },
    isMaximized: {
        type: Boolean,
        default: false,
    },
    isCellSelected: {
        type: Function,
        default: () => false,
    },
    selectionText: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['select-cell', 'start-range', 'extend-range', 'finish-range', 'resize-column', 'update-cell', 'paste-grid']);

const rowIndices = computed(() => Array.from({ length: props.sheet?.rows ?? 0 }, (_, index) => index));
const columnIndices = computed(() => Array.from({ length: props.sheet?.columns ?? 0 }, (_, index) => index));

const columnLabel = (index) => {
    let currentIndex = index + 1;
    let label = '';

    while (currentIndex > 0) {
        const remainder = (currentIndex - 1) % 26;
        label = String.fromCharCode(65 + remainder) + label;
        currentIndex = Math.floor((currentIndex - 1) / 26);
    }

    return label;
};

const cellKey = (rowIndex, columnIndex) => `${rowIndex}:${columnIndex}`;

const cellValue = (rowIndex, columnIndex) => {
    return props.sheet?.cells?.[cellKey(rowIndex, columnIndex)]?.value ?? '';
};

const cellStyle = (rowIndex, columnIndex) => {
    const cell = props.sheet?.cells?.[cellKey(rowIndex, columnIndex)] ?? {};

    return {
        backgroundColor: cell.background ?? '#ffffff',
        color: cell.color ?? '#0f172a',
        fontWeight: cell.fontWeight ?? '400',
        textAlign: cell.textAlign ?? 'left',
    };
};

const gridHeightClass = computed(() => {
    return props.isMaximized ? 'max-h-[calc(100vh-220px)]' : 'max-h-[68vh]';
});

const resizeState = ref({
    columnIndex: null,
    startX: 0,
    startWidth: 64,
});
const isResizingColumn = ref(false);

const columnWidth = (columnIndex) => {
    return Math.max(48, Number(props.sheet?.columnWidths?.[columnIndex] ?? 64));
};

const columnStyle = (columnIndex) => {
    const width = columnWidth(columnIndex);

    return {
        width: `${width}px`,
        minWidth: `${width}px`,
        maxWidth: `${width}px`,
    };
};

const handlePaste = (event, rowIndex, columnIndex) => {
    const text = event.clipboardData?.getData('text/plain') ?? '';

    if (!text.includes('\t') && !text.includes('\n')) {
        return;
    }

    event.preventDefault();
    emit('paste-grid', { rowIndex, columnIndex, text });
};

const handleCopy = (event) => {
    if (!props.selectionText) {
        return;
    }

    event.preventDefault();
    event.clipboardData?.setData('text/plain', props.selectionText);
};

const handleCellMouseDown = (event, rowIndex, columnIndex) => {
    if (isResizingColumn.value) {
        return;
    }

    const cellElement = event.currentTarget;

    if (typeof document !== 'undefined') {
        document.body.style.userSelect = 'none';
    }

    emit('start-range', { rowIndex, columnIndex, shiftKey: event.shiftKey });
    window.requestAnimationFrame(() => {
        cellElement.querySelector('input')?.focus({ preventScroll: true });
    });
};

const handleCellMouseEnter = (rowIndex, columnIndex) => {
    if (isResizingColumn.value) {
        return;
    }

    emit('extend-range', { rowIndex, columnIndex });
};

const startColumnResize = (event, columnIndex) => {
    isResizingColumn.value = true;

    if (typeof document !== 'undefined') {
        document.body.style.cursor = 'col-resize';
        document.body.style.userSelect = 'none';
    }

    resizeState.value = {
        columnIndex,
        startX: event.clientX,
        startWidth: columnWidth(columnIndex),
    };
};

const handleGlobalMouseMove = (event) => {
    if (!isResizingColumn.value || resizeState.value.columnIndex === null) {
        return;
    }

    emit('resize-column', {
        columnIndex: resizeState.value.columnIndex,
        width: resizeState.value.startWidth + (event.clientX - resizeState.value.startX),
    });
};

const handleGlobalMouseUp = () => {
    if (isResizingColumn.value) {
        isResizingColumn.value = false;
    }

    if (typeof document !== 'undefined') {
        document.body.style.cursor = '';
        document.body.style.userSelect = '';
    }

    emit('finish-range');
};

onMounted(() => {
    window.addEventListener('mousemove', handleGlobalMouseMove);
    window.addEventListener('mouseup', handleGlobalMouseUp);
});

onBeforeUnmount(() => {
    window.removeEventListener('mousemove', handleGlobalMouseMove);
    window.removeEventListener('mouseup', handleGlobalMouseUp);

    if (typeof document !== 'undefined') {
        document.body.style.cursor = '';
        document.body.style.userSelect = '';
    }
});
</script>

<template>
    <div
        class="overflow-auto rounded-2xl border border-slate-200 bg-white shadow-sm"
        :class="gridHeightClass"
        @copy.capture="handleCopy"
    >
        <table class="min-w-full border-collapse text-[11px]">
            <thead>
                <tr>
                    <th class="sticky left-0 top-0 z-20 w-10 border border-slate-200 bg-slate-100 px-1 py-0.5 text-center text-[10px] font-semibold uppercase tracking-wide text-slate-500" />
                    <th
                        v-for="columnIndex in columnIndices"
                        :key="`column-${columnIndex}`"
                        class="sticky top-0 z-10 border border-slate-200 bg-slate-100 px-1 py-0.5 text-center text-[10px] font-semibold uppercase tracking-wide text-slate-500"
                        :style="columnStyle(columnIndex)"
                    >
                        {{ columnLabel(columnIndex) }}
                        <button
                            type="button"
                            class="absolute inset-y-0 -right-[3px] z-20 w-2 cursor-col-resize bg-transparent"
                            :title="`Resize column ${columnLabel(columnIndex)}`"
                            @mousedown.prevent.stop="startColumnResize($event, columnIndex)"
                        />
                    </th>
                </tr>
            </thead>

            <tbody>
                <tr v-for="rowIndex in rowIndices" :key="`row-${rowIndex}`">
                    <th class="sticky left-0 z-10 w-10 border border-slate-200 bg-slate-100 px-1 py-0.5 text-center text-[10px] font-semibold text-slate-500">
                        {{ rowIndex + 1 }}
                    </th>

                    <td
                        v-for="columnIndex in columnIndices"
                        :key="cellKey(rowIndex, columnIndex)"
                        class="border border-slate-200 p-0 align-top select-none"
                        :style="columnStyle(columnIndex)"
                        :class="[
                            props.isCellSelected(rowIndex, columnIndex) ? 'bg-indigo-50' : '',
                            selectedCellKey === cellKey(rowIndex, columnIndex) ? 'ring-1 ring-inset ring-indigo-500' : '',
                        ]"
                        @mousedown.prevent="handleCellMouseDown($event, rowIndex, columnIndex)"
                        @mouseenter="handleCellMouseEnter(rowIndex, columnIndex)"
                    >
                        <input
                            type="text"
                            spellcheck="false"
                            :value="cellValue(rowIndex, columnIndex)"
                            :style="cellStyle(rowIndex, columnIndex)"
                            class="h-[22px] w-full border-0 bg-transparent px-1.5 text-[11px] leading-none select-none focus:outline-none"
                            @focus="emit('select-cell', { rowIndex, columnIndex, preserveAnchor: $event.shiftKey })"
                            @click.stop="emit('select-cell', { rowIndex, columnIndex, preserveAnchor: $event.shiftKey })"
                            @paste="handlePaste($event, rowIndex, columnIndex)"
                            @input="emit('update-cell', { rowIndex, columnIndex, value: $event.target.value })"
                        >
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
