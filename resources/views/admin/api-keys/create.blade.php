<x-admin.layout title="Create API Key" description="Generate a new API key for accessing the screenshot service">
    <div class="card" style="max-width: 600px;">
        <div class="card-body">
            <form action="{{ route('admin.api-keys.store') }}" method="POST">
                @csrf

                <div class="form-group">
                    <label class="form-label" for="name">Key Name</label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        class="form-input"
                        value="{{ old('name') }}"
                        placeholder="e.g., Production App, Development"
                        required
                    >
                    <p class="form-hint">A descriptive name to identify this API key.</p>
                </div>

                <div class="form-group">
                    <label class="form-label" for="rate_limit">Rate Limit (requests per hour)</label>
                    <input
                        type="number"
                        id="rate_limit"
                        name="rate_limit"
                        class="form-input"
                        value="{{ old('rate_limit') }}"
                        placeholder="Leave empty for unlimited"
                        min="1"
                    >
                    <p class="form-hint">Optional. Limit the number of requests this key can make per hour.</p>
                </div>

                @if(auth()->user()->isSuperAdmin() && $users->isNotEmpty())
                    <div class="form-group">
                        <label class="form-label">Assign to Users</label>
                        <div class="checkbox-list">
                            @foreach($users as $user)
                                <label>
                                    <input
                                        type="checkbox"
                                        name="user_ids[]"
                                        value="{{ $user->id }}"
                                        {{ in_array($user->id, old('user_ids', [])) ? 'checked' : '' }}
                                    >
                                    {{ $user->name }} ({{ $user->email }})
                                    @if($user->is_super_admin)
                                        <x-admin.badge type="info">Super Admin</x-admin.badge>
                                    @endif
                                </label>
                            @endforeach
                        </div>
                        <p class="form-hint">Select users who should have access to this API key.</p>
                    </div>
                @endif

                <div style="display: flex; gap: 12px; margin-top: 24px;">
                    <button type="submit" class="btn btn-primary">Create API Key</button>
                    <a href="{{ route('admin.api-keys.index') }}" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-admin.layout>
