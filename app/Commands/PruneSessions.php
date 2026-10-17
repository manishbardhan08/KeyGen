<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Prunes expired API sessions.
 *
 * api_sessions rows are only removed opportunistically on validate() today,
 * which means abandoned sessions accumulate forever. Run this on a schedule:
 *
 *   php spark sessions:prune
 *
 * Suggested cron (daily):
 *   0 3 * * * cd /path/to/app && php spark sessions:prune >> writable/logs/prune.log 2>&1
 */
class PruneSessions extends BaseCommand
{
    protected $group       = 'KeyVault';
    protected $name        = 'sessions:prune';
    protected $description = 'Delete expired API session tokens.';
    protected $usage       = 'sessions:prune [--grace <seconds>]';
    protected $options     = [
        '--grace' => 'Extra seconds past expiry to keep (default 3600).',
    ];

    public function run(array $params)
    {
        $grace = (int) (CLI::getOption('grace') ?? 3600);
        $cutoff = date('Y-m-d H:i:s', time() - $grace);

        $db = db_connect();
        $deleted = $db->table('api_sessions')
            ->where('expires_at IS NOT NULL')
            ->where('expires_at <', $cutoff)
            ->delete();

        CLI::write("Pruned {$deleted} expired API session(s).", 'green');
        log_message('info', "sessions:prune removed {$deleted} expired session(s).");
    }
}