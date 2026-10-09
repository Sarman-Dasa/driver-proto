<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Plan;
use App\Services\Stripe\StripeBillingService;
use Illuminate\Console\Command;

class SyncStripePlansCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'billflow:sync-stripe-plans';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize BillFlow paid plans with products and prices in Stripe';

    /**
     * Execute the console command.
     */
    public function handle(StripeBillingService $billingService): int
    {
        $this->info('Starting synchronization of plans with Stripe...');

        $plans = Plan::where('price_monthly', '>', 0)
            ->where('is_active', true)
            ->get();

        if ($plans->isEmpty()) {
            $this->warn('No paid plans found to synchronize.');

            return self::SUCCESS;
        }

        foreach ($plans as $plan) {
            $this->line("Processing plan: {$plan->name} ({$plan->slug})...");

            try {
                $monthlyPriceId = $billingService->provisionStripePrice($plan, 'monthly');
                $this->info("  [✓] Monthly Price: {$monthlyPriceId}");

                $yearlyPriceId = $billingService->provisionStripePrice($plan, 'yearly');
                $this->info("  [✓] Yearly Price:  {$yearlyPriceId}");
            } catch (\Throwable $e) {
                $this->error("  [✗] Failed to sync {$plan->name}: {$e->getMessage()}");

                return self::FAILURE;
            }
        }

        $this->info('All paid plans have been successfully synchronized with Stripe!');

        return self::SUCCESS;
    }
}
