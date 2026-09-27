<?php

namespace Tests\Feature;

use App\Jobs\CaptureScreenshot;
use App\Models\Screenshot;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Mockery;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

class CaptureScreenshotStorageTest extends TestCase
{
    public function test_s3_uploads_skip_the_acl_when_served_through_cloudfront(): void
    {
        config(['screenshot.s3_public_acl' => false]);

        $this->assertSame([], $this->uploadOptions('s3'));
    }

    public function test_s3_uploads_are_public_by_default_for_direct_bucket_urls(): void
    {
        config(['screenshot.s3_public_acl' => true]);

        $this->assertSame(['visibility' => 'public'], $this->uploadOptions('s3'));
    }

    /**
     * The local disk is served by the web server, which needs the files
     * readable whatever the S3 setting says.
     */
    public function test_local_uploads_stay_public(): void
    {
        config(['screenshot.s3_public_acl' => false]);

        $this->assertSame(['visibility' => 'public'], $this->uploadOptions('public'));
    }

    /**
     * The S3 disk doesn't throw, so a rejected upload returns false. Treating
     * that as success marked captures completed with URLs that 404.
     */
    public function test_a_rejected_upload_fails_the_capture(): void
    {
        $filesystem = Mockery::mock(Filesystem::class);
        $filesystem->shouldReceive('put')->andReturnFalse();
        Storage::shouldReceive('disk')->with('s3')->andReturn($filesystem);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Could not write');

        $this->store('s3');
    }

    /**
     * @return array<string, string>
     */
    private function uploadOptions(string $disk): array
    {
        $options = null;
        $filesystem = Mockery::mock(Filesystem::class);
        $filesystem->shouldReceive('put')->andReturnUsing(function ($path, $contents, $given) use (&$options) {
            $options = $given;

            return true;
        });
        Storage::shouldReceive('disk')->with($disk)->andReturn($filesystem);

        $this->store($disk);

        return $options;
    }

    private function store(string $disk): void
    {
        $localPath = tempnam(sys_get_temp_dir(), 'shot');
        file_put_contents($localPath, 'png');

        try {
            $job = new CaptureScreenshot(new Screenshot());
            $method = new ReflectionMethod($job, 'store');
            $method->setAccessible(true);
            $method->invoke($job, $disk, 'screenshots/x-full.png', $localPath);
        } finally {
            @unlink($localPath);
        }
    }
}
