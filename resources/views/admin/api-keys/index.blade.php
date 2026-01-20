<x-admin.layout title="API Keys" description="Manage API keys for accessing the screenshot service">
    @if(session('new_key'))
        <div class="alert alert-success">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                <polyline points="22 4 12 14.01 9 11.01"></polyline>
            </svg>
            <div>
                <strong>API key created successfully!</strong>
                <div class="key-display" style="margin-top: 10px;">{{ session('new_key') }}</div>
            </div>
        </div>
    @endif

    {{-- Create button: show for super admins, or sub users without a key --}}
    @if(auth()->user()->isSuperAdmin() || $apiKeys->isEmpty())
        <div style="margin-bottom: 20px;">
            <a href="{{ route('admin.api-keys.create') }}" class="btn btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                Create API Key
            </a>
        </div>
    @endif

    <div class="card">
        <div class="table-container">
            @if($apiKeys->isEmpty())
                <div class="empty-state">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"></path>
                    </svg>
                    <h3>No API keys yet</h3>
                    <p>Create your first API key to start capturing screenshots.</p>
                </div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Key</th>
                            <th>Status</th>
                            <th>Rate Limit</th>
                            <th>Screenshots</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($apiKeys as $apiKey)
                            <tr>
                                <td style="font-weight: 500; color: var(--text-primary);">
                                    {{ $apiKey->name }}
                                </td>
                                <td>
                                    <code class="key-display" style="font-size: 0.75rem;">{{ $apiKey->key }}</code>
                                </td>
                                <td>
                                    @if($apiKey->is_active)
                                        <x-admin.badge type="success">Active</x-admin.badge>
                                    @else
                                        <x-admin.badge type="danger">Inactive</x-admin.badge>
                                    @endif
                                </td>
                                <td>
                                    @if($apiKey->rate_limit)
                                        {{ number_format($apiKey->rate_limit) }}/hour
                                    @else
                                        <span style="color: var(--text-muted);">Unlimited</span>
                                    @endif
                                </td>
                                <td>{{ number_format($apiKey->screenshots_count) }}</td>
                                <td>{{ $apiKey->created_at->format('M j, Y') }}</td>
                                <td>
                                    <div class="actions">
                                        @if(auth()->user()->isSuperAdmin())
                                            <a href="{{ route('admin.api-keys.edit', $apiKey) }}" class="btn btn-secondary btn-sm">Edit</a>
                                        @endif
                                        <form action="{{ route('admin.api-keys.toggle', $apiKey) }}" method="POST" style="display: inline;">
                                            @csrf
                                            <button type="submit" class="btn btn-secondary btn-sm">
                                                {{ $apiKey->is_active ? 'Deactivate' : 'Activate' }}
                                            </button>
                                        </form>
                                        {{-- Delete button: show for super admins OR if it's the user's own key --}}
                                        @if(auth()->user()->isSuperAdmin() || $apiKey->user_id === auth()->id())
                                            <form action="{{ route('admin.api-keys.destroy', $apiKey) }}" method="POST" style="display: inline;" onsubmit="return confirm('Are you sure? This will also delete all associated screenshots.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</x-admin.layout>
