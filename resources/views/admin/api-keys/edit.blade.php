<x-admin.layout title="Edit API Key" description="Update rate limit for this API key">
    <div class="card" style="max-width: 600px;">
        <div class="card-body">
            <div class="form-group" style="margin-bottom: 24px;">
                <label class="form-label">Key Name</label>
                <p style="color: var(--text-primary); font-weight: 500;">{{ $apiKey->name }}</p>
            </div>

            <div class="form-group" style="margin-bottom: 24px;">
                <label class="form-label">API Key</label>
                <code class="key-display" style="font-size: 0.75rem;">{{ $apiKey->key }}</code>
            </div>

            <form action="{{ route('admin.api-keys.update', $apiKey) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="form-group">
                    <label class="form-label" for="rate_limit">Rate Limit (requests per hour)</label>
                    <input
                        type="number"
                        id="rate_limit"
                        name="rate_limit"
                        class="form-input"
                        value="{{ old('rate_limit', $apiKey->rate_limit) }}"
                        placeholder="Leave empty for unlimited"
                        min="1"
                    >
                    <p class="form-hint">Optional. Limit the number of requests this key can make per hour.</p>
                </div>

                <div style="display: flex; gap: 12px; margin-top: 24px;">
                    <button type="submit" class="btn btn-primary">Update Rate Limit</button>
                    <a href="{{ route('admin.api-keys.index') }}" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-admin.layout>
