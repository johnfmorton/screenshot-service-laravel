<x-admin.layout title="Users" description="Manage admin users">
    <div style="margin-bottom: 20px;">
        <a href="{{ route('admin.users.create') }}" class="btn btn-primary">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Create User
        </a>
    </div>

    <div class="card">
        <div class="table-container">
            @if($users->isEmpty())
                <div class="empty-state">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                    <h3>No users yet</h3>
                    <p>Create your first user to grant access to the admin panel.</p>
                </div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>API Keys</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($users as $user)
                            <tr>
                                <td style="font-weight: 500; color: var(--text-primary);">
                                    {{ $user->name }}
                                    @if($user->id === auth()->id())
                                        <span style="color: var(--text-muted); font-weight: 400;">(you)</span>
                                    @endif
                                </td>
                                <td>{{ $user->email }}</td>
                                <td>
                                    @if($user->is_super_admin)
                                        <x-admin.badge type="info">Super Admin</x-admin.badge>
                                    @else
                                        <x-admin.badge type="muted">User</x-admin.badge>
                                    @endif
                                </td>
                                <td>{{ $user->api_keys_count }}</td>
                                <td>{{ $user->created_at->format('M j, Y') }}</td>
                                <td>
                                    @if($user->id !== auth()->id())
                                        <div class="actions">
                                            <form action="{{ route('admin.users.toggle', $user) }}" method="POST" style="display: inline;">
                                                @csrf
                                                <button type="submit" class="btn btn-secondary btn-sm">
                                                    {{ $user->is_super_admin ? 'Demote' : 'Promote' }}
                                                </button>
                                            </form>
                                            <form action="{{ route('admin.users.destroy', $user) }}" method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                            </form>
                                        </div>
                                    @else
                                        <span style="color: var(--text-muted);">-</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</x-admin.layout>
