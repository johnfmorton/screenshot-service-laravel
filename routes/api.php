<?php

use App\Http\Controllers\ApiDocumentationController;
use App\Http\Controllers\ScreenshotController;
use App\Http\Middleware\ValidateApiKey;
use Illuminate\Support\Facades\Route;

// API Documentation (no auth required)
Route::get('/', [ApiDocumentationController::class, 'index'])->name('api.docs');
Route::get('/screenshots', [ApiDocumentationController::class, 'index']);

Route::middleware(ValidateApiKey::class)->group(function () {
    Route::post('/screenshots', [ScreenshotController::class, 'store'])->name('screenshots.store');
    Route::get('/screenshots/{screenshot}', [ScreenshotController::class, 'show'])->name('screenshots.show');
    Route::delete('/screenshots/{screenshot}', [ScreenshotController::class, 'destroy'])->name('screenshots.destroy');
});
