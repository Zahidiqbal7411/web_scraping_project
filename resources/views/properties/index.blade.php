<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Rightmove Internal URLs - Web Scraping</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 1rem 1.5rem;
            border-radius: 0.5rem;
            color: white;
            font-weight: 500;
            z-index: 1000;
            animation: slideIn 0.3s ease-out;
        }
        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        .success { background-color: #10b981; }
        .error { background-color: #ef4444; }
        
        /* Table styles */
        table {
            border-collapse: separate;
            border-spacing: 0;
        }
        thead {
            position: sticky;
            top: 0;
            z-index: 10;
            background: linear-gradient(to bottom, #1e40af, #1e3a8a);
        }
        tbody tr:hover {
            background-color: #f0f9ff;
        }
        .url-cell {
            max-width: 500px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .table-container {
            max-height: 600px;
            overflow-y: auto;
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .table-container::-webkit-scrollbar {
            width: 10px;
        }
        .table-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        .table-container::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 10px;
        }
        .table-container::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-4xl font-bold text-gray-900">Rightmove Internal URLs</h1>
                <p class="text-gray-600 mt-2">Web scraping project</p>
            </div>
            <div class="flex gap-2">

                <button 
                    id="fetch-btn" 
                    class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg shadow-md transition duration-200 flex items-center space-x-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    <span>Fetch URLs</span>
                </button>
            </div>
        </div>



        <!-- Stats -->
        <div id="stats-container" class="hidden grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-600">Total URLs</p>
                        <p id="total-urls" class="text-2xl font-bold text-gray-900">0</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-600">Source</p>
                        <p class="text-lg font-bold text-gray-900">Rightmove</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-600">Last Updated</p>
                        <p id="last-updated" class="text-sm font-semibold text-gray-900">-</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Table Container -->
        <div id="table-wrapper" class="hidden">
            <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                <div class="table-container">
                    <table class="min-w-full">
                        <thead>
                            <tr>
                                <th class="py-4 px-6 text-left text-xs font-semibold text-white uppercase tracking-wider border-b border-blue-800">
                                    #
                                </th>
                                <th class="py-4 px-6 text-left text-xs font-semibold text-white uppercase tracking-wider border-b border-blue-800">
                                    Internal URL
                                </th>
                                <th class="py-4 px-6 text-center text-xs font-semibold text-white uppercase tracking-wider border-b border-blue-800">
                                    Action
                                </th>
                            </tr>
                        </thead>
                        <tbody id="urls-table-body" class="bg-white divide-y divide-gray-200">
                            <!-- URLs will be populated here -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div id="empty-state" class="text-center py-16 bg-white rounded-lg shadow-md">
            <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            <p class="text-gray-500 text-lg mt-4">Click "Fetch URLs" to load property URLs from Rightmove</p>
            <p class="text-gray-400 text-sm mt-2">This will fetch approximately 600 internal property URLs</p>
        </div>

    </div>

    <script>
        const fetchBtn = document.getElementById('fetch-btn');
        const tableWrapper = document.getElementById('table-wrapper');
        const emptyState = document.getElementById('empty-state');
        const statsContainer = document.getElementById('stats-container');
        const urlsTableBody = document.getElementById('urls-table-body');
        const totalUrlsElement = document.getElementById('total-urls');
        const lastUpdatedElement = document.getElementById('last-updated');

        // Saved Search Elements


        // Show notification
        function showNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.textContent = message;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 5000);
        }

        // Format date
        function formatDateTime() {
            const now = new Date();
            return now.toLocaleString('en-GB', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        // Fetch URLs
        fetchBtn.addEventListener('click', async () => {
            const originalContent = fetchBtn.innerHTML;
            fetchBtn.disabled = true;
            fetchBtn.innerHTML = '<div class="loading"></div><span class="ml-2">Fetching URLs...</span>';
            
            try {
                const response = await fetch('/api/internal-property/fetch-urls', {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                
                const data = await response.json();
                
                if (data.success && data.urls) {
                    showNotification(`${data.count} property URLs fetched successfully!`, 'success');
                    displayUrls(data.urls);
                    
                    // Update stats
                    totalUrlsElement.textContent = data.count;
                    lastUpdatedElement.textContent = formatDateTime();
                    
                    // Show stats and table, hide empty state
                    statsContainer.classList.remove('hidden');
                    tableWrapper.classList.remove('hidden');
                    emptyState.classList.add('hidden');
                } else {
                    const errorMsg = data.error ? data.error : (data.message || 'Failed to fetch URLs');
                    showNotification(errorMsg, 'error');
                    console.error('Error details:', data);
                }
            } catch (error) {
                showNotification('Network error: ' + error.message, 'error');
                console.error('Fetch error:', error);
            } finally {
                fetchBtn.disabled = false;
                fetchBtn.innerHTML = originalContent;
            }
        });

        // Display URLs in table
        function displayUrls(urls) {
            urlsTableBody.innerHTML = urls.map((item, index) => `
                <tr class="transition duration-150">
                    <td class="py-4 px-6 text-sm font-medium text-gray-900">
                        ${index + 1}
                    </td>
                    <td class="py-4 px-6 text-sm text-blue-600 url-cell" title="${item.url}">
                        ${item.url || 'N/A'}
                    </td>
                    <td class="py-4 px-6 text-center">
                        <a href="${item.url}" target="_blank" 
                           class="inline-flex items-center px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-md transition duration-150">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                            </svg>
                            View
                        </a>
                    </td>
                </tr>
            `).join('');
        }


    </script>
</body>
</html>