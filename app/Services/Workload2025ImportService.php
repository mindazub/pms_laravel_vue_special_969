<?php

namespace App\Services;

use App\Models\Note;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class Workload2025ImportService
{
    /**
     * @return array{
     *     dry_run: bool,
     *     import_batch: string,
     *     total_rows: int,
     *     processed_rows: int,
     *     created_count: int,
     *     updated_count: int,
     *     skipped_rows: int,
     *     error_count: int,
     *     errors: array<int, string>
     * }
     */
    public function importFromPath(string $path, User $user, bool $dryRun = false): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new InvalidArgumentException('CSV file is not readable.');
        }

        $importBatch = (string) Str::uuid();
        $summary = [
            'dry_run' => $dryRun,
            'import_batch' => $importBatch,
            'total_rows' => 0,
            'processed_rows' => 0,
            'created_count' => 0,
            'updated_count' => 0,
            'skipped_rows' => 0,
            'error_count' => 0,
            'errors' => [],
        ];

        $rows = $this->readCsvRows($path);
        $summary['total_rows'] = count($rows);

        DB::beginTransaction();

        try {
            foreach ($rows as $row) {
                if ($this->isEmptyRow($row['data'])) {
                    $summary['skipped_rows']++;

                    continue;
                }

                try {
                    $payload = $this->normalizeRow($row['data'], $user, $importBatch);
                } catch (InvalidArgumentException $exception) {
                    if ($exception->getMessage() === 'Owner, week, and task are required.') {
                        $summary['skipped_rows']++;

                        continue;
                    }

                    $summary['errors'][] = "Line {$row['line']}: {$exception->getMessage()}";

                    continue;
                }

                $summary['processed_rows']++;

                if ($dryRun) {
                    continue;
                }

                $existing = Note::query()
                    ->where('import_fingerprint', $payload['import_fingerprint'])
                    ->first();

                if ($existing) {
                    $existing->fill($payload)->save();
                    $summary['updated_count']++;

                    continue;
                }

                Note::query()->create($payload);
                $summary['created_count']++;
            }

            if ($dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (\Throwable $throwable) {
            DB::rollBack();

            throw $throwable;
        }

        $summary['error_count'] = count($summary['errors']);

        return $summary;
    }

    /**
     * @return array<int, array{line: int, data: array<string, string>}>
     */
    private function readCsvRows(string $path): array
    {
        $handle = fopen($path, 'rb');

        if (! $handle) {
            throw new InvalidArgumentException('Unable to open CSV file.');
        }

        $headerRow = fgetcsv($handle);

        if (! is_array($headerRow)) {
            fclose($handle);
            throw new InvalidArgumentException('CSV file is missing a header row.');
        }

        $headers = array_map(fn ($header) => $this->normalizeHeader((string) $header), $headerRow);
        $rows = [];
        $line = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $line++;

            if (! is_array($row)) {
                continue;
            }

            $values = array_pad($row, count($headers), '');
            $rows[] = [
                'line' => $line,
                'data' => array_combine($headers, array_map(fn ($value) => trim((string) $value), $values)),
            ];
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row, User $user, string $importBatch): array
    {
        $owner = $this->cleanString($this->value($row, 'owner'));
        $weekLabel = Str::upper($this->cleanString($this->value($row, 'week')) ?? '');
        $task = $this->cleanString($this->value($row, 'task'));

        if (! $owner || ! $weekLabel || ! $task) {
            throw new InvalidArgumentException('Owner, week, and task are required.');
        }

        $sourceStatus = $this->normalizeWorkloadStatus(
            $this->value($row, 'status_not_started_in_progress_done_blocked'),
            $this->value($row, 'complete')
        );

        $progress = $this->parseProgress($this->value($row, 'complete'), $sourceStatus);
        $weekNumber = $this->parseWeekNumber($weekLabel);
        $sourceDate = $this->parseDate($this->value($row, 'date_added'));
        $category = $this->cleanString($this->value($row, 'category_planned_ad_hoc'));
        $linkedGoal = $this->cleanString($this->value($row, 'linked_goal'));
        $priority = Str::upper($this->cleanString($this->value($row, 'priority_h_m_l')) ?? '');
        $comments = $this->cleanString($this->value($row, 'comments_blockers'));
        $replacedTask = $this->cleanString($this->value($row, 'replaced_task_if_ad_hoc'));

        return [
            'user_id' => $user->id,
            'project_id' => null,
            'title' => Str::limit($task, 255, ''),
            'content' => $comments ?? '',
            'clipboard_text' => null,
            'status' => $this->mapNoteStatus($sourceStatus, $progress),
            'progress' => $progress,
            'attachments' => [],
            'scope' => Note::SCOPE_WORKLOAD_2025,
            'work_year' => 2025,
            'work_week_label' => $weekLabel,
            'work_week_number' => $weekNumber,
            'owner_name_raw' => $owner,
            'category' => $category,
            'linked_goal' => $linkedGoal,
            'priority_code' => $priority !== '' ? $priority : null,
            'workload_status' => $sourceStatus,
            'estimated_time_hours' => $this->parseHours($this->value($row, 'estimated_time_h')),
            'moved_to_next_week' => $this->parseYesNo($this->value($row, 'moved_to_next_week_y_n')),
            'replaced_task' => $replacedTask,
            'source_date_added' => $sourceDate,
            'import_batch' => $importBatch,
            'import_fingerprint' => sha1(implode('|', [
                Note::SCOPE_WORKLOAD_2025,
                '2025',
                $owner,
                $weekLabel,
                $task,
                $linkedGoal ?? '',
                $sourceDate?->format('Y-m-d') ?? '',
            ])),
        ];
    }

    private function normalizeHeader(string $header): string
    {
        return Str::of($header)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->toString();
    }

    /**
     * @param  array<string, string>  $row
     */
    private function isEmptyRow(array $row): bool
    {
        return collect($row)
            ->filter(fn ($value) => trim($value) !== '')
            ->isEmpty();
    }

    /**
     * @param  array<string, string>  $row
     */
    private function value(array $row, string $key): ?string
    {
        $value = $row[$key] ?? null;

        return $value !== null ? trim($value) : null;
    }

    private function cleanString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $cleaned = preg_replace('/\s+/', ' ', trim($value));

        return $cleaned !== '' ? $cleaned : null;
    }

    private function normalizeWorkloadStatus(?string $value, ?string $progress): string
    {
        $normalized = Str::of($value ?? '')
            ->lower()
            ->replace([' ', '-'], '_')
            ->trim()
            ->value();

        if (in_array($normalized, ['done', 'completed', 'finished'], true)) {
            return 'done';
        }

        if ($normalized === 'blocked') {
            return 'blocked';
        }

        if (in_array($normalized, ['in_progress', 'progress'], true)) {
            return 'in_progress';
        }

        if (in_array($normalized, ['not_started', 'notstarted', 'todo'], true)) {
            return 'not_started';
        }

        $numericProgress = $this->parseProgress($progress, 'not_started');

        if ($numericProgress >= 100) {
            return 'done';
        }

        if ($numericProgress > 0) {
            return 'in_progress';
        }

        return 'not_started';
    }

    private function mapNoteStatus(string $workloadStatus, int $progress): string
    {
        if ($workloadStatus === 'done' || $progress >= 100) {
            return Note::STATUS_DONE;
        }

        if (in_array($workloadStatus, ['in_progress', 'blocked'], true) || $progress > 0) {
            return Note::STATUS_IN_PROGRESS;
        }

        return Note::STATUS_TODO;
    }

    private function parseProgress(?string $value, string $fallbackStatus): int
    {
        $normalized = trim((string) $value);

        if ($normalized !== '') {
            $number = (int) preg_replace('/[^0-9]/', '', $normalized);

            return max(0, min(100, $number));
        }

        return match ($fallbackStatus) {
            'done' => 100,
            'in_progress', 'blocked' => 50,
            default => 0,
        };
    }

    private function parseWeekNumber(string $weekLabel): int
    {
        if (preg_match('/(\d{1,2})/', $weekLabel, $matches) !== 1) {
            throw new InvalidArgumentException('Week is invalid.');
        }

        return max(1, min(53, (int) $matches[1]));
    }

    private function parseHours(?string $value): ?float
    {
        $normalized = trim((string) $value);

        if ($normalized === '') {
            return null;
        }

        return round((float) str_replace(',', '.', $normalized), 2);
    }

    private function parseYesNo(?string $value): bool
    {
        return Str::upper(trim((string) $value)) === 'Y';
    }

    private function parseDate(?string $value): ?CarbonImmutable
    {
        $normalized = trim((string) $value);

        if ($normalized === '') {
            return null;
        }

        foreach (['Y-m-d', 'm/d/Y', 'n/j/Y'] as $format) {
            try {
                return CarbonImmutable::createFromFormat($format, $normalized)->startOfDay();
            } catch (\Throwable) {
                continue;
            }
        }

        try {
            return CarbonImmutable::parse($normalized)->startOfDay();
        } catch (\Throwable) {
            throw new InvalidArgumentException('Date Added is invalid.');
        }
    }
}
