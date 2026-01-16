@extends('layouts.app')

@section('styles')
<style>
    .import-container {
        max-width: 800px;
        margin: 2rem auto;
        padding: 3rem;
        background: var(--card-bg);
        border-radius: 16px;
        box-shadow: var(--shadow-lg);
        text-align: center;
        position: relative;
        overflow: hidden;
    }

    .loader-wrapper {
        margin: 2.5rem 0;
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100px;
    }

    /* Premium Modern Loader */
    .premium-loader {
        width: 60px;
        height: 60px;
        border: 4px solid var(--primary-light);
        border-top: 4px solid var(--primary);
        border-radius: 50%;
        animation: spin 1s linear infinite, glow 2s ease-in-out infinite;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    @keyframes glow {
        0%, 100% { box-shadow: 0 0 5px hsla(220, 85%, 50%, 0.2); }
        50% { box-shadow: 0 0 20px hsla(220, 85%, 50%, 0.4); }
    }

    #successContainer {
        display: none;
        animation: fadeInScale 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }

    @keyframes fadeInScale {
        from { opacity: 0; transform: scale(0.8); }
        to { opacity: 1; transform: scale(1); }
    }

    .success-icon {
        width: 80px;
        height: 80px;
        background: var(--success);
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.5rem;
        margin: 0 auto 1.5rem;
        box-shadow: 0 8px 16px hsla(142, 70%, 45%, 0.3);
    }

    .progress-wrapper {
        margin-top: 2rem;
        padding: 0 2rem;
    }

    .custom-progress {
        height: 12px;
        background: var(--bg);
        border-radius: 10px;
        overflow: hidden;
        margin-bottom: 0.75rem;
        box-shadow: inset 0 2px 4px rgba(0,0,0,0.05);
    }

    .custom-progress-bar {
        height: 100%;
        background: linear-gradient(90deg, var(--primary), var(--secondary));
        width: 0%;
        transition: width 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        border-radius: 10px;
    }

    #logContainer {
        margin-top: 3rem;
        text-align: left;
    }

    .log-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.75rem;
        color: var(--text-secondary);
        font-size: 0.85rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    #logArea {
        background: #1e293b;
        color: #e2e8f0;
        padding: 1.25rem;
        border-radius: 12px;
        height: 250px;
        overflow-y: auto;
        font-family: 'Fira Code', monospace;
        font-size: 13px;
        line-height: 1.6;
        border: 1px solid rgba(255,255,255,0.1);
        box-shadow: inset 0 2px 10px rgba(0,0,0,0.2);
    }

    #logArea::-webkit-scrollbar {
        width: 8px;
    }
    #logArea::-webkit-scrollbar-thumb {
        background: rgba(255,255,255,0.1);
        border-radius: 4px;
    }
</style>
@endsection

@section('content')
<div class="container">
    <div class="import-container">
        <div id="importContent">
            <h2 id="statusTitle" style="font-weight: 800; margin-bottom: 0.5rem;">📅 Schedule Import</h2>
            <p id="statusMessage" class="text-muted" style="font-size: 1.1rem;">Initializing connection to scraper engine...</p>
            
            <div class="loader-wrapper" id="loaderWrapper">
                <div class="premium-loader"></div>
            </div>

            <div class="progress-wrapper">
                <div class="custom-progress">
                    <div id="progressBar" class="custom-progress-bar"></div>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span id="progressText" style="font-weight: 700; color: var(--primary);">0%</span>
                    <span class="text-muted" style="font-size: 0.85rem;">Overall Progress</span>
                </div>
            </div>
        </div>

        <!-- Success Message Container -->
        <div id="successContainer">
            <div class="success-icon">
                <svg width="40" height="40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
            <h2 style="font-weight: 800; color: var(--text-primary);">Processing Complete!</h2>
            <p class="text-muted" style="font-size: 1.1rem; margin-bottom: 2rem;">All scheduled tasks have been successfully finalized.</p>
            <div class="d-flex justify-content-center gap-3">
                <a href="{{ url('/searchproperties') }}" class="btn btn-primary" style="padding: 0.8rem 2rem;">Back to Saved Searches</a>
                <a href="{{ route('internal-property.index') }}" class="btn btn-secondary" style="padding: 0.8rem 2rem; background: var(--bg); color: var(--text-primary); border: 1px solid var(--card-border);">View All Properties</a>
            </div>
        </div>

        <div id="logContainer">
            <div class="log-header">
                <span>Operation Logs</span>
                <span id="logStatus" class="badge" style="background: var(--primary-light); color: var(--primary); font-size: 0.7rem; padding: 0.3rem 0.6rem;">Live Tracking</span>
            </div>
            <div id="logArea"></div>
        </div>
    </div>
