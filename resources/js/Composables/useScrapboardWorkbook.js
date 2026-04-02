import { computed, ref } from 'vue';

const DEFAULT_ROWS = 200;
const DEFAULT_COLUMNS = 26;
const MAX_ROWS = 1000;
const MAX_COLUMNS = 26;
const DEFAULT_COLUMN_WIDTH = 64;
const MIN_COLUMN_WIDTH = 48;
const MAX_COLUMN_WIDTH = 320;

const defaultCell = () => ({
    value: '',
    background: '#ffffff',
    color: '#0f172a',
    fontWeight: '400',
    textAlign: 'left',
});

const clone = (value) => JSON.parse(JSON.stringify(value));

const parseCellKey = (key = '0:0') => {
    const [rowIndex, columnIndex] = String(key).split(':').map(Number);

    return {
        rowIndex: Number.isNaN(rowIndex) ? 0 : rowIndex,
        columnIndex: Number.isNaN(columnIndex) ? 0 : columnIndex,
    };
};

const createCellKey = (rowIndex, columnIndex) => `${rowIndex}:${columnIndex}`;
const clampColumnWidth = (width) => Math.max(MIN_COLUMN_WIDTH, Math.min(MAX_COLUMN_WIDTH, Number(width || DEFAULT_COLUMN_WIDTH)));
const defaultColumnWidths = (count = DEFAULT_COLUMNS) => Array.from({ length: count }, () => DEFAULT_COLUMN_WIDTH);

const createSheet = (name, index) => ({
    id: `sheet-${Date.now()}-${index}`,
    name,
    rows: DEFAULT_ROWS,
    columns: DEFAULT_COLUMNS,
    columnWidths: defaultColumnWidths(DEFAULT_COLUMNS),
    cells: {},
});

const normalizeCell = (cell = {}) => ({
    ...defaultCell(),
    value: cell?.value ?? '',
    background: cell?.background ?? '#ffffff',
    color: cell?.color ?? '#0f172a',
    fontWeight: cell?.fontWeight === '700' ? '700' : '400',
    textAlign: ['left', 'center', 'right'].includes(cell?.textAlign) ? cell.textAlign : 'left',
});

export const normalizeWorkbook = (workbook = null) => {
    if (!workbook || !Array.isArray(workbook.sheets) || workbook.sheets.length === 0) {
        return {
            activeSheetId: 'sheet-1',
            sheets: [
                {
                    id: 'sheet-1',
                    name: 'Sheet 1',
                    rows: DEFAULT_ROWS,
                    columns: DEFAULT_COLUMNS,
                    columnWidths: defaultColumnWidths(DEFAULT_COLUMNS),
                    cells: {},
                },
                {
                    id: 'sheet-2',
                    name: 'Sheet 2',
                    rows: DEFAULT_ROWS,
                    columns: DEFAULT_COLUMNS,
                    columnWidths: defaultColumnWidths(DEFAULT_COLUMNS),
                    cells: {},
                },
            ],
        };
    }

    const sheets = workbook.sheets.map((sheet, index) => {
        const cells = Object.entries(sheet?.cells ?? {}).reduce((carry, [key, value]) => {
            carry[key] = normalizeCell(value);
            return carry;
        }, {});

        const columns = Math.max(DEFAULT_COLUMNS, Math.min(MAX_COLUMNS, Number(sheet?.columns ?? DEFAULT_COLUMNS)));

        return {
            id: sheet?.id ?? `sheet-${index + 1}`,
            name: sheet?.name?.trim?.() || `Sheet ${index + 1}`,
            rows: Math.max(DEFAULT_ROWS, Math.min(MAX_ROWS, Number(sheet?.rows ?? DEFAULT_ROWS))),
            columns,
            columnWidths: Array.from({ length: columns }, (_, widthIndex) => clampColumnWidth(sheet?.columnWidths?.[widthIndex])),
            cells,
        };
    });

    const activeSheetId = sheets.some((sheet) => sheet.id === workbook.activeSheetId)
        ? workbook.activeSheetId
        : sheets[0].id;

    return {
        activeSheetId,
        sheets,
    };
};

