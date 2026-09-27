<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Removes image files whose screenshot rows are already gone.
 *
 * Deleting an API key cascades to its screenshot rows in one statement, but
 * the files are one storage call each — on S3, one DELETE request per file —
 * so they're cleared here rather than inside the admin's request.
 */
class DeleteStoredScreenshots implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  list<string>  $paths
     */
    public function __construct(
        public string $disk,
        public array $paths,
    ) {}

    public function handle(): void
    {
        Storage::disk($this->disk)->delete($this->paths);
    }
}