</div>

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', async function() {
    const statusTitle = document.getElementById('statusTitle');
    const statusMessage = document.getElementById('statusMessage');
    const progressBar = document.getElementById('progressBar');
    const progressText = document.getElementById('progressText');
    const loaderWrapper = document.getElementById('loaderWrapper');
    const importContent = document.getElementById('importContent');
    const successContainer = document.getElementById('successContainer');
    const logArea = document.getElementById('logArea');
    const logStatus = document.getElementById('logStatus');

    function log(message, type = 'info') {
        const time = new Date().toLocaleTimeString();
        let color = '#e2e8f0';
        if (type === 'success') color = '#4ade80';
        if (type === 'error') color = '#f87171';
        if (type === 'warning') color = '#fbbf24';

        logArea.innerHTML += `<div style="margin-bottom: 4px;"><span style="color: #64748b;">[${time}]</span> <span style="color: ${color}">${message}</span></div>`;
        logArea.scrollTop = logArea.scrollHeight;
    }

    log('Establishing connection with background workers...');

    let isDone = false;
    let errorCount = 0;
    let consecutiveSuccesses = 0;

    while (!isDone) {
        try {
            // Use AbortController for request timeout (60 seconds to be safe during worker startup)
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 60000);

            const response = await fetch('/schedules/process', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                signal: controller.signal
            });

            clearTimeout(timeoutId);

            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            
            const data = await response.json();
            errorCount = 0; // Reset error count on success
            consecutiveSuccesses++;

            if (!data.success) {
                statusTitle.textContent = '❌ Import Halted';
                statusMessage.textContent = data.error || data.message;
                log('CRITICAL: ' + (data.error || data.message), 'error');
                logStatus.textContent = 'Process Error';
                logStatus.style.background = 'var(--error)';
                logStatus.style.color = 'white';
                break;
            }

            if (data.done) {
                isDone = true;
                importContent.style.display = 'none';
                successContainer.style.display = 'block';
                log('SUCCESS: All scheduled imports finished!', 'success');
                logStatus.textContent = 'Completed';
                logStatus.style.background = 'var(--success)';
                logStatus.style.color = 'white';
                break;
            }

            if (data.schedule_completed) {
                log('✓ Completed: ' + data.schedule_name + ' (' + (data.imported_count || 0) + ' properties)', 'success');
                continue;
            }

            // Update progress
            statusTitle.innerHTML = `<span style="color: var(--primary);">📥 Importing:</span> ${data.schedule_name}`;
            statusMessage.textContent = data.message;
            
            const progress = data.progress_percentage || 0;
            progressBar.style.width = progress + '%';
            progressText.textContent = Math.round(progress) + '%';
            
            if (data.message && !data.message.includes('No more pending')) {
                log(data.message);
            }

        } catch (error) {
            errorCount++;
            consecutiveSuccesses = 0;
            console.error(error);
            
            // More descriptive error based on state and error type
            let errorMsg = 'Connection interrupted. Retrying... (' + errorCount + ')';
            if (error.name === 'AbortError') {
                errorMsg = 'Request timed out. Starting worker... (' + errorCount + ')';
            } else if (statusMessage.textContent.includes('Initializing')) {
                errorMsg = 'Worker initializing import patterns... (' + errorCount + ')';
            } else if (statusMessage.textContent.includes('Started queued')) {
                errorMsg = 'Worker starting up. Please wait... (' + errorCount + ')';
            }
            
            log(errorMsg, 'warning');
            
            if (errorCount > 600) { // Allow significantly more retries for large recursions (up to ~20 mins)
                statusTitle.textContent = '❌ Connection Lost';
                statusMessage.textContent = 'Unable to reach the server. Please check your connection.';
                log('MAX RETRIES EXCEEDED: Connection aborted. Please refresh the page.', 'error');
                break;
            }
            
            // Wait between retries - start short, increase gradually
            const retryDelay = Math.min(2000 + (500 * errorCount), 10000); 
            await new Promise(resolve => setTimeout(resolve, retryDelay));
            continue; // Force next iteration of loop to retry
        }

        // Polling interval - faster when things are progressing
        const pollDelay = consecutiveSuccesses > 3 ? 1500 : 2000;
        await new Promise(resolve => setTimeout(resolve, pollDelay));
    }
});
</script>
@endsection
@endsection
