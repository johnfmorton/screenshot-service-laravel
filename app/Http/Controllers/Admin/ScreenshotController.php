<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ScreenshotStatus;
use App\Http\Controllers\Controller;
use App\Models\Screenshot;
use App\Services\ScreenshotService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ScreenshotController extends Controller
{
    public function __construct(private ScreenshotService $screenshotService)
    {
    }

    public function index(Request $request): View
    {
        $user = Auth::user();

        $query = Screenshot::with('apiKey');

        // Scope to user's API keys if not super admin
        if (!$user->isSuperAdmin()) {
            $apiKeyIds = $user->apiKeys()->pluck('api_keys.id');
            $query->whereIn('api_key_id', $apiKeyIds);
        }

        // Apply filters
        if ($request->filled('status')) {
            $status = ScreenshotStatus::tryFrom($request->input('status'));
            if ($status) {
                $query->where('status', $status);
            }
        }

        if ($request->filled('api_key')) {
            $query->where('api_key_id', $request->input('api_key'));
        }

        if ($request->filled('search')) {
            $query->where('url', 'like', '%' . $request->input('search') . '%');
        }

        $screenshots = $query->orderBy('created_at', 'desc')->paginate(20);

        // Get API keys for filter dropdown
        if ($user->isSuperAdmin()) {
            $apiKeys = \App\Models\ApiKey::orderBy('name')->get();
        } else {
            $apiKeys = $user->apiKeys()->orderBy('name')->get();
        }

        return view('admin.screenshots.index', compact('screenshots', 'apiKeys'));
    }

    public function destroy(Screenshot $screenshot): RedirectResponse
    {
        $user = Auth::user();

        // Check authorization
        if (!$user->isSuperAdmin()) {
            $userApiKeyIds = $user->apiKeys()->pluck('api_keys.id')->toArray();
            if (!in_array($screenshot->api_key_id, $userApiKeyIds)) {
                abort(403, 'Unauthorized');
            }
        }

        $this->screenshotService->deleteScreenshot($screenshot);

        return redirect()->route('admin.screenshots.index')
            ->with('success', 'Screenshot deleted successfully.');
    }
}
