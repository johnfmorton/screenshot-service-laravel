<?php

use App\Http\Controllers\Admin\ApiKeyController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\InstallationCheckController;
use App\Http\Controllers\Admin\ScreenshotController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

// Guest routes (unauthenticated)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('admin.login');
    Route::post('/login', [AuthController::class, 'login'])->name('admin.login.submit');
});

// Authenticated admin routes
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('admin.logout');

    Route::get('/', [DashboardController::class, 'index'])->name('admin.dashboard');

    // Screenshots
    Route::get('/screenshots', [ScreenshotController::class, 'index'])->name('admin.screenshots.index');
    Route::delete('/screenshots/{screenshot}', [ScreenshotController::class, 'destroy'])->name('admin.screenshots.destroy');

    // API Keys
    Route::get('/api-keys', [ApiKeyController::class, 'index'])->name('admin.api-keys.index');
    Route::get('/api-keys/create', [ApiKeyController::class, 'create'])->name('admin.api-keys.create');
    Route::post('/api-keys', [ApiKeyController::class, 'store'])->name('admin.api-keys.store');
    Route::get('/api-keys/{apiKey}/edit', [ApiKeyController::class, 'edit'])->name('admin.api-keys.edit');
    Route::put('/api-keys/{apiKey}', [ApiKeyController::class, 'update'])->name('admin.api-keys.update');
    Route::post('/api-keys/{apiKey}/toggle', [ApiKeyController::class, 'toggle'])->name('admin.api-keys.toggle');
    Route::delete('/api-keys/{apiKey}', [ApiKeyController::class, 'destroy'])->name('admin.api-keys.destroy');

    // Users (super admin only)
    Route::get('/users', [UserController::class, 'index'])->name('admin.users.index');
    Route::get('/users/create', [UserController::class, 'create'])->name('admin.users.create');
    Route::post('/users', [UserController::class, 'store'])->name('admin.users.store');
    Route::post('/users/{user}/toggle', [UserController::class, 'toggle'])->name('admin.users.toggle');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('admin.users.destroy');

    // Installation Check
    Route::get('/installation-check', [InstallationCheckController::class, 'index'])
        ->name('admin.installation-check');
    Route::post('/installation-check/test', [InstallationCheckController::class, 'runTest'])
        ->name('admin.installation-check.test');
    Route::get('/installation-check/status/{screenshot}', [InstallationCheckController::class, 'checkStatus'])
        ->name('admin.installation-check.status');

    // Settings
    Route::get('/settings', [SettingsController::class, 'index'])->name('admin.settings');
});
