<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\GitLab\Models\Branch;
use App\Domain\GitLab\Models\Commit;
use App\Domain\Matching\Models\MatchResult;
use App\Domain\Bitrix24\Models\Task;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

final class DataRestoreCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'data:restore
        {--from= : Start date (YYYY-MM-DD)}
        {--to= : End date (YYYY-MM-DD)}
        {--dry-run : Preview without restoring}';

    /**
     * @var string
     */
    protected $description = 'Restore soft-deleted records within a date range';

    public function handle(): int
    {
        $from = $this->option('from');
        $to = $this->option('to');

        if (! is_string($from) || ! is_string($to)) {
            $this->error('Both --from and --to options are required.');

            return self::FAILURE;
        }

        if (! $this->isValidDate($from) || ! $this->isValidDate($to)) {
            $this->error('Invalid date format. Use YYYY-MM-DD.');

            return self::FAILURE;
        }

        $isDryRun = (bool) $this->option('dry-run');

        if ($isDryRun) {
            $this->info('DRY RUN — no changes will be made.');
        }

        $dateRange = [$from . ' 00:00:00', $to . ' 23:59:59'];
        $totalRestored = 0;

        $totalRestored += $this->restoreModel('Branches', Branch::onlyTrashed()->whereBetween('deleted_at', $dateRange), $isDryRun);
        $totalRestored += $this->restoreModel('Commits', Commit::onlyTrashed()->whereBetween('deleted_at', $dateRange), $isDryRun);
        $totalRestored += $this->restoreModel('Tasks', Task::onlyTrashed()->whereBetween('deleted_at', $dateRange), $isDryRun);
        $totalRestored += $this->restoreModel('Match Results', MatchResult::onlyTrashed()->whereBetween('deleted_at', $dateRange), $isDryRun);

        $this->newLine();
        $action = $isDryRun ? 'would be restored' : 'restored';
        $this->info("Total: {$totalRestored} records {$action}");

        return self::SUCCESS;
    }

    /**
     * @param  Builder<Branch>|Builder<Commit>|Builder<Task>|Builder<MatchResult>  $query
     */
    private function restoreModel(string $label, Builder $query, bool $isDryRun): int
    {
        $count = $query->count();

        if ($count > 0 && ! $isDryRun) {
            $query->restore();
        }

        $action = $isDryRun ? 'would be restored' : 'restored';
        $this->line("{$label}: {$count} records {$action}");

        return $count;
    }

    private function isValidDate(string $date): bool
    {
        $parsed = date_parse_from_format('Y-m-d', $date);

        return $parsed['error_count'] === 0 && $parsed['warning_count'] === 0;
    }
}
