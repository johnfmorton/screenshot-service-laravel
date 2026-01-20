<x-admin.layout title="Installation Check" description="Verify environment configuration and test screenshot capture">
    <div class="card" style="max-width: 700px;">
        <div class="card-header">
            <h2>Environment Variables</h2>
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Variable</th>
                        <th>Value</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($checks as $check)
                        <tr>
                            <td style="font-weight: 500; color: var(--text-primary);">{{ $check['name'] }}</td>
                            <td class="mono" style="font-size: 0.8125rem;">{{ $check['value'] ?? '(not set)' }}</td>
                            <td>
                                @if($check['status'] === 'success')
                                    <x-admin.badge type="success">OK</x-admin.badge>
                                @elseif($check['status'] === 'warning')
                                    <x-admin.badge type="warning">Warning</x-admin.badge>
                                @else
                                    <x-admin.badge type="danger">Error</x-admin.badge>
                                @endif
                                @if($check['message'])
                                    <span style="color: var(--text-secondary); font-size: 0.75rem; margin-left: 8px;">
                                        {{ $check['message'] }}
                                    </span>
                                @endif
                            </td>
                        </tr>
                        @if(isset($check['install_hints']))
                            <tr>
                                <td colspan="3" style="padding: 0;">
                                    <div style="background: var(--bg-secondary); padding: 16px; border-top: 1px solid var(--border-color);">
                                        <div style="font-weight: 500; color: var(--text-primary); margin-bottom: 12px;">
                                            Installation Instructions for {{ $check['install_hints']['os'] }}
                                        </div>

                                        @foreach($check['install_hints']['instructions'] as $instruction)
                                            <div style="margin-bottom: 12px;">
                                                <div style="color: var(--text-secondary); font-size: 0.875rem; margin-bottom: 6px;">
                                                    {{ $instruction['title'] }}
                                                </div>
                                                <pre style="background: var(--bg-primary); padding: 12px; border-radius: 6px; margin: 0; overflow-x: auto; font-size: 0.8125rem; border: 1px solid var(--border-color);">{{ implode("\n", $instruction['commands']) }}</pre>
                                            </div>
                                        @endforeach

                                        @if($check['install_hints']['dependencies'])
                                            <div style="margin-bottom: 12px;">
                                                <div style="color: var(--text-secondary); font-size: 0.875rem; margin-bottom: 6px;">
                                                    {{ $check['install_hints']['dependencies']['title'] }}
                                                </div>
                                                <pre style="background: var(--bg-primary); padding: 12px; border-radius: 6px; margin: 0; overflow-x: auto; font-size: 0.8125rem; border: 1px solid var(--border-color);">{{ implode("\n", $check['install_hints']['dependencies']['commands']) }}</pre>
                                            </div>
                                        @endif

                                        <div style="margin-top: 16px; padding: 12px; background: rgba(59, 130, 246, 0.1); border-radius: 6px; border: 1px solid rgba(59, 130, 246, 0.2);">
                                            <div style="font-weight: 500; color: var(--text-primary); margin-bottom: 4px;">
                                                Update your .env file
                                            </div>
                                            <pre style="background: var(--bg-primary); padding: 8px 12px; border-radius: 4px; margin: 0; font-size: 0.8125rem; border: 1px solid var(--border-color);">SCREENSHOT_CHROME_PATH={{ $check['install_hints']['env_path'] }}</pre>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="card" style="max-width: 700px; margin-top: 24px;">
        <div class="card-header">
            <h2>Test Screenshot</h2>
        </div>
        <div class="card-body">
            <p style="color: var(--text-secondary); margin-bottom: 16px;">
                Test the complete screenshot capture flow by entering a URL below.
            </p>

            <form id="test-form" style="display: flex; gap: 12px; align-items: flex-start;">
                @csrf
                <div style="flex: 1;">
                    <input
                        type="url"
                        id="test-url"
                        name="url"
                        class="form-input"
                        value="https://example.com"
                        placeholder="https://example.com"
                        required
                        style="width: 100%;"
                    >
                </div>
                <button type="submit" id="test-button" class="btn btn-primary">
                    Run Test
                </button>
            </form>

            <div id="test-status" style="margin-top: 20px; display: none;">
                <div style="padding: 16px; background: var(--bg-secondary); border-radius: 8px;">
                    <div id="status-indicator" style="display: flex; align-items: center; gap: 12px;">
                        <div id="status-spinner" class="spinner" style="display: none;"></div>
                        <span id="status-text" style="font-weight: 500;"></span>
                    </div>

                    <div id="result-success" style="display: none; margin-top: 16px;">
                        <div style="color: var(--success); font-weight: 500; margin-bottom: 12px;">
                            Screenshot captured successfully!
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 8px;">
                            <div>
                                <span style="color: var(--text-secondary);">Full Image:</span>
                                <a id="full-image-link" href="#" target="_blank" class="mono" style="font-size: 0.8125rem; word-break: break-all;"></a>
                            </div>
                            <div>
                                <span style="color: var(--text-secondary);">Thumbnail:</span>
                                <a id="thumbnail-link" href="#" target="_blank" class="mono" style="font-size: 0.8125rem; word-break: break-all;"></a>
                            </div>
                        </div>
                        <div id="preview-container" style="margin-top: 16px;">
                            <img id="thumbnail-preview" src="" alt="Thumbnail preview" style="max-width: 100%; border-radius: 4px; border: 1px solid var(--border-color);">
                        </div>
                    </div>

                    <div id="result-error" style="display: none; margin-top: 16px;">
                        <div style="color: var(--danger); font-weight: 500; margin-bottom: 8px;">
                            Screenshot failed
                        </div>
                        <div id="error-message" class="mono" style="font-size: 0.8125rem; color: var(--text-secondary); margin-bottom: 12px;"></div>
                        <div id="troubleshooting" style="display: none;">
                            <div style="font-weight: 500; margin-bottom: 8px; color: var(--text-primary);">Troubleshooting Tips:</div>
                            <ul id="troubleshooting-list" style="margin: 0; padding-left: 20px; color: var(--text-secondary); font-size: 0.875rem;"></ul>
                        </div>
                    </div>

                    <div id="pending-warning" style="display: none; margin-top: 16px; padding: 12px; background: rgba(251, 191, 36, 0.1); border-radius: 6px; border: 1px solid rgba(251, 191, 36, 0.3);">
                        <div style="color: var(--warning); font-weight: 500; margin-bottom: 4px;">
                            Screenshot still pending
                        </div>
                        <div style="color: var(--text-secondary); font-size: 0.875rem;">
                            The queue worker may not be running. Start it with: <code class="key-display" style="display: inline; padding: 2px 6px;">php artisan queue:work</code>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .spinner {
            width: 20px;
            height: 20px;
            border: 2px solid var(--border-color);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('test-form');
            const urlInput = document.getElementById('test-url');
            const testButton = document.getElementById('test-button');
            const statusContainer = document.getElementById('test-status');
            const statusSpinner = document.getElementById('status-spinner');
            const statusText = document.getElementById('status-text');
            const resultSuccess = document.getElementById('result-success');
            const resultError = document.getElementById('result-error');
            const pendingWarning = document.getElementById('pending-warning');

            let pollInterval = null;
            let pollCount = 0;
            const maxPolls = 60; // 2 minutes max polling

            form.addEventListener('submit', function(e) {
                e.preventDefault();
                startTest();
            });

            function startTest() {
                // Reset state
                clearInterval(pollInterval);
                pollCount = 0;
                statusContainer.style.display = 'block';
                statusSpinner.style.display = 'block';
                statusText.textContent = 'Creating screenshot request...';
                resultSuccess.style.display = 'none';
                resultError.style.display = 'none';
                pendingWarning.style.display = 'none';
                testButton.disabled = true;
                testButton.textContent = 'Testing...';

                fetch('{{ route('admin.installation-check.test') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        url: urlInput.value
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        statusText.textContent = 'Screenshot queued, waiting for capture...';
                        startPolling(data.poll_url);
                    } else {
                        showError(data.error, []);
                    }
                })
                .catch(error => {
                    showError('Request failed: ' + error.message, []);
                });
            }

            function startPolling(pollUrl) {
                pollInterval = setInterval(function() {
                    pollCount++;

                    if (pollCount > maxPolls) {
                        clearInterval(pollInterval);
                        showError('Polling timeout - screenshot is taking too long', [
                            'Check if the queue worker is running',
                            'The target URL may be very slow to load'
                        ]);
                        return;
                    }

                    // Show pending warning after 10 seconds
                    if (pollCount === 5) {
                        pendingWarning.style.display = 'block';
                    }

                    fetch(pollUrl, {
                        headers: {
                            'Accept': 'application/json'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        updateStatus(data);

                        if (data.is_completed || data.is_failed) {
                            clearInterval(pollInterval);
                        }
                    })
                    .catch(error => {
                        console.error('Polling error:', error);
                    });
                }, 2000);
            }

            function updateStatus(data) {
                if (data.status === 'processing') {
                    statusText.textContent = 'Processing screenshot...';
                    pendingWarning.style.display = 'none';
                } else if (data.status === 'pending') {
                    statusText.textContent = 'Waiting in queue...';
                }

                if (data.is_completed) {
                    showSuccess(data);
                } else if (data.is_failed) {
                    showError(data.error_message || 'Screenshot capture failed', data.troubleshooting || []);
                }
            }

            function showSuccess(data) {
                statusSpinner.style.display = 'none';
                statusText.textContent = 'Complete!';
                pendingWarning.style.display = 'none';
                resultSuccess.style.display = 'block';

                const fullImageLink = document.getElementById('full-image-link');
                const thumbnailLink = document.getElementById('thumbnail-link');
                const thumbnailPreview = document.getElementById('thumbnail-preview');

                fullImageLink.href = data.full_image_url;
                fullImageLink.textContent = data.full_image_url;

                thumbnailLink.href = data.thumbnail_url;
                thumbnailLink.textContent = data.thumbnail_url;

                thumbnailPreview.src = data.thumbnail_url;

                resetButton();
            }

            function showError(message, tips) {
                statusSpinner.style.display = 'none';
                statusText.textContent = 'Failed';
                pendingWarning.style.display = 'none';
                resultError.style.display = 'block';

                document.getElementById('error-message').textContent = message;

                const troubleshooting = document.getElementById('troubleshooting');
                const troubleshootingList = document.getElementById('troubleshooting-list');

                // Clear existing list items
                while (troubleshootingList.firstChild) {
                    troubleshootingList.removeChild(troubleshootingList.firstChild);
                }

                if (tips && tips.length > 0) {
                    troubleshooting.style.display = 'block';
                    tips.forEach(function(tip) {
                        const li = document.createElement('li');
                        li.textContent = tip;
                        troubleshootingList.appendChild(li);
                    });
                } else {
                    troubleshooting.style.display = 'none';
                }

                resetButton();
            }

            function resetButton() {
                testButton.disabled = false;
                testButton.textContent = 'Run Test';
            }
        });
    </script>
</x-admin.layout>
