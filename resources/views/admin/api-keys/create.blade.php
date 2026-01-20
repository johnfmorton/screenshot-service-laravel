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

                @if(auth()->user()->isSuperAdmin())
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
                @endif

                @if(auth()->user()->isSuperAdmin() && $users->isNotEmpty())
                    <div class="form-group">
                        <label class="form-label" for="user_id">Assign to User</label>
                        <select id="user_id" name="user_id" class="form-input">
                            <option value="">-- Select a user --</option>
                            @foreach($users as $user)
                                <option
                                    value="{{ $user->id }}"
                                    {{ old('user_id') == $user->id ? 'selected' : '' }}
                                    @if(!$user->is_super_admin && $user->apiKeys()->exists())
                                        disabled
                                    @endif
                                >
                                    {{ $user->name }} ({{ $user->email }})
                                    @if($user->is_super_admin)
                                        - Super Admin
                                    @elseif($user->apiKeys()->exists())
                                        - Already has a key
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        <p class="form-hint">Each API key belongs to one user. Sub users can only have one key.</p>
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
