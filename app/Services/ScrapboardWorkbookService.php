<?php

namespace App\Services;

use Illuminate\Support\Str;

class ScrapboardWorkbookService
{
    private const DEFAULT_ROWS = 200;

    private const DEFAULT_COLUMNS = 26;

    private const DEFAULT_COLUMN_WIDTH = 64;

    public function defaultWorkbook(): array
    {
        return [
            'activeSheetId' => 'sheet-1',
            'sheets' => [
                $this->defaultSheet('sheet-1', 'Sheet 1'),
                $this->defaultSheet('sheet-2', 'Sheet 2'),
            ],
        ];
    }

    public function normalize(?array $workbook): array
    {
        if (! is_array($workbook) || ! isset($workbook['sheets']) || ! is_array($workbook['sheets']) || $workbook['sheets'] === []) {
            return $this->defaultWorkbook();
        }

        $sheets = collect($workbook['sheets'])
            ->values()
            ->map(function (mixed $sheet, int $index): array {
                if (! is_array($sheet)) {
                    return $this->defaultSheet('sheet-'.($index + 1), 'Sheet '.($index + 1));
                }

                $rows = max(self::DEFAULT_ROWS, min(1000, (int) ($sheet['rows'] ?? self::DEFAULT_ROWS)));
                $columns = max(self::DEFAULT_COLUMNS, min(self::DEFAULT_COLUMNS, (int) ($sheet['columns'] ?? self::DEFAULT_COLUMNS)));
                $columnWidths = collect($sheet['columnWidths'] ?? [])
                    ->map(fn (mixed $width): int => max(48, min(320, (int) $width ?: self::DEFAULT_COLUMN_WIDTH)))
                    ->pad($columns, self::DEFAULT_COLUMN_WIDTH)
                    ->take($columns)
                    ->values()
                    ->all();
                $cells = [];

                foreach (($sheet['cells'] ?? []) as $key => $cell) {
                    if (! is_string($key) || ! preg_match('/^\d+:\d+$/', $key) || ! is_array($cell)) {
                        continue;
                    }

                    [$rowIndex, $columnIndex] = array_map('intval', explode(':', $key));

                    if ($rowIndex >= $rows || $columnIndex >= $columns) {
                        continue;
                    }

                    $textAlign = $cell['textAlign'] ?? 'left';

                    $cells[$key] = [
                        'value' => Str::limit((string) ($cell['value'] ?? ''), 5000, ''),
                        'background' => $this->sanitizeColor($cell['background'] ?? '#ffffff', '#ffffff'),
                        'color' => $this->sanitizeColor($cell['color'] ?? '#0f172a', '#0f172a'),
                        'fontWeight' => ($cell['fontWeight'] ?? '400') === '700' ? '700' : '400',
                        'textAlign' => in_array($textAlign, ['left', 'center', 'right'], true)
                            ? $textAlign
                            : 'left',
                    ];
                }

                return [
                    'id' => (string) ($sheet['id'] ?? 'sheet-'.($index + 1)),
                    'name' => $this->sanitizeSheetName($sheet['name'] ?? null, $index),
                    'rows' => $rows,
                    'columns' => $columns,
                    'columnWidths' => $columnWidths,
                    'cells' => $cells,
                ];
            })
            ->all();

        $activeSheetId = (string) ($workbook['activeSheetId'] ?? $sheets[0]['id']);

        if (! collect($sheets)->pluck('id')->contains($activeSheetId)) {
            $activeSheetId = $sheets[0]['id'];
        }

        return [
            'activeSheetId' => $activeSheetId,
            'sheets' => $sheets,
        ];
    }

    private function defaultSheet(string $id, string $name): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'rows' => self::DEFAULT_ROWS,
            'columns' => self::DEFAULT_COLUMNS,
            'columnWidths' => array_fill(0, self::DEFAULT_COLUMNS, self::DEFAULT_COLUMN_WIDTH),
            'cells' => [],
        ];
    }

    private function sanitizeSheetName(mixed $name, int $index): string
    {
        $sheetName = trim((string) ($name ?? ''));

        if ($sheetName === '') {
            return 'Sheet '.($index + 1);
        }

        return Str::limit($sheetName, 60, '');
    }

    private function sanitizeColor(mixed $color, string $fallback): string
    {
        $value = trim((string) $color);

        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1
            ? strtolower($value)
            : $fallback;
    }
}
