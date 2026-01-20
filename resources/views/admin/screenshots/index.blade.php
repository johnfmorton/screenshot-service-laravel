<x-admin.layout title="Screenshots" description="Manage captured screenshots">
    <div class="filter-bar">
        <form action="{{ route('admin.screenshots.index') }}" method="GET" style="display: contents;">
            <input
                type="text"
                name="search"
                class="form-input"
                placeholder="Search by URL..."
                value="{{ request('search') }}"
            >
            <select name="status" class="form-input">
                <option value="">All Statuses</option>
                <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="processing" {{ request('status') === 'processing' ? 'selected' : '' }}>Processing</option>
                <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completed</option>
                <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>Failed</option>
            </select>
            @if(auth()->user()->isSuperAdmin())
                <select name="api_key" class="form-input">
                    <option value="">All API Keys</option>
                    @foreach($apiKeys as $apiKey)
                        <option value="{{ $apiKey->id }}" {{ request('api_key') === $apiKey->id ? 'selected' : '' }}>
                            {{ $apiKey->name }}
                        </option>
                    @endforeach
                </select>
            @endif
            <button type="submit" class="btn btn-secondary">Filter</button>
            @if(request()->hasAny(['search', 'status', 'api_key']))
                <a href="{{ route('admin.screenshots.index') }}" class="btn btn-secondary">Clear</a>
            @endif
        </form>
    </div>

    <div class="card">
        <div class="table-container">
            @if($screenshots->isEmpty())
                <div class="empty-state">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                        <circle cx="8.5" cy="8.5" r="1.5"></circle>
                        <polyline points="21 15 16 10 5 21"></polyline>
                    </svg>
                    <h3>No screenshots found</h3>
                    <p>Try adjusting your filters or wait for new screenshots.</p>
                </div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>URL</th>
                            <th>Status</th>
                            <th>API Key</th>
                            <th>Viewport</th>
                            <th>Created</th>
                            <th>Expires</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($screenshots as $screenshot)
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
                                <td class="mono" style="font-size: 0.8125rem;">
                                    {{ $screenshot->viewport_width }}x{{ $screenshot->viewport_height }}
                                </td>
                                <td>{{ $screenshot->created_at->format('M j, Y H:i') }}</td>
                                <td>
                                    @if($screenshot->expires_at)
                                        @if($screenshot->isExpired())
                                            <span style="color: var(--danger);">Expired</span>
                                        @else
                                            {{ $screenshot->expires_at->diffForHumans() }}
                                        @endif
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>
                                    <div class="actions">
                                        @if($screenshot->isCompleted() && $screenshot->full_image_url)
                                            <a href="{{ $screenshot->full_image_url }}" target="_blank" class="btn btn-secondary btn-sm" title="View Full Image">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14">
                                                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                                    <polyline points="15 3 21 3 21 9"></polyline>
                                                    <line x1="10" y1="14" x2="21" y2="3"></line>
                                                </svg>
                                            </a>
                                        @endif
                                        <form action="{{ route('admin.screenshots.destroy', $screenshot) }}" method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this screenshot?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm" title="Delete">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14">
                                                    <polyline points="3 6 5 6 21 6"></polyline>
                                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    @if($screenshots->hasPages())
        <div class="pagination">
            @if($screenshots->onFirstPage())
                <span class="pagination-link disabled">Previous</span>
            @else
                <a href="{{ $screenshots->previousPageUrl() }}" class="pagination-link">Previous</a>
            @endif

            @foreach($screenshots->getUrlRange(1, $screenshots->lastPage()) as $page => $url)
                <a href="{{ $url }}" class="pagination-link {{ $page == $screenshots->currentPage() ? 'active' : '' }}">
                    {{ $page }}
                </a>
            @endforeach

            @if($screenshots->hasMorePages())
                <a href="{{ $screenshots->nextPageUrl() }}" class="pagination-link">Next</a>
            @else
                <span class="pagination-link disabled">Next</span>
            @endif
        </div>
    @endif
</x-admin.layout>
