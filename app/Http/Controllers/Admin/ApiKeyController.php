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

    public function create(): View|RedirectResponse
    {
        $user = Auth::user();

        // Sub users with existing key can't create another
        if (!$user->isSuperAdmin() && $user->apiKeys()->exists()) {
            return redirect()->route('admin.api-keys.index');
        }

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
            'user_id' => ['nullable', 'exists:users,id'],
        ]);

        // Determine the owner of this API key
        $ownerId = $user->isSuperAdmin() && !empty($validated['user_id'])
            ? $validated['user_id']
            : $user->id;

        // Sub users can only have one API key
        $owner = User::find($ownerId);
        if (!$owner->isSuperAdmin() && $owner->apiKeys()->exists()) {
            return redirect()->route('admin.api-keys.create')
                ->withInput()
                ->with('error', 'Sub users can only have one API key.');
        }

        // Only super admins can set rate limits
        $rateLimit = $user->isSuperAdmin() ? ($validated['rate_limit'] ?? null) : null;

        $apiKey = ApiKey::generate(
            $validated['name'],
            $rateLimit
        );

        // Set the owner
        $apiKey->update(['user_id' => $ownerId]);

        return redirect()->route('admin.api-keys.index')
            ->with('success', 'API key created successfully.')
            ->with('new_key', $apiKey->key);
    }

    public function toggle(ApiKey $apiKey): RedirectResponse
    {
        $user = Auth::user();

        // Check authorization
        if (!$user->isSuperAdmin()) {
            if ($apiKey->user_id !== $user->id) {
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

        // Super admins can delete any key; sub users can delete their own
        if (!$user->isSuperAdmin() && $apiKey->user_id !== $user->id) {
            abort(403, 'Unauthorized');
        }

        $apiKey->delete();

        return redirect()->route('admin.api-keys.index')
            ->with('success', 'API key deleted successfully.');
    }
}
