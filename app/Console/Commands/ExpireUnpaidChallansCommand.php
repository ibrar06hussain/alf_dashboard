<?php

namespace App\Console\Commands;

use App\Services\ApplicationLifecycleService;
use Illuminate\Console\Command;

class ExpireUnpaidChallansCommand extends Command
{
    protected $signature = 'applications:expire-challans';

    protected $description = 'Expire unpaid application challans exceeding the 14 calendar day window into Dormant status';

    public function handle(ApplicationLifecycleService $service): int
    {
        $this->info('Checking for unpaid application challans older than 14 days...');

        $count = $service->expireUnpaidChallans();

        $this->info("Completed. Expired {$count} unpaid application challan(s) to Dormant status.");

        return Command::SUCCESS;
    }
}
