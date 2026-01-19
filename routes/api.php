<?php

use App\Http\Controllers\ScreenshotController;
use App\Http\Middleware\ValidateApiKey;
use Illuminate\Support\Facades\Route;

Route::middleware(ValidateApiKey::class)->group(function () {
    Route::post('/screenshots', [ScreenshotController::class, 'store'])->name('screenshots.store');
    Route::get('/screenshots/{screenshot}', [ScreenshotController::class, 'show'])->name('screenshots.show');
    Route::delete('/screenshots/{screenshot}', [ScreenshotController::class, 'destroy'])->name('screenshots.destroy');
});
