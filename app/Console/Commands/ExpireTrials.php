<?php

namespace App\Console\Commands;

use App\Models\Store;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpireTrials extends Command
{
    protected $signature = 'stores:expire-trials';

    protected $description = 'Suspend stores with expired trials that have no active subscription';

    public function handle(): int
    {
        $this->info('Checking for expired trials...');

        $expiredStores = Store::where('trial_used', true)
            ->where('trial_ends_at', '<', now())
            ->where('status', Store::STATUS_ACTIVE)
            ->whereDoesntHave('activeSubscription')
            ->get();

        $count = 0;

        foreach ($expiredStores as $store) {
            $store->update([
                'status' => Store::STATUS_SUSPENDED,
                'is_active' => false,
            ]);

            Log::info('Store suspended due to expired trial', [
                'store_id' => $store->id,
                'store_name' => $store->name,
                'owner_id' => $store->owner_id,
                'trial_ended_at' => $store->trial_ends_at,
            ]);

            $count++;
        }

        $this->info("Suspended {$count} stores with expired trials.");

        return Command::SUCCESS;
    }
}
