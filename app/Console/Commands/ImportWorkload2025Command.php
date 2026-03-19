<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Workload2025ImportService;
use Illuminate\Console\Command;

class ImportWorkload2025Command extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'workload2025:import
                            {file : Absolute or relative path to the CSV file}
                            {user : User id, email, or exact name to own the imported dataset}
                            {--dry-run : Validate and summarize without writing to the database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import the 2025 workload CSV into the existing notes table.';

    /**
     * Execute the console command.
     */
    public function handle(Workload2025ImportService $importService): int
    {
        $user = $this->resolveUser($this->argument('user'));

        if (! $user) {
            $this->error('Unable to resolve the user argument to an existing user.');

            return self::FAILURE;
        }

        $summary = $importService->importFromPath(
            $this->argument('file'),
            $user,
            (bool) $this->option('dry-run')
        );

        $this->info(sprintf(
            'Processed %d rows. Created: %d. Updated: %d. Skipped: %d. Errors: %d.%s',
            $summary['processed_rows'],
            $summary['created_count'],
            $summary['updated_count'],
            $summary['skipped_rows'],
            $summary['error_count'],
            $summary['dry_run'] ? ' Dry run only.' : ''
        ));

        foreach (array_slice($summary['errors'], 0, 20) as $error) {
            $this->warn($error);
        }

        return self::SUCCESS;
    }

    private function resolveUser(string $value): ?User
    {
        return User::query()
            ->whereKey($value)
            ->orWhere('email', $value)
            ->orWhere('name', $value)
            ->first();
    }
}