export function useScrapboardWorkbook(initialScrapboard = null) {
    const workbook = ref(normalizeWorkbook(initialScrapboard?.workbook));
    const selectedSheetId = ref(workbook.value.activeSheetId);
    const selectedCellKey = ref('0:0');
    const selectionAnchorKey = ref('0:0');
    const selectionFocusKey = ref('0:0');
    const isSelectingRange = ref(false);

    const activeSheet = computed(() => {
        return workbook.value.sheets.find((sheet) => sheet.id === selectedSheetId.value) ?? workbook.value.sheets[0];
    });

    const selectedCell = computed(() => {
        return activeSheet.value?.cells?.[selectedCellKey.value] ?? defaultCell();
    });

    const selectedRange = computed(() => {
        const anchor = parseCellKey(selectionAnchorKey.value);
        const focus = parseCellKey(selectionFocusKey.value);

        return {
            top: Math.min(anchor.rowIndex, focus.rowIndex),
            bottom: Math.max(anchor.rowIndex, focus.rowIndex),
            left: Math.min(anchor.columnIndex, focus.columnIndex),
            right: Math.max(anchor.columnIndex, focus.columnIndex),
        };
    });

    const isCellSelected = (rowIndex, columnIndex) => {
        const range = selectedRange.value;

        return rowIndex >= range.top
            && rowIndex <= range.bottom
            && columnIndex >= range.left
            && columnIndex <= range.right;
    };

    const resetSelection = (key = '0:0') => {
        selectedCellKey.value = key;
        selectionAnchorKey.value = key;
        selectionFocusKey.value = key;
        isSelectingRange.value = false;
    };

    const setActiveSheet = (sheetId) => {
        selectedSheetId.value = sheetId;
        workbook.value.activeSheetId = sheetId;
        resetSelection('0:0');
    };

    const ensureCell = (rowIndex, columnIndex) => {
        if (!activeSheet.value) {
            return defaultCell();
        }

        const key = `${rowIndex}:${columnIndex}`;

        if (!activeSheet.value.cells[key]) {
            activeSheet.value.cells[key] = defaultCell();
        }

        return activeSheet.value.cells[key];
    };

    const loadScrapboard = (scrapboard) => {
        workbook.value = normalizeWorkbook(scrapboard?.workbook);
        selectedSheetId.value = workbook.value.activeSheetId;
        resetSelection('0:0');
    };

    const selectCell = ({ rowIndex, columnIndex, preserveAnchor = false }) => {
        const key = createCellKey(rowIndex, columnIndex);

        selectedCellKey.value = key;

        if (!preserveAnchor) {
            selectionAnchorKey.value = key;
        }

        selectionFocusKey.value = key;
        ensureCell(rowIndex, columnIndex);
    };

    const beginRangeSelection = ({ rowIndex, columnIndex, shiftKey = false }) => {
        isSelectingRange.value = true;
        selectCell({ rowIndex, columnIndex, preserveAnchor: shiftKey });
    };

    const extendRangeSelection = ({ rowIndex, columnIndex }) => {
        if (!isSelectingRange.value) {
            return;
        }

        const key = createCellKey(rowIndex, columnIndex);
        selectedCellKey.value = key;
        selectionFocusKey.value = key;
        ensureCell(rowIndex, columnIndex);
    };

    const finishRangeSelection = () => {
        isSelectingRange.value = false;
    };

    const ensureSheetSize = (rowCount, columnCount) => {
        if (!activeSheet.value) {
            return;
        }

        activeSheet.value.rows = Math.max(activeSheet.value.rows, Math.min(MAX_ROWS, rowCount));
        activeSheet.value.columns = Math.max(activeSheet.value.columns, Math.min(MAX_COLUMNS, columnCount));

        while ((activeSheet.value.columnWidths?.length ?? 0) < activeSheet.value.columns) {
            activeSheet.value.columnWidths.push(DEFAULT_COLUMN_WIDTH);
        }
    };

    const updateCell = ({ rowIndex, columnIndex, value }) => {
        ensureSheetSize(rowIndex + 1, columnIndex + 1);

        const cell = ensureCell(rowIndex, columnIndex);
        cell.value = value;
    };

    const applyCellStyle = (stylePatch) => {
        const [rowIndex, columnIndex] = selectedCellKey.value.split(':').map(Number);
        const cell = ensureCell(rowIndex, columnIndex);

        Object.assign(cell, stylePatch);
    };

    const clearSelectedCell = () => {
        if (!activeSheet.value?.cells?.[selectedCellKey.value]) {
            return;
        }

        delete activeSheet.value.cells[selectedCellKey.value];
    };

    const addRow = () => {
        if (activeSheet.value) {
            activeSheet.value.rows += 1;
        }
    };

    const addColumn = () => {
        if (activeSheet.value && activeSheet.value.columns < 26) {
            activeSheet.value.columns += 1;
            activeSheet.value.columnWidths.push(DEFAULT_COLUMN_WIDTH);
        }
    };

    const setColumnWidth = ({ columnIndex, width }) => {
        if (!activeSheet.value) {
            return;
        }

        ensureSheetSize(activeSheet.value.rows, columnIndex + 1);
        activeSheet.value.columnWidths[columnIndex] = clampColumnWidth(width);
    };

    const pasteClipboardText = ({ rowIndex, columnIndex, text }) => {
        const normalizedText = String(text ?? '').replace(/\r\n/g, '\n').replace(/\r/g, '\n');
        const rows = normalizedText.split('\n');

        while (rows.length > 0 && rows[rows.length - 1] === '') {
            rows.pop();
        }

        const matrix = rows.map((row) => row.split('\t'));
        const longestRow = Math.max(...matrix.map((row) => row.length), 1);

        ensureSheetSize(rowIndex + matrix.length, columnIndex + longestRow);

        matrix.forEach((values, matrixRowIndex) => {
            values.forEach((value, matrixColumnIndex) => {
                updateCell({
                    rowIndex: rowIndex + matrixRowIndex,
                    columnIndex: columnIndex + matrixColumnIndex,
                    value,
                });
            });
        });
    };

    const addSheet = (name) => {
        const nextIndex = workbook.value.sheets.length + 1;
        const sheet = createSheet(name?.trim() || `Sheet ${nextIndex}`, nextIndex);
        workbook.value.sheets.push(sheet);
        setActiveSheet(sheet.id);
    };

    const renameActiveSheet = (name) => {
        if (!activeSheet.value) {
            return;
        }

        const nextName = name?.trim();

        if (nextName) {
            activeSheet.value.name = nextName;
        }
    };

    const removeActiveSheet = () => {
        if (workbook.value.sheets.length <= 1) {
            return false;
        }

        const nextSheets = workbook.value.sheets.filter((sheet) => sheet.id !== selectedSheetId.value);
        workbook.value.sheets = nextSheets;
        setActiveSheet(nextSheets[0].id);

        return true;
    };

    const copySelectedRangeToText = () => {
        const range = selectedRange.value;
        const rows = [];

        for (let rowIndex = range.top; rowIndex <= range.bottom; rowIndex += 1) {
            const columns = [];

            for (let columnIndex = range.left; columnIndex <= range.right; columnIndex += 1) {
                columns.push(activeSheet.value?.cells?.[createCellKey(rowIndex, columnIndex)]?.value ?? '');
            }

            rows.push(columns.join('\t'));
        }

        return rows.join('\n');
    };

    const replaceWorkbook = (nextWorkbook) => {
        workbook.value = normalizeWorkbook(nextWorkbook);
        selectedSheetId.value = workbook.value.activeSheetId;
        resetSelection('0:0');
    };

    const serializableWorkbook = computed(() => clone(workbook.value));

    return {
        workbook,
        activeSheet,
        selectedSheetId,
        selectedCell,
        selectedCellKey,
        selectedRange,
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
    };
}
