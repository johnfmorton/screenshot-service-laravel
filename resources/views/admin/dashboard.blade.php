<x-admin.layout title="Dashboard" description="Overview of your screenshot service">
    <div class="stats-grid">
        <x-admin.stat-card label="Total Screenshots" :value="$stats['total_screenshots']" />
        <x-admin.stat-card label="Active Screenshots" :value="$stats['active_screenshots']" accent />
        <x-admin.stat-card label="Pending" :value="$stats['pending_screenshots']" />
        <x-admin.stat-card label="Failed" :value="$stats['failed_screenshots']" />
        <x-admin.stat-card label="Today" :value="$stats['screenshots_today']" />
        <x-admin.stat-card label="API Keys" :value="$stats['total_api_keys']" />
        <x-admin.stat-card label="Active Keys" :value="$stats['active_api_keys']" />
        @if($stats['total_users'] !== null)
            <x-admin.stat-card label="Users" :value="$stats['total_users']" />
        @endif
    </div>

    <div class="card">
        <div class="card-header">
            <h2>Recent Screenshots</h2>
            <a href="{{ route('admin.screenshots.index') }}" class="btn btn-secondary btn-sm">View All</a>
        </div>
        <div class="table-container">
            @if($stats['recent_screenshots']->isEmpty())
                <div class="empty-state">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                        <circle cx="8.5" cy="8.5" r="1.5"></circle>
                        <polyline points="21 15 16 10 5 21"></polyline>
                    </svg>
                    <h3>No screenshots yet</h3>
                    <p>Screenshots will appear here once they're captured.</p>
                </div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>URL</th>
                            <th>Status</th>
                            <th>API Key</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($stats['recent_screenshots'] as $screenshot)
                            <tr>
                                <td>
                                    <span class="url-truncate" title="{{ $screenshot->url }}">
                                        {{ $screenshot->url }}
                                    </span>
                                </td>
                                <td>
                                    @switch($screenshot->status->value)
                                        @case('completed')
                                            <x-admin.badge type="success">Completed</x-admin.badge>
                                            @break
                                        @case('pending')
                                            <x-admin.badge type="warning">Pending</x-admin.badge>
                                            @break
                                        @case('processing')
                                            <x-admin.badge type="info">Processing</x-admin.badge>
                                            @break
                                        @case('failed')
                                            <x-admin.badge type="danger">Failed</x-admin.badge>
                                            @break
                                    @endswitch
                                </td>
                                <td>{{ $screenshot->apiKey?->name ?? 'N/A' }}</td>
                                <td>{{ $screenshot->created_at->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</x-admin.layout>
