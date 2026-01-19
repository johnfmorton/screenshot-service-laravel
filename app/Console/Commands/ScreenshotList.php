<?php

namespace App\Console\Commands;

use App\Enums\ScreenshotStatus;
use App\Models\Screenshot;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ScreenshotList extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'screenshot:list
                            {--status= : Filter by status (pending, processing, completed, failed)}
                            {--api-key= : Filter by API key ID}
                            {--expired : Show only expired screenshots}
                            {--limit=50 : Maximum results}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List screenshots with optional filters';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $query = Screenshot::with('apiKey');

        if ($status = $this->option('status')) {
            $statusEnum = ScreenshotStatus::tryFrom($status);
            if (!$statusEnum) {
                $this->error("Invalid status: {$status}. Valid values: pending, processing, completed, failed");
                return self::FAILURE;
            }
            $query->where('status', $statusEnum);
        }

        if ($apiKeyId = $this->option('api-key')) {
            $query->where('api_key_id', $apiKeyId);
        }

        if ($this->option('expired')) {
            $query->where('expires_at', '<', Carbon::now());
        }

        $limit = (int) $this->option('limit');
        $screenshots = $query->orderBy('created_at', 'desc')->limit($limit)->get();

        if ($screenshots->isEmpty()) {
            $this->info('No screenshots found matching the criteria.');
            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'URL', 'Status', 'API Key', 'Captured At', 'Expires At'],
            $screenshots->map(function (Screenshot $screenshot) {
                return [
                    $screenshot->id,
                    $this->truncateUrl($screenshot->url, 40),
                    $screenshot->status->value,
                    $screenshot->apiKey?->name ?? 'N/A',
                    $screenshot->captured_at?->format('Y-m-d H:i') ?? '-',
                    $screenshot->expires_at?->format('Y-m-d H:i') ?? '-',
                ];
            })
        );

        $this->newLine();
        $this->info("Showing {$screenshots->count()} screenshot(s)");

        return self::SUCCESS;
    }

    private function truncateUrl(string $url, int $maxLength): string
    {
        if (strlen($url) <= $maxLength) {
            return $url;
        }

        return substr($url, 0, $maxLength - 3) . '...';
    }
}
