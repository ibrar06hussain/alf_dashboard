<?php

namespace App\Console\Commands;

use App\Services\StatsAggregationService;
use Illuminate\Console\Command;

class RefreshDepartmentStats extends Command
{
    protected $signature = 'stats:refresh';

    protected $description = 'Refresh auto-aggregated department statistics from applications';

    public function handle(StatsAggregationService $service): int
    {
        $count = $service->refreshAll();
        $this->info("Refreshed {$count} auto statistic(s).");

        return self::SUCCESS;
    }
}
