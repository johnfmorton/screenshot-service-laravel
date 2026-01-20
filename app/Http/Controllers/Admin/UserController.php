<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct()
    {
        // Only super admin can access user management
    }

    public function index(): View
    {
        $this->authorizeSuperAdmin();

        $users = User::withCount('apiKeys')->orderBy('created_at', 'desc')->get();

        return view('admin.users.index', compact('users'));
    }

    public function create(): View
    {
        $this->authorizeSuperAdmin();

        $apiKeys = ApiKey::orderBy('name')->get();

        return view('admin.users.create', compact('apiKeys'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeSuperAdmin();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'is_super_admin' => ['boolean'],
            'api_key_ids' => ['nullable', 'array'],
            'api_key_ids.*' => ['exists:api_keys,id'],
            'create_api_key' => ['boolean'],
            'new_api_key_name' => ['required_if:create_api_key,1', 'nullable', 'string', 'max:255'],
            'new_api_key_rate_limit' => ['nullable', 'integer', 'min:1'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'is_super_admin' => $validated['is_super_admin'] ?? false,
        ]);

        if (!empty($validated['api_key_ids'])) {
            $user->apiKeys()->attach($validated['api_key_ids']);
        }

        $newKey = null;
        if (!empty($validated['create_api_key']) && !empty($validated['new_api_key_name'])) {
            $apiKey = ApiKey::generate(
                $validated['new_api_key_name'],
                $validated['new_api_key_rate_limit'] ?? null
            );
            $user->apiKeys()->attach($apiKey->id);
            $newKey = $apiKey->key;
        }

        $redirect = redirect()->route('admin.users.index')
            ->with('success', 'User created successfully.');

        if ($newKey) {
            $redirect->with('new_key', $newKey);
        }

        return $redirect;
    }

    public function toggle(User $user): RedirectResponse
    {
        $this->authorizeSuperAdmin();

        // Prevent toggling own account
        if ($user->id === Auth::id()) {
            return redirect()->route('admin.users.index')
                ->with('error', 'You cannot modify your own account.');
        }

        $user->update(['is_super_admin' => !$user->is_super_admin]);

        $status = $user->is_super_admin ? 'promoted to super admin' : 'demoted to regular user';

        return redirect()->route('admin.users.index')
            ->with('success', "User {$status}.");
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorizeSuperAdmin();

        // Prevent deleting own account
        if ($user->id === Auth::id()) {
            return redirect()->route('admin.users.index')
                ->with('error', 'You cannot delete your own account.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', 'User deleted successfully.');
    }

    private function authorizeSuperAdmin(): void
    {
        if (!Auth::user()->isSuperAdmin()) {
            abort(403, 'Only super admins can access user management.');
        }
    }
}
