<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ScreenshotStatus;
use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\Screenshot;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();

        if ($user->isSuperAdmin()) {
            $stats = $this->getSuperAdminStats();
        } else {
            $stats = $this->getUserStats($user);
        }

        return view('admin.dashboard', compact('stats'));
    }

    private function getSuperAdminStats(): array
    {
        return [
            'total_screenshots' => Screenshot::count(),
            'active_screenshots' => Screenshot::where('status', ScreenshotStatus::Completed)
                ->where('expires_at', '>', Carbon::now())
                ->count(),
            'pending_screenshots' => Screenshot::where('status', ScreenshotStatus::Pending)->count(),
            'failed_screenshots' => Screenshot::where('status', ScreenshotStatus::Failed)->count(),
            'screenshots_today' => Screenshot::whereDate('created_at', Carbon::today())->count(),
            'total_api_keys' => ApiKey::count(),
            'active_api_keys' => ApiKey::where('is_active', true)->count(),
            'total_users' => User::count(),
            'recent_screenshots' => Screenshot::with('apiKey')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(),
        ];
    }

    private function getUserStats(User $user): array
    {
        $apiKeyIds = $user->apiKeys()->pluck('id');

        return [
            'total_screenshots' => Screenshot::whereIn('api_key_id', $apiKeyIds)->count(),
            'active_screenshots' => Screenshot::whereIn('api_key_id', $apiKeyIds)
                ->where('status', ScreenshotStatus::Completed)
                ->where('expires_at', '>', Carbon::now())
                ->count(),
            'pending_screenshots' => Screenshot::whereIn('api_key_id', $apiKeyIds)
                ->where('status', ScreenshotStatus::Pending)
                ->count(),
            'failed_screenshots' => Screenshot::whereIn('api_key_id', $apiKeyIds)
                ->where('status', ScreenshotStatus::Failed)
                ->count(),
            'screenshots_today' => Screenshot::whereIn('api_key_id', $apiKeyIds)
                ->whereDate('created_at', Carbon::today())
                ->count(),
            'total_api_keys' => $user->apiKeys()->count(),
            'active_api_keys' => $user->apiKeys()->where('is_active', true)->count(),
            'total_users' => null,
            'recent_screenshots' => Screenshot::with('apiKey')
                ->whereIn('api_key_id', $apiKeyIds)
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(),
        ];
    }
}
