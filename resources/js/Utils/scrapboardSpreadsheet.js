import * as XLSX from 'xlsx';
import { normalizeWorkbook } from '@/Composables/useScrapboardWorkbook';

const DEFAULT_ROWS = 200;
const DEFAULT_COLUMNS = 26;
const MAX_ROWS = 1000;
const MAX_COLUMNS = 26;

const sanitizeFileName = (name, extension) => {
    const baseName = String(name ?? 'scrapboard')
        .trim()
        .replace(/[\\/:*?"<>|]+/g, '-')
        .replace(/\s+/g, ' ')
        || 'scrapboard';

    return baseName.toLowerCase().endsWith(`.${extension}`)
        ? baseName
        : `${baseName}.${extension}`;
};

const defaultCell = (value = '') => ({
    value: String(value),
    background: '#ffffff',
    color: '#0f172a',
    fontWeight: '400',
    textAlign: 'left',
});

const downloadTextFile = (content, fileName, mimeType) => {
    const blob = new Blob([content], { type: mimeType });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');

    link.href = url;
    link.download = fileName;
    link.click();

    window.setTimeout(() => {
        window.URL.revokeObjectURL(url);
    }, 250);
};

export const sheetToMatrix = (sheet) => {
    const normalizedSheet = sheet ?? {
        rows: DEFAULT_ROWS,
        columns: DEFAULT_COLUMNS,
        cells: {},
    };

    const matrix = Array.from({ length: normalizedSheet.rows ?? DEFAULT_ROWS }, () =>
        Array.from({ length: normalizedSheet.columns ?? DEFAULT_COLUMNS }, () => '')
    );

    Object.entries(normalizedSheet.cells ?? {}).forEach(([key, cell]) => {
        const [rowIndex, columnIndex] = key.split(':').map(Number);

        if (Number.isNaN(rowIndex) || Number.isNaN(columnIndex)) {
            return;
        }

        if (!matrix[rowIndex] || columnIndex >= matrix[rowIndex].length) {
            return;
        }

        matrix[rowIndex][columnIndex] = String(cell?.value ?? '');
    });

    let lastRowIndex = -1;
    let lastColumnIndex = -1;

    matrix.forEach((row, rowIndex) => {
        row.forEach((value, columnIndex) => {
            if (String(value ?? '').trim() !== '') {
                lastRowIndex = Math.max(lastRowIndex, rowIndex);
                lastColumnIndex = Math.max(lastColumnIndex, columnIndex);
            }
        });
    });

    if (lastRowIndex === -1 || lastColumnIndex === -1) {
        return [['']];
    }

    return matrix
        .slice(0, lastRowIndex + 1)
        .map((row) => row.slice(0, lastColumnIndex + 1));
};

const sheetToWorksheet = (sheet) => {
    const worksheet = XLSX.utils.aoa_to_sheet(sheetToMatrix(sheet));
    const columnWidths = Array.isArray(sheet?.columnWidths) ? sheet.columnWidths : [];

    if (columnWidths.length > 0) {
        worksheet['!cols'] = columnWidths.map((width) => ({ wpx: Number(width || 64) }));
    }

    return worksheet;
};

const hasSheetContent = (sheet) => {
    return Object.values(sheet?.cells ?? {}).some((cell) => String(cell?.value ?? '').trim() !== '');
};

const buildExportSheets = (workbookData, activeSheetId = null) => {
    const normalizedWorkbook = normalizeWorkbook(workbookData);
    const activeSheet = normalizedWorkbook.sheets.find((sheet) => sheet.id === activeSheetId) ?? normalizedWorkbook.sheets[0];
    const populatedSheets = normalizedWorkbook.sheets.filter((sheet) => hasSheetContent(sheet));

    const sheetsToExport = populatedSheets.length > 0 ? populatedSheets : [activeSheet];

    if (!activeSheet || !sheetsToExport.some((sheet) => sheet.id === activeSheet.id)) {
        return sheetsToExport;
    }

    return [
        activeSheet,
        ...sheetsToExport.filter((sheet) => sheet.id !== activeSheet.id),
    ];
};

export const parseClipboardMatrix = (text) => {
    const normalizedText = String(text ?? '').replace(/\r\n/g, '\n').replace(/\r/g, '\n');
    const rows = normalizedText.split('\n');

    while (rows.length > 0 && rows[rows.length - 1] === '') {
        rows.pop();
    }

    return rows.map((row) => row.split('\t'));
};

export const createWorkbookFromMatrices = (sheets = []) => {
    const normalizedSheets = sheets
        .slice(0, 20)
        .map((sheet, index) => {
            const matrix = Array.isArray(sheet?.matrix) ? sheet.matrix : [];
            const rowCount = Math.max(DEFAULT_ROWS, Math.min(MAX_ROWS, matrix.length || DEFAULT_ROWS));
            const columnCount = DEFAULT_COLUMNS;
            const cells = {};

            matrix.slice(0, rowCount).forEach((row, rowIndex) => {
                const values = Array.isArray(row) ? row : [row];

                values.slice(0, columnCount).forEach((value, columnIndex) => {
                    if (value === null || value === undefined || value === '') {
                        return;
                    }

                    cells[`${rowIndex}:${columnIndex}`] = defaultCell(value);
                });
            });

            return {
                id: `sheet-${index + 1}`,
                name: String(sheet?.name ?? `Sheet ${index + 1}`).trim() || `Sheet ${index + 1}`,
                rows: rowCount,
                columns: columnCount,
                columnWidths: Array.from({ length: columnCount }, () => 64),
                cells,
            };
        });

    return normalizeWorkbook({
        activeSheetId: normalizedSheets[0]?.id ?? 'sheet-1',
        sheets: normalizedSheets.length > 0
            ? normalizedSheets
            : [
                {
                    id: 'sheet-1',
                    name: 'Sheet 1',
                    rows: DEFAULT_ROWS,
                    columns: DEFAULT_COLUMNS,
                    columnWidths: Array.from({ length: DEFAULT_COLUMNS }, () => 64),
                    cells: {},
                },
            ],
    });
};

export const readSpreadsheetFile = async (file) => {
    const arrayBuffer = await file.arrayBuffer();
    const workbook = XLSX.read(arrayBuffer, {
        type: 'array',
        cellDates: false,
    });

    return workbook.SheetNames.map((sheetName) => ({
        name: sheetName,
        matrix: XLSX.utils.sheet_to_json(workbook.Sheets[sheetName], {
            header: 1,
            raw: false,
            blankrows: true,
            defval: '',
        }),
    }));
};

export const saveWorkbookAsXlsx = (workbookData, fileName = 'scrapboard.xlsx', activeSheetId = null) => {
    const workbook = XLSX.utils.book_new();
    const sheetsToExport = buildExportSheets(workbookData, activeSheetId);

    sheetsToExport.forEach((sheet, index) => {
        XLSX.utils.book_append_sheet(
            workbook,
            sheetToWorksheet(sheet),
            String(sheet?.name ?? `Sheet ${index + 1}`).slice(0, 31) || `Sheet ${index + 1}`
        );
    });

    workbook.Workbook = {
        Views: [{ activeTab: 0 }],
    };

    XLSX.writeFile(workbook, sanitizeFileName(fileName, 'xlsx'));
};

export const saveSheetAsCsv = (sheet, fileName = 'sheet.csv') => {
    const worksheet = sheetToWorksheet(sheet);
    const csv = XLSX.utils.sheet_to_csv(worksheet);

    downloadTextFile(csv, sanitizeFileName(fileName, 'csv'), 'text/csv;charset=utf-8;');
};
