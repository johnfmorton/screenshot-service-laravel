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

                <div class="form-group">
                    <label class="form-checkbox">
                        <input
                            type="checkbox"
                            name="create_api_key"
                            id="create_api_key"
                            value="1"
                            {{ old('create_api_key') ? 'checked' : '' }}
                            onchange="document.getElementById('new-api-key-fields').style.display = this.checked ? 'block' : 'none';"
                        >
                        <span>
                            <strong>Create a new API key for this user</strong>
                        </span>
                    </label>
                </div>

                <div id="new-api-key-fields" style="display: {{ old('create_api_key') ? 'block' : 'none' }}; margin-left: 24px; padding-left: 16px; border-left: 2px solid var(--border-color);">
                    <div class="form-group">
                        <label class="form-label" for="new_api_key_name">API Key Name</label>
                        <input
                            type="text"
                            id="new_api_key_name"
                            name="new_api_key_name"
                            class="form-input"
                            value="{{ old('new_api_key_name') }}"
                            placeholder="e.g., Production App, Development"
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="new_api_key_rate_limit">Rate Limit (requests/hour)</label>
                        <input
                            type="number"
                            id="new_api_key_rate_limit"
                            name="new_api_key_rate_limit"
                            class="form-input"
                            value="{{ old('new_api_key_rate_limit') }}"
                            placeholder="Leave empty for unlimited"
                            min="1"
                        >
                    </div>
                </div>

                <div style="display: flex; gap: 12px; margin-top: 24px;">
                    <button type="submit" class="btn btn-primary">Create User</button>
                    <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-admin.layout>
