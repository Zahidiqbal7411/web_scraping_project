<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Browse retirement properties from Rightmove">
    <title>
        @if(isset($search))
            {{ str_replace('+', ' ', urldecode($search->area)) }} - Property Details
        @else
            Internal Properties - Property Listings
        @endif
    </title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: hsl(220, 85%, 50%);
            --primary-light: hsl(220, 85%, 96%);
            --secondary: hsl(200, 70%, 50%);
            --accent: hsl(340, 70%, 55%);
            --bg: hsl(0, 0%, 98%);
            --card-bg: hsl(0, 0%, 100%);
            --card-border: hsl(0, 0%, 88%);
            --text-primary: hsl(0, 0%, 15%);
            --text-secondary: hsl(0, 0%, 45%);
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.08);
            --shadow-md: 0 2px 8px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 4px 16px rgba(0, 0, 0, 0.12);
            --success: hsl(142, 70%, 45%);
            --error: hsl(0, 70%, 55%);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg);
            color: var(--text-primary);
            min-height: 100vh;
            line-height: 1.6;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem 1.5rem;
        }

        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .title {
            font-size: 2.25rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .sync-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .sync-btn:hover {
            background: hsl(220, 85%, 45%);
            box-shadow: var(--shadow-md);
        }

        .sync-btn:active {
            transform: translateY(0);
        }

        .sync-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .sync-icon {
            width: 20px;
            height: 20px;
            animation: none;
        }

        .sync-btn:disabled .sync-icon {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* Stats Bar */
        .stats-bar {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 8px;
            padding: 1.25rem;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow-sm);
            flex-wrap: wrap;
            gap: 1rem;
        }

        .stat-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 0.875rem;
            font-weight: 500;
        }

        .stat-value {
            color: var(--text-primary);
            font-size: 1.5rem;
            font-weight: 700;
        }

        /* Alert Messages */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            display: none;
            align-items: center;
            gap: 1rem;
            animation: slideIn 0.3s ease-out;
        }

        .alert.active {
            display: flex;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .alert-success {
            background: rgba(34, 197, 94, 0.15);
            border: 1px solid var(--success);
            color: hsl(142, 76%, 60%);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid var(--error);
            color: hsl(0, 84%, 75%);
        }

        /* Loading State */
        .loading {
            display: none;
            text-align: center;
            padding: 3rem;
            color: var(--text-secondary);
        }

        .loading.active {
            display: block;
        }

        .spinner {
            border: 4px solid rgba(255, 255, 255, 0.1);
            border-top: 4px solid var(--primary);
            border-radius: 50%;
            width: 60px;
            height: 60px;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            display: none;
        }

        .empty-state.active {
            display: block;
        }

        .empty-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-text {
            font-size: 1.125rem;
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
        }

        /* Properties Grid */
        .properties-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        /* Property Card */
        .property-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 8px;
            overflow: hidden;
            transition: all 0.2s ease;
            box-shadow: var(--shadow-sm);
            cursor: pointer;
            animation: fadeIn 0.3s ease-out;
        }

        .property-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
            border-color: var(--primary);
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Property Image */
        .property-image-wrapper {
            position: relative;
            aspect-ratio: 4/3;
            overflow: hidden;
            background: linear-gradient(135deg, rgba(0,0,0,0.3), rgba(0,0,0,0.5));
        }

        .property-image-slider {
            position: relative;
            height: 100%;
        }

        .property-image-container {
            display: flex;
            transition: transform 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            height: 100%;
        }

        .property-image {
            min-width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .image-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            opacity: 0;
            transition: all 0.3s;
            z-index: 2;
        }

        .property-card:hover .image-nav {
            opacity: 1;
        }

        .image-nav:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-50%) scale(1.1);
        }

        .image-nav.prev {
            left: 0.5rem;
        }

        .image-nav.next {
            right: 0.5rem;
        }

        .image-counter {
            position: absolute;
            bottom: 0.75rem;
            right: 0.75rem;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(10px);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            z-index: 2;
        }

        .property-badge {
            position: absolute;
            top: 0.75rem;
            left: 0.75rem;
            background: var(--accent);
            color: white;
            padding: 0.375rem 0.875rem;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 600;
            z-index: 2;
        }
        
        /* Image Slider Wrapper */
        .image-slider-wrapper {
            position: relative;
            width: 100%;
            aspect-ratio: 4/3;
            overflow: hidden;
            background: #f0f0f0;
        }
        
        .image-slides {
            position: relative;
            width: 100%;
            height: 100%;
        }
        
        .image-slide {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            transition: opacity 0.5s ease;
        }
        
        .image-slide.active {
            opacity: 1;
        }
        
        .image-slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .nav-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(255, 255, 255, 0.8);
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1.5rem;
            color: #333;
            opacity: 0;
            transition: all 0.3s;
            z-index: 10;
        }
        
        .property-card:hover .nav-arrow {
            opacity: 1;
        }
        
        .nav-arrow:hover {
            background: white;
            transform: translateY(-50%) scale(1.1);
        }
        
        .nav-arrow.left {
            left: 0.75rem;
        }
        
        .nav-arrow.right {
            right: 0.75rem;
        }

        /* Property Details */
        .property-details {
            padding: 1.5rem;
        }

        .property-price {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .property-reduced {
            color: var(--accent);
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 1rem;
        }

        .property-address {
            color: var(--text-primary);
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            line-height: 1.4;
        }

        .property-features {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 1rem;
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .feature {
            display: flex;
            align-items: center;
            gap: 0.375rem;
        }

        .feature-icon {
            width: 16px;
            height: 16px;
        }

        .property-type {
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin-top: 0.75rem;
            padding-top: 0.75rem;
            border-top: 1px solid var(--card-border);
        }
        
        /* Property Info Section */
        .property-info-section {
            padding: 1.25rem;
        }
        
        .property-address-title {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0 0 0.75rem 0;
            line-height: 1.4;
        }
        
        .property-price-section {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }
        
        .price-amount {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .info-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 18px;
            height: 18px;
            font-size: 0.75rem;
            color: var(--text-secondary);
            cursor: help;
        }
        
        .reduced-date {
            font-size: 0.875rem;
            color: var(--accent);
            margin-bottom: 1rem;
        }
        
        /* Property Details Grid */
        .property-details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--card-border);
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .detail-label {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .detail-value {
            display: flex;
            align-items: center;
            gap: 0.375rem;
            font-size: 0.875rem;
            color: var(--text-primary);
            font-weight: 500;
        }
        
        .detail-icon {
            width: 16px;
            height: 16px;
            color: var(--text-secondary);
        }
        
        .detail-item.skeleton .detail-value {
            color: var(--text-secondary);
            font-style: italic;
        }

        .property-actions {
            display: flex;
            gap: 0.75rem;
            margin-top: 1rem;
        }

        .action-btn {
            flex: 1;
            padding: 0.75rem;
            border-radius: 8px;
            border: 1px solid var(--card-border);
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-primary);
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            text-align: center;
        }

        .action-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: var(--primary);
        }

        .action-btn.primary {
            background: var(--primary);
            color: white;
            border: none;
        }

        .action-btn.primary:hover {
            background: hsl(220, 85%, 45%);
        }
        
        /* View Button */
        .view-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            width: 100%;
            padding: 0.875rem;
            margin-top: 1rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        
        .view-btn:hover {
            background: hsl(220, 85%, 45%);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }
        
        .view-btn svg {
            flex-shrink: 0;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .title {
                font-size: 1.75rem;
            }

            .properties-grid {
                grid-template-columns: 1fr;
            }

            .stats-bar {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1 class="title">
                @if(isset($search))
                    Properties in {{ str_replace('+', ' ', urldecode($search->area)) }}
                @else
                    Internal Properties
                @endif
            </h1>
            <button class="sync-btn" id="syncBtn">
                <svg class="sync-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                Sync Properties
            </button>
        </div>

        <!-- Stats Bar -->
        <div class="stats-bar" id="statsBar" style="display: none;">
            <div class="stat-item">
                <span class="stat-label">Total Properties</span>
                <span class="stat-value" id="totalCount">0</span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Loaded</span>
                <span class="stat-value" id="loadedCount">0</span>
            </div>
        </div>

        <!-- Alerts -->
        <div class="alert alert-success" id="successAlert"></div>
        <div class="alert alert-error" id="errorAlert"></div>

        <!-- Loading State -->
        <div class="loading" id="loading">
            <div class="spinner"></div>
            <p>Loading properties...</p>
        </div>

        <!-- Empty State -->
        <div class="empty-state active" id="emptyState">
            <div class="empty-icon">🏠</div>
            <p class="empty-text">Click the "Sync Properties" button to load property listings</p>
            <p style="color: var(--text-secondary); font-size: 0.875rem;">This will fetch data from approximately 620 property URLs</p>
        </div>

        <!-- Properties Grid -->
        <div class="properties-grid" id="propertiesGrid"></div>
    </div>

    <script>
        window.searchContext = @json($search ?? null);
    </script>
    <script>
        // State
        let currentSlide = 0;
        let propertyData = null;
        let currentImageIndexes = {};
        let propertyUrls = [];
        let loadedProperties = [];

        // Elements
        const syncBtn = document.getElementById('syncBtn');
        const loading = document.getElementById('loading');
        const emptyState = document.getElementById('emptyState');
        const propertiesGrid = document.getElementById('propertiesGrid');
        const statsBar = document.getElementById('statsBar');
        const totalCount = document.getElementById('totalCount');
        const loadedCount = document.getElementById('loadedCount');
        const successAlert = document.getElementById('successAlert');
        const errorAlert = document.getElementById('errorAlert');

        // Sample property URLs - in real implementation, this would come from your database or API
        // These will be fetched dynamically from the PropertyController

        // Sync button handler
        syncBtn.addEventListener('click', async () => {
            await syncAllProperties();
        });

        // Sync all properties with OPTIMIZED concurrent progressive loading
        async function syncAllProperties() {
            try {
                // Hide empty state
                emptyState.classList.remove('active');
                successAlert.classList.remove('active');
                errorAlert.classList.remove('active');
                
                // Show loading
                loading.classList.add('active');
                syncBtn.disabled = true;

                // Fetch all property URLs (instant if cached)
                showAlert('success', 'Loading property URLs...');
                
                const url = window.searchContext 
                    ? `/api/internal-property/fetch-urls?search_id=${window.searchContext.id}` 
                    : '/api/internal-property/fetch-urls';
                
                const urlsResponse = await fetch(url);
                
                if (!urlsResponse.ok) {
                    throw new Error(`HTTP error! status: ${urlsResponse.status}`);
                }
                
                const urlsData = await urlsResponse.json();

                if (!urlsData.success || !urlsData.urls || urlsData.urls.length === 0) {
                    throw new Error(urlsData.message || urlsData.hint || 'No property URLs found');
                }
                
                // Show cache status if available
                if (urlsData.cached) {
                    showAlert('success', `Loaded ${urlsData.urls.length} properties from cache`);
                }

                propertyUrls = urlsData.urls;
                
                // Show stats
                statsBar.style.display = 'flex';
                totalCount.textContent = propertyUrls.length;
                loadedCount.textContent = '0';

                // Instantly display all properties with placeholders
                loadedProperties = propertyUrls.map((urlData, index) => ({
                    id: urlData.id || index,
                    url: urlData.url,
                    title: urlData.title || 'Property for sale',
                    price: urlData.price || 'Price on request',
                    address: urlData.address || 'Bath, Somerset',
                    property_type: '',
                    bedrooms: '',
                    bathrooms: '',
                    size: '',
                    tenure: '',
                    reduced_on: '',
                    images: [],
                    loading: true
                }));

                displayProperties(loadedProperties);
                loading.classList.remove('active');
                showAlert('success', `Showing ${loadedProperties.length} properties. Loading details...`);

                // Load details with CONCURRENT batching for MAXIMUM SPEED
                await loadDetailsConcurrently(propertyUrls);

            } catch (error) {
                console.error('Error syncing properties:', error);
                showAlert('error', error.message || 'An error occurred while syncing properties');
                emptyState.classList.add('active');
                loading.classList.remove('active');
                syncBtn.disabled = false;
            }
        }

        // Load property details with CONCURRENT batches (NEW OPTIMIZED APPROACH)
        async function loadDetailsConcurrently(urls) {
            const batchSize = 50; // Larger batches since backend is concurrent
            const maxConcurrent = 3; // Process 3 batches at the same time!
            let processed = 0;
            const totalBatches = Math.ceil(urls.length / batchSize);
            
            console.log(`Starting concurrent loading: ${urls.length} properties in ${totalBatches} batches, ${maxConcurrent} at a time`);

            // Process batches with concurrency limit
            for (let i = 0; i < totalBatches; i += maxConcurrent) {
                // Create array of batch promises (up to maxConcurrent)
                const batchPromises = [];
                
                for (let j = 0; j < maxConcurrent && (i + j) < totalBatches; j++) {
                    const batchIndex = i + j;
                    const startIdx = batchIndex * batchSize;
                    const endIdx = Math.min(startIdx + batchSize, urls.length);
                    const batch = urls.slice(startIdx, endIdx);
                    
                    // Create promise for this batch
                    const batchPromise = fetchBatch(batch, batchIndex + 1, totalBatches)
                        .then(result => {
                            if (result.success && result.properties) {
                                // Update properties with full details
                                result.properties.forEach(prop => {
                                    const index = loadedProperties.findIndex(p => p.url === prop.url);
                                    if (index !== -1) {
                                        loadedProperties[index] = {...loadedProperties[index], ...prop, loading: false};
                                        updatePropertyCard(loadedProperties[index]);
                                        processed++;
                                        loadedCount.textContent = processed;
                                    }
                                });
                            }
                            return result;
                        })
                        .catch(err => {
                            console.error(`Batch ${batchIndex + 1} failed:`, err);
                            return { success: false, error: err.message };
                        });
                    
                    batchPromises.push(batchPromise);
                }
                
                // Wait for all concurrent batches to complete
                console.log(`Processing batches ${i + 1} to ${Math.min(i + maxConcurrent, totalBatches)} concurrently...`);
                await Promise.all(batchPromises);
            }

            syncBtn.disabled = false;
            showAlert('success', `All ${processed} properties loaded successfully! 🎉`);
        }

        // Fetch a single batch of properties
        async function fetchBatch(batch, batchNum, totalBatches) {
            console.log(`Fetching batch ${batchNum}/${totalBatches} (${batch.length} properties)`);
            
            try {
                const response = await fetch('/api/internal-property/fetch-all', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ urls: batch })
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const data = await response.json();
                console.log(`Batch ${batchNum}/${totalBatches} completed:`, data.processed, 'properties');
                
                return data;
                
            } catch (error) {
                console.error(`Batch ${batchNum} error:`, error);
                throw error;
            }
        }

        // Update a single property card with new data
        function updatePropertyCard(property) {
            const card = document.getElementById(`card-${property.id}`);
            if (card) {
                const newCardHTML = createPropertyCard(property, 0);
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = newCardHTML;
                card.parentNode.replaceChild(tempDiv.firstElementChild, card);
            }
        }

        // Display properties in grid
        function displayProperties(props) {
            propertiesGrid.innerHTML = props.map((property, index) => {
                currentImageIndexes[property.id] = 0;
                return createPropertyCard(property, index);
            }).join('');

            // Add event listeners for image navigation
            props.forEach(property => {
                const prevBtn = document.getElementById(`prev-${property.id}`);
                const nextBtn = document.getElementById(`next-${property.id}`);

                if (prevBtn) {
                    prevBtn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        navigateImage(property.id, -1, property.images.length);
                    });
                }

                if (nextBtn) {
                    nextBtn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        navigateImage(property.id, 1, property.images.length);
                    });
                }

                // Card click to view original listing
                const card = document.getElementById(`card-${property.id}`);
                if (card) {
                    card.addEventListener('click', () => {
                        window.open(property.url || property.original_data?.url, '_blank');
                    });
                }
            });
        }

        // Create property card HTML - matches screenshot design exactly
        function createPropertyCard(property, index) {
            const hasImages = property.images && property.images.length > 0;
            const imageCount = hasImages ? property.images.length : 0;
            const loadingClass = property.loading ? 'loading' : '';
            
            // Use first image or placeholder
            const mainImage = hasImages ? property.images[0] : `https://via.placeholder.com/600x400/e0e0e0/666666?text=Loading+Image`;

            return `
                <div class="property-card ${loadingClass}" id="card-${property.id}" style="animation-delay: ${index * 0.01}s;">
                    <div class="property-image-section">
                        <div class="image-slider-wrapper">
                            <div class="image-slides" id="slides-${property.id}">
                                ${hasImages ? 
                                    property.images.map((img, idx) => `
                                        <div class="image-slide ${idx === 0 ? 'active' : ''}">
                                            <img src="${img}" alt="Property image" loading="lazy" onerror="this.src='https://via.placeholder.com/600x400/e0e0e0/666666?text=Image+Not+Found'">
                                        </div>
                                    `).join('') : 
                                    `<div class="image-slide active">
                                        <img src="${mainImage}" alt="Loading...">
                                    </div>`
                                }
                            </div>
                            ${hasImages && imageCount > 1 ? `
                                <button class="nav-arrow left" onclick="event.stopPropagation(); navigateSlide('${property.id}', -1, ${imageCount})">‹</button>
                                <button class="nav-arrow right" onclick="event.stopPropagation(); navigateSlide('${property.id}', 1, ${imageCount})">›</button>
                            ` : ''}
                        </div>
                    </div>
                    
                    <div class="property-info-section">
                        <h3 class="property-address-title">${property.address}</h3>
                        
                        <div class="property-price-section">
                            <span class="price-amount">${property.price}</span>
                            ${property.reduced_on ? `<span class="info-icon" title="Price information">ⓘ</span>` : ''}
                        </div>
                        
                        ${property.reduced_on ? `
                            <div class="reduced-date">Reduced on ${property.reduced_on}</div>
                        ` : ''}
                        
                        <div class="property-details-grid">
                            ${property.property_type ? `
                                <div class="detail-item">
                                    <div class="detail-label">PROPERTY TYPE</div>
                                    <div class="detail-value">
                                        <svg class="detail-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                                        </svg>
                                        <span>${property.property_type}</span>
                                    </div>
                                </div>
                            ` : `
                                <div class="detail-item skeleton">
                                    <div class="detail-label">PROPERTY TYPE</div>
                                    <div class="detail-value">Loading...</div>
                                </div>
                            `}
                            
                            ${property.bedrooms || !property.loading ? `
                                <div class="detail-item">
                                    <div class="detail-label">BEDROOMS</div>
                                    <div class="detail-value">
                                        <svg class="detail-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                                        </svg>
                                        <span>${property.bedrooms || '-'}</span>
                                    </div>
                                </div>
                            ` : `
                                <div class="detail-item skeleton">
                                    <div class="detail-label">BEDROOMS</div>
                                    <div class="detail-value">Loading...</div>
                                </div>
                            `}
                            
                            ${property.bathrooms || !property.loading ? `
                                <div class="detail-item">
                                    <div class="detail-label">BATHROOMS</div>
                                    <div class="detail-value">
                                        <svg class="detail-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                        </svg>
                                        <span>${property.bathrooms || '-'}</span>
                                    </div>
                                </div>
                            ` : `
                                <div class="detail-item skeleton">
                                    <div class="detail-label">BATHROOMS</div>
                                    <div class="detail-value">Loading...</div>
                                </div>
                            `}
                            
                            ${property.size || !property.loading ? `
                                <div class="detail-item">
                                    <div class="detail-label">SIZE</div>
                                    <div class="detail-value">
                                        <svg class="detail-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"></path>
                                        </svg>
                                        <span>${property.size || 'Ask agent'}</span>
                                    </div>
                                </div>
                            ` : `
                                <div class="detail-item skeleton">
                                    <div class="detail-label">SIZE</div>
                                    <div class="detail-value">Loading...</div>
                                </div>
                            `}
                            
                            ${property.tenure || !property.loading ? `
                                <div class="detail-item">
                                    <div class="detail-label">TENURE <span class="info-icon">ⓘ</span></div>
                                    <div class="detail-value">
                                        <span>${property.tenure || 'Freehold'}</span>
                                    </div>
                                </div>
                            ` : `
                                <div class="detail-item skeleton">
                                    <div class="detail-label">TENURE</div>
                                    <div class="detail-value">Loading...</div>
                                </div>
                            `}
                        </div>
                        
                        <a href="${property.url}" target="_blank" class="view-btn" onclick="event.stopPropagation()">
                            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                            </svg>
                            View on Rightmove
                        </a>
                    </div>
                </div>
            `;
        }
        
        // Navigate image slides
        function navigateSlide(propertyId, direction, totalImages) {
            const slidesContainer = document.getElementById(`slides-${propertyId}`);
            if (!slidesContainer) return;
            
            const slides = slidesContainer.querySelectorAll('.image-slide');
            let currentIndex = Array.from(slides).findIndex(slide => slide.classList.contains('active'));
            
            slides[currentIndex].classList.remove('active');
            
            currentIndex = (currentIndex + direction + totalImages) % totalImages;
            slides[currentIndex].classList.add('active');
        }
        
        // Share property
        function shareProperty(url) {
            if (navigator.share) {
                navigator.share({ url: url });
            } else {
                navigator.clipboard.writeText(url);
                alert('Link copied to clipboard!');
            }
        }
        
        // Toggle favorite
        function toggleFavorite(propertyId) {
            const btn = event.currentTarget;
            btn.classList.toggle('active');
        }

        // Navigate images
        function navigateImage(propertyId, direction, totalImages) {
            const currentIndex = currentImageIndexes[propertyId];
            let newIndex = currentIndex + direction;

            if (newIndex < 0) newIndex = totalImages - 1;
            if (newIndex >= totalImages) newIndex = 0;

            currentImageIndexes[propertyId] = newIndex;

            const slider = document.getElementById(`slider-${propertyId}`);
            const counter = document.getElementById(`counter-${propertyId}`);

            if (slider) {
                slider.style.transform = `translateX(-${newIndex * 100}%)`;
            }

            if (counter) {
                counter.textContent = `${newIndex + 1} / ${totalImages}`;
            }
        }

        // Alert helper
        function showAlert(type, message) {
            const alert = type === 'success' ? successAlert : errorAlert;
            alert.textContent = message;
            alert.classList.add('active');
            
            setTimeout(() => {
                alert.classList.remove('active');
            }, 5000);
        }
    </script>
</body>
</html>
