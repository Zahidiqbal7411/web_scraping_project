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
                    id="view-searches-btn"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 px-6 rounded-lg shadow-md transition duration-200 flex items-center space-x-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                    </svg>
                    <span>View Saved Searches</span>
                </button>
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

        <!-- Save Search Input -->
        <div class="bg-white rounded-lg shadow p-6 mb-8">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Save New Search</h2>
            <div class="flex gap-4">
                <input 
                    type="text" 
                    id="rightmove-url-input"
                    placeholder="Paste Rightmove URL here (e.g. https://www.rightmove.co.uk/property-for-sale/find.html?...)"
                    class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition duration-150">
                <button 
                    id="save-search-btn"
                    class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-6 rounded-lg shadow transition duration-200 flex items-center gap-2 whitespace-nowrap">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path>
                    </svg>
                    Save Search
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
        <!-- Saved Searches Modal -->
        <div id="saved-searches-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-5xl shadow-lg rounded-md bg-white">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-bold text-gray-900">Saved Searches</h3>
                    <button id="close-modal-btn" class="text-gray-400 hover:text-gray-500">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Area</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price Range</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bedrooms</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="saved-searches-body" class="bg-white divide-y divide-gray-200">
                            <!-- Populated via JS -->
                        </tbody>
                    </table>
                </div>
            </div>
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
        const saveSearchBtn = document.getElementById('save-search-btn');
        const urlInput = document.getElementById('rightmove-url-input');
        const viewSearchesBtn = document.getElementById('view-searches-btn');
        const savedSearchesModal = document.getElementById('saved-searches-modal');
        const closeModalBtn = document.getElementById('close-modal-btn');
        const savedSearchesBody = document.getElementById('saved-searches-body');

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

        // --- Saved Searches Logic ---

        // Save Search
        saveSearchBtn.addEventListener('click', async () => {
            const url = urlInput.value.trim();
            if (!url) {
                showNotification('Please enter a valid URL', 'error');
                return;
            }

            const originalContent = saveSearchBtn.innerHTML;
            saveSearchBtn.disabled = true;
            saveSearchBtn.innerHTML = '<span class="loading"></span> Saving...';

            try {
                const response = await fetch('/api/saved-searches', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ updates_url: url })
                });

                const data = await response.json();

                if (data.success) {
                    showNotification('Search saved successfully!', 'success');
                    urlInput.value = '';
                } else {
                    showNotification(data.message || 'Failed to save search', 'error');
                }
            } catch (error) {
                showNotification('Network error: ' + error.message, 'error');
            } finally {
                saveSearchBtn.disabled = false;
                saveSearchBtn.innerHTML = originalContent;
            }
        });

        // Toggle Modal
        viewSearchesBtn.addEventListener('click', () => {
            savedSearchesModal.classList.remove('hidden');
            loadSavedSearches();
        });

        closeModalBtn.addEventListener('click', () => {
            savedSearchesModal.classList.add('hidden');
        });

        // Close modal on outside click
        savedSearchesModal.addEventListener('click', (e) => {
            if (e.target === savedSearchesModal) {
                savedSearchesModal.classList.add('hidden');
            }
        });

        // Load Saved Searches
        async function loadSavedSearches() {
            savedSearchesBody.innerHTML = '<tr><td colspan="5" class="text-center py-4">Loading...</td></tr>';

            try {
                const response = await fetch('/api/saved-searches');
                const data = await response.json();

                if (data.success && data.searches) {
                    if (data.searches.length === 0) {
                        savedSearchesBody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-gray-500">No saved searches found</td></tr>';
                        return;
                    }

                    savedSearchesBody.innerHTML = data.searches.map(search => {
                        const minPrice = search.min_price ? `£${parseInt(search.min_price).toLocaleString()}` : '0';
                        const maxPrice = search.max_price ? `£${parseInt(search.max_price).toLocaleString()}` : 'Max';
                        const minBed = search.min_bed || '0';
                        const maxBed = search.max_bed || 'Max';
                        const type = search.property_type ? search.property_type.replace(/,/g, ', ') : 'Any';
                        const area = search.area ? search.area.replace(/\+/g, ' ') : 'Unknown';

                        return `
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${area}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${minPrice} - ${maxPrice}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${minBed} - ${maxBed} Beds</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 capitalize">${type}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex gap-2">
                                        <a href="${search.updates_url}" target="_blank" class="text-indigo-600 hover:text-indigo-900 bg-indigo-50 px-3 py-1 rounded-md">View</a>
                                        <button onclick="deleteSearch(${search.id})" class="text-red-600 hover:text-red-900 bg-red-50 px-3 py-1 rounded-md">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        `;
                    }).join('');
                } else {
                    savedSearchesBody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-red-500">Failed to load searches</td></tr>';
                }
            } catch (error) {
                console.error(error);
                savedSearchesBody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-red-500">Error loading searches</td></tr>';
            }
        }

        // Delete Search
        window.deleteSearch = async (id) => {
            if (!confirm('Are you sure you want to delete this saved search?')) return;

            try {
                const response = await fetch(`/api/saved-searches/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification('Search deleted successfully', 'success');
                    loadSavedSearches(); // Reload list
                } else {
                    showNotification('Failed to delete search', 'error');
                }
            } catch (error) {
                showNotification('Error deleting search', 'error');
            }
        };
    </script>
</body>
</html>