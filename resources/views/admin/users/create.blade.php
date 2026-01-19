<x-admin.layout title="Create User" description="Add a new admin user">
    <div class="card" style="max-width: 600px;">
        <div class="card-body">
            <form action="{{ route('admin.users.store') }}" method="POST">
                @csrf

                <div class="form-group">
                    <label class="form-label" for="name">Name</label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        class="form-input"
                        value="{{ old('name') }}"
                        placeholder="John Doe"
                        required
                    >
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="form-input"
                        value="{{ old('email') }}"
                        placeholder="john@example.com"
                        required
                    >
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-input"
                        placeholder="Minimum 8 characters"
                        required
                    >
                </div>

                <div class="form-group">
                    <label class="form-label" for="password_confirmation">Confirm Password</label>
                    <input
                        type="password"
                        id="password_confirmation"
                        name="password_confirmation"
                        class="form-input"
                        placeholder="Re-enter password"
                        required
                    >
                </div>

                <div class="form-group">
                    <label class="form-checkbox">
                        <input
                            type="checkbox"
                            name="is_super_admin"
                            value="1"
                            {{ old('is_super_admin') ? 'checked' : '' }}
                        >
                        <span>
                            <strong>Super Admin</strong>
                            <br>
                            <span style="color: var(--text-muted); font-size: 0.8125rem;">
                                Can manage all users, API keys, and screenshots
                            </span>
                        </span>
                    </label>
                </div>

                @if($apiKeys->isNotEmpty())
                    <div class="form-group">
                        <label class="form-label">Assign API Keys</label>
                        <div class="checkbox-list">
                            @foreach($apiKeys as $apiKey)
                                <label>
                                    <input
                                        type="checkbox"
                                        name="api_key_ids[]"
                                        value="{{ $apiKey->id }}"
                                        {{ in_array($apiKey->id, old('api_key_ids', [])) ? 'checked' : '' }}
                                    >
                                    {{ $apiKey->name }}
                                    @if(!$apiKey->is_active)
                                        <x-admin.badge type="danger">Inactive</x-admin.badge>
                                    @endif
                                </label>
                            @endforeach
                        </div>
                        <p class="form-hint">Select API keys this user should have access to.</p>
                    </div>
                @endif

                <div style="display: flex; gap: 12px; margin-top: 24px;">
                    <button type="submit" class="btn btn-primary">Create User</button>
                    <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-admin.layout>
