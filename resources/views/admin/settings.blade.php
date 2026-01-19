<x-admin.layout title="Settings" description="View system configuration">
    <div class="card" style="max-width: 600px;">
        <div class="card-header">
            <h2>Configuration Values</h2>
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Setting</th>
                        <th>Value</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($settings as $name => $value)
                        <tr>
                            <td style="font-weight: 500; color: var(--text-primary);">{{ $name }}</td>
                            <td class="mono" style="font-size: 0.8125rem;">{{ $value }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="card" style="max-width: 600px; margin-top: 24px;">
        <div class="card-header">
            <h2>Artisan Commands</h2>
        </div>
        <div class="card-body">
            <p style="color: var(--text-secondary); margin-bottom: 16px;">
                Useful commands for managing the screenshot service:
            </p>

            <div style="display: flex; flex-direction: column; gap: 16px;">
                <div>
                    <div class="key-display" style="margin-bottom: 8px;">
                        php artisan screenshot:list
                    </div>
                    <p class="form-hint">List screenshots with optional filters (--status, --api-key, --expired)</p>
                </div>

                <div>
                    <div class="key-display" style="margin-bottom: 8px;">
                        php artisan screenshot:stats
                    </div>
                    <p class="form-hint">Display screenshot statistics and per-API key breakdown</p>
                </div>

                <div>
                    <div class="key-display" style="margin-bottom: 8px;">
                        php artisan screenshot:cleanup --dry-run
                    </div>
                    <p class="form-hint">Clean up expired screenshots (use --dry-run to preview)</p>
                </div>
            </div>
        </div>
    </div>
</x-admin.layout>
