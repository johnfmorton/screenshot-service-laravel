<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ApiKeyController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();

        if ($user->isSuperAdmin()) {
            $apiKeys = ApiKey::withCount('screenshots')->orderBy('created_at', 'desc')->get();
        } else {
            $apiKeys = $user->apiKeys()->withCount('screenshots')->orderBy('created_at', 'desc')->get();
        }

        return view('admin.api-keys.index', compact('apiKeys'));
    }

    public function create(): View
    {
        $user = Auth::user();

        // Only super admin can assign to users
        $users = $user->isSuperAdmin() ? User::orderBy('name')->get() : collect();

        return view('admin.api-keys.create', compact('users'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'rate_limit' => ['nullable', 'integer', 'min:1'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['exists:users,id'],
        ]);

        $apiKey = ApiKey::generate(
            $validated['name'],
            $validated['rate_limit'] ?? null
        );

        // Attach to users if super admin
        if ($user->isSuperAdmin() && !empty($validated['user_ids'])) {
            $apiKey->users()->attach($validated['user_ids']);
        } else {
            // Regular users: auto-attach to themselves
            $apiKey->users()->attach($user->id);
        }

        return redirect()->route('admin.api-keys.index')
            ->with('success', 'API key created successfully.')
            ->with('new_key', $apiKey->key);
    }

    public function toggle(ApiKey $apiKey): RedirectResponse
    {
        $user = Auth::user();

        // Check authorization
        if (!$user->isSuperAdmin()) {
            if (!$user->apiKeys()->where('api_keys.id', $apiKey->id)->exists()) {
                abort(403, 'Unauthorized');
            }
        }

        $apiKey->update(['is_active' => !$apiKey->is_active]);

        $status = $apiKey->is_active ? 'activated' : 'deactivated';

        return redirect()->route('admin.api-keys.index')
            ->with('success', "API key {$status} successfully.");
    }

    public function destroy(ApiKey $apiKey): RedirectResponse
    {
        $user = Auth::user();

        // Only super admin can delete API keys
        if (!$user->isSuperAdmin()) {
            abort(403, 'Only super admins can delete API keys.');
        }

        $apiKey->delete();

        return redirect()->route('admin.api-keys.index')
            ->with('success', 'API key deleted successfully.');
    }
}
