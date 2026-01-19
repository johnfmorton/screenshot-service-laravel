<?php

namespace App\Console\Commands;

use App\Models\Screenshot;
use App\Services\ScreenshotService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ScreenshotCleanup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'screenshot:cleanup
                            {--dry-run : Preview deletions without executing}
                            {--orphaned : Also find orphaned files in storage}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up expired screenshots and optionally orphaned files';

    public function __construct(private ScreenshotService $screenshotService)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $checkOrphaned = $this->option('orphaned');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        $expiredCount = $this->cleanupExpiredScreenshots($dryRun);
        $orphanedCount = 0;

        if ($checkOrphaned) {
            $orphanedCount = $this->cleanupOrphanedFiles($dryRun);
        }

        $this->newLine();
        $this->info('Cleanup Summary:');
        $this->line("  Expired screenshots: {$expiredCount}");

        if ($checkOrphaned) {
            $this->line("  Orphaned files: {$orphanedCount}");
        }

        if ($dryRun) {
            $this->newLine();
            $this->warn('Run without --dry-run to actually delete these items.');
        }

        return self::SUCCESS;
    }

    private function cleanupExpiredScreenshots(bool $dryRun): int
    {
        $expired = Screenshot::where('expires_at', '<', Carbon::now())->get();

        if ($expired->isEmpty()) {
            $this->info('No expired screenshots found.');
            return 0;
        }

        $this->info("Found {$expired->count()} expired screenshot(s)");

        $bar = $this->output->createProgressBar($expired->count());
        $bar->start();

        foreach ($expired as $screenshot) {
            if (!$dryRun) {
                $this->screenshotService->deleteScreenshot($screenshot);
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        return $expired->count();
    }

    private function cleanupOrphanedFiles(bool $dryRun): int
    {
        $disk = config('screenshot.storage_disk');
        $storage = Storage::disk($disk);

        $allFiles = $storage->allFiles('screenshots');

        if (empty($allFiles)) {
            $this->info('No files found in storage.');
            return 0;
        }

        $this->info("Scanning {$storage->path('')} for orphaned files...");

        $dbPaths = Screenshot::whereNotNull('full_image_path')
            ->pluck('full_image_path')
            ->merge(Screenshot::whereNotNull('thumbnail_path')->pluck('thumbnail_path'))
            ->toArray();

        $orphaned = array_diff($allFiles, $dbPaths);

        if (empty($orphaned)) {
            $this->info('No orphaned files found.');
            return 0;
        }

        $this->info("Found " . count($orphaned) . " orphaned file(s)");

        if ($this->option('verbose')) {
            foreach ($orphaned as $file) {
                $this->line("  - {$file}");
            }
        }

        if (!$dryRun) {
            $bar = $this->output->createProgressBar(count($orphaned));
            $bar->start();

            foreach ($orphaned as $file) {
                $storage->delete($file);
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
        }

        return count($orphaned);
    }
}
