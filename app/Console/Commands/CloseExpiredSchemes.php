<?php

namespace App\Console\Commands;

use App\Models\Scheme;
use Illuminate\Console\Command;

class CloseExpiredSchemes extends Command
{
    protected $signature = 'schemes:close-expired';

    protected $description = 'Mark active running schemes as closed when their application deadline has passed';

    public function handle(): int
    {
        $count = Scheme::query()
            ->where('status', 'active')
            ->where('type', 'running')
            ->whereNotNull('last_date')
            ->whereDate('last_date', '<', now()->toDateString())
            ->update(['status' => 'closed']);

        $this->info("Closed {$count} expired scheme(s).");

        return self::SUCCESS;
    }
}
