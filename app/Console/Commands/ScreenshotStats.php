<?php

namespace App\Console\Commands;

use App\Enums\ScreenshotStatus;
use App\Models\ApiKey;
use App\Models\Screenshot;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ScreenshotStats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'screenshot:stats';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display screenshot statistics';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->displaySummary();
        $this->newLine();
        $this->displayStatusCounts();
        $this->newLine();
        $this->displayApiKeyBreakdown();

        return self::SUCCESS;
    }

    private function displaySummary(): void
    {
        $this->info('=== Screenshot Summary ===');
        $this->newLine();

        $total = Screenshot::count();
        $expired = Screenshot::where('expires_at', '<', Carbon::now())->count();
        $active = Screenshot::where('status', ScreenshotStatus::Completed)
            ->where('expires_at', '>', Carbon::now())
            ->count();
        $today = Screenshot::whereDate('created_at', Carbon::today())->count();
        $thisWeek = Screenshot::where('created_at', '>=', Carbon::now()->startOfWeek())->count();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Screenshots', $total],
                ['Active (Completed & Not Expired)', $active],
                ['Expired', $expired],
                ['Created Today', $today],
                ['Created This Week', $thisWeek],
            ]
        );
    }

    private function displayStatusCounts(): void
    {
        $this->info('=== Status Breakdown ===');
        $this->newLine();

        $statusCounts = [];
        foreach (ScreenshotStatus::cases() as $status) {
            $count = Screenshot::where('status', $status)->count();
            $statusCounts[] = [ucfirst($status->value), $count];
        }

        $this->table(['Status', 'Count'], $statusCounts);
    }

    private function displayApiKeyBreakdown(): void
    {
        $this->info('=== Per-API Key Breakdown ===');
        $this->newLine();

        $apiKeys = ApiKey::withCount('screenshots')->get();

        if ($apiKeys->isEmpty()) {
            $this->line('No API keys found.');
            return;
        }

        $rows = $apiKeys->map(function (ApiKey $apiKey) {
            $activeCount = Screenshot::where('api_key_id', $apiKey->id)
                ->where('status', ScreenshotStatus::Completed)
                ->where('expires_at', '>', Carbon::now())
                ->count();

            $failedCount = Screenshot::where('api_key_id', $apiKey->id)
                ->where('status', ScreenshotStatus::Failed)
                ->count();

            return [
                $apiKey->name,
                $apiKey->is_active ? 'Active' : 'Inactive',
                $apiKey->screenshots_count,
                $activeCount,
                $failedCount,
            ];
        });

        $this->table(
            ['API Key', 'Status', 'Total', 'Active', 'Failed'],
            $rows
        );
    }
}
