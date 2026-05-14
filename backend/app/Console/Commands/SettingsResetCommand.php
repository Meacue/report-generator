<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class SettingsResetCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'settings:reset {--force : Skip confirmation prompt}';

    /**
     * @var string
     */
    protected $description = 'Clear encrypted credentials (gitlab_token, bitrix24_api_key, llm_api_key) from settings';

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        if (! $force && ! $this->confirm('Reset encrypted credentials (gitlab_token, bitrix24_api_key, llm_api_key)?')) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        $affected = DB::table('settings')->update([
            'gitlab_token'     => null,
            'bitrix24_api_key' => null,
            'llm_api_key'      => null,
        ]);

        $this->info("Cleared encrypted credentials in {$affected} settings row(s).");

        return self::SUCCESS;
    }
}
