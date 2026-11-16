<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:audit-prune'
    .' {--keep=30 : Number of days of audit history to keep}'
    .' {--dry-run : Count the entries without deleting them}')]
#[Description('Prune audit log entries older than the keep window')]
class AuditPrune extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $keep = filter_var($this->option('keep'), FILTER_VALIDATE_INT);

        if (! \is_int($keep) || $keep < 1) {
            $this->error('The --keep option must be a whole number of days greater than zero.');

            return self::FAILURE;
        }

        $cutoff = now()->subDays($keep);

        $query = AuditLog::query()->where('created_at', '<', $cutoff);

        if ((bool) $this->option('dry-run')) {
            $this->info("{$query->count()} audit log entries are older than {$keep} days.");

            return self::SUCCESS;
        }

        $deleted = $query->delete();

        $this->info("Deleted {$deleted} audit log entries older than {$keep} days.");

        return self::SUCCESS;
    }
}
