@extends('layouts.app')

@section('styles')
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: hsl(220, 85%, 50%);
            --primary-light: hsl(220, 85%, 96%);
            --secondary: hsl(250, 70%, 55%);
            --success: hsl(142, 70%, 45%);
            --error: hsl(0, 70%, 55%);
            --bg: hsl(0, 0%, 98%);
            --card-bg: hsl(0, 0%, 100%);
            --card-border: hsl(0, 0%, 88%);
            --text-primary: hsl(0, 0%, 15%);
            --text-secondary: hsl(0, 0%, 45%);
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.08);
            --shadow-md: 0 2px 8px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 4px 16px rgba(0, 0, 0, 0.12);
            --teal: hsl(170, 85%, 35%);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg);
            color: var(--text-primary);
            min-height: 100vh;
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
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

        .subtitle {
            color: var(--text-secondary);
            font-size: 1rem;
            margin-top: 0.25rem;
        }

        /* Buttons */
        .btn {
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: var(--shadow-sm);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-1px);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-secondary {
            background: var(--secondary);
            color: white;
        }

        .btn-teal {
            background: var(--teal);
            color: white;
        }

        .btn-danger {
            background: var(--error);
            color: white;
        }

        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.75rem;
        }

        /* Cards */
        .card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-sm);
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1.25rem;
            color: var(--text-primary);
        }

        /* Form Elements */
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-label {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-secondary);
        }

        .form-control {
            padding: 0.75rem 1rem;
            border: 1px solid var(--card-border);
            border-radius: 8px;
            font-size: 0.95rem;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
            background: white;
        }

        .form-control:focus {
            border-color: var(--teal);
            box-shadow: 0 0 0 3px hsla(170, 85%, 35%, 0.15);
        }

        /* Property Types */
        .property-types {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .property-type-checkbox {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }

        .property-type-checkbox input {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--teal);
        }

        .property-type-checkbox span {
            font-size: 0.875rem;
            color: var(--text-primary);
        }

        /* Table */
        .table-wrapper {
            overflow-x: auto;
            overflow-y: auto;
            max-height: 500px;
            border-radius: 8px;
            border: 1px solid var(--card-border);
        }

        table {
            width: 100%;
            min-width: 1000px;
            border-collapse: collapse;
        }

        /* Property Type Link Style */
        .property-type-link {
            color: var(--teal);
            cursor: pointer;
            text-decoration: underline;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 150px;
            display: inline-block;
        }

        .property-type-link:hover {
            color: hsl(170, 85%, 30%);
        }

        thead {
            background: linear-gradient(to bottom, var(--teal), hsl(170, 85%, 30%));
        }

        th {
            padding: 1rem;
            text-align: left;
            font-size: 0.75rem;
            font-weight: 600;
            color: white;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            padding: 1rem;
            font-size: 0.875rem;
            border-bottom: 1px solid var(--card-border);
            color: var(--text-primary);
        }

        tbody tr:hover {
            background: var(--primary-light);
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .text-muted {
            color: var(--text-secondary);
        }

        .capitalize {
            text-transform: capitalize;
        }

        /* Actions */
        .actions {
            display: flex;
            gap: 0.5rem;
        }

        /* Alert */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: none;
            align-items: center;
            gap: 0.75rem;
            animation: slideIn 0.3s ease-out;
        }

        .alert.active {
            display: flex;
        }

        .alert-success {
            background: hsla(142, 70%, 45%, 0.15);
            border: 1px solid var(--success);
            color: hsl(142, 70%, 35%);
        }

        .alert-error {
            background: hsla(0, 70%, 55%, 0.15);
            border: 1px solid var(--error);
            color: hsl(0, 70%, 45%);
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Spinner */
        .spinner {
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-top: 3px solid white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--text-secondary);
        }

        .empty-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* Form Actions */
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--card-border);
        }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 1rem;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-content {
            background: var(--card-bg);
            border-radius: 16px;
            width: 100%;
            max-width: 700px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-lg);
            animation: modalSlideIn 0.3s ease-out;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-20px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--card-border);
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--text-secondary);
            cursor: pointer;
            padding: 0.25rem;
            line-height: 1;
            transition: color 0.2s;
        }

        .modal-close:hover {
            color: var(--error);
        }

        .modal-body {
            padding: 1.5rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .title {
                font-size: 1.75rem;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .modal-content {
                max-height: 85vh;
            }
        }
    </style>
@endsection

@section('content')
        <!-- Header with Add Button -->
        <div class="header">
            <div>
                <h1 class="title">Search Properties</h1>
                <p class="subtitle">Create and save Rightmove property searches</p>
            </div>
            <button type="button" class="btn btn-teal" id="openModalBtn">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Add New Search
            </button>
        </div>

        <!-- Alerts -->
        <div class="alert alert-success" id="successAlert"></div>
        <div class="alert alert-error" id="errorAlert"></div>

        <!-- Search Form Modal -->
        <div class="modal-overlay" id="searchModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title" id="modalTitle">Create New Search</h2>
                    <button type="button" class="modal-close" id="closeModalBtn">&times;</button>
                </div>
                <div class="modal-body">
                    <!-- Hidden field for editing -->
                    <input type="hidden" id="editSearchId" value="">
                    
                    <!-- Row 1: Area Input -->
            <div class="form-row">
                <div class="form-group" style="grid-column: span 2;">
                    <label class="form-label">Enter Area / Region <span style="color: var(--error);">*</span></label>
                    <input type="text" id="areaInput" class="form-control" placeholder="Type area name (e.g., Birmingham, London, Manchester)">
                    <input type="hidden" id="areaIdentifier" value="">
                    <input type="hidden" id="areaName" value="">
                </div>
            </div>

            <!-- Row 2: Price Range -->
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Min Price (£)</label>
                    <select id="minPrice" class="form-control">
                        <option value="">No min</option>
                        <option value="50000">£50,000</option>
                        <option value="60000">£60,000</option>
                        <option value="70000">£70,000</option>
                        <option value="80000">£80,000</option>
                        <option value="90000">£90,000</option>
                        <option value="100000">£100,000</option>
                        <option value="125000">£125,000</option>
                        <option value="150000">£150,000</option>
                        <option value="175000">£175,000</option>
                        <option value="200000">£200,000</option>
                        <option value="250000">£250,000</option>
                        <option value="300000">£300,000</option>
                        <option value="400000">£400,000</option>
                        <option value="500000">£500,000</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Max Price (£)</label>
                    <select id="maxPrice" class="form-control">
                        <option value="">No max</option>
                        <option value="50000">£50,000</option>
                        <option value="60000">£60,000</option>
                        <option value="70000">£70,000</option>
                        <option value="80000">£80,000</option>
                        <option value="90000">£90,000</option>
                        <option value="100000">£100,000</option>
                        <option value="125000">£125,000</option>
                        <option value="150000">£150,000</option>
                        <option value="175000">£175,000</option>
                        <option value="200000">£200,000</option>
                        <option value="250000">£250,000</option>
                        <option value="300000">£300,000</option>
                        <option value="400000">£400,000</option>
                        <option value="500000">£500,000</option>
                    </select>
                </div>
            </div>

            <!-- Row 3: Bedrooms -->
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Min Bedrooms</label>
                    <select id="minBedrooms" class="form-control">
                        <option value="">No min</option>
                        <option value="1">1</option>
                        <option value="2">2</option>
                        <option value="3">3</option>
                        <option value="4">4</option>
                        <option value="5">5</option>
                        <option value="6">6</option>
                        <option value="7">7</option>
                        <option value="8">8</option>
                        <option value="9">9</option>
                        <option value="10">10</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Max Bedrooms</label>
                    <select id="maxBedrooms" class="form-control">
                        <option value="">No max</option>
                        <option value="1">1</option>
                        <option value="2">2</option>
                        <option value="3">3</option>
                        <option value="4">4</option>
                        <option value="5">5</option>
                        <option value="6">6</option>
                        <option value="7">7</option>
                        <option value="8">8</option>
                        <option value="9">9</option>
                        <option value="10">10</option>
                    </select>
                </div>
            </div>

            <!-- Row 4: Bathrooms -->
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Min Bathrooms</label>
                    <select id="minBathrooms" class="form-control">
                        <option value="">No min</option>
                        <option value="1">1</option>
                        <option value="2">2</option>
                        <option value="3">3</option>
                        <option value="4">4</option>
                        <option value="5">5</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Max Bathrooms</label>
                    <select id="maxBathrooms" class="form-control">
                        <option value="">No max</option>
                        <option value="1">1</option>
                        <option value="2">2</option>
                        <option value="3">3</option>
                        <option value="4">4</option>
                        <option value="5">5</option>
                    </select>
                </div>
            </div>

            <!-- Include Under Offer, Sold STC -->
            <div class="form-row" style="margin-top: 1rem;">
                <div class="form-group" style="grid-column: span 2;">
                    <label class="property-type-checkbox" style="font-size: 0.9rem;">
                        <input type="checkbox" id="includeSSTC" checked>
                        <span>Include Under Offer, Sold STC</span>
                    </label>
                </div>
            </div>

            <!-- Row 5: Property Types -->
            <div class="form-group" style="margin-top: 0.5rem;">
                <label class="form-label">Property Types</label>
                <div class="property-types">
                    <label class="property-type-checkbox">
                        <input type="checkbox" name="propertyType" value="flat">
                        <span>Flat</span>
                    </label>
                    <label class="property-type-checkbox">
                        <input type="checkbox" name="propertyType" value="bungalow">
                        <span>Bungalow</span>
                    </label>
                    <label class="property-type-checkbox">
                        <input type="checkbox" name="propertyType" value="land">
                        <span>Land</span>
                    </label>
                    <label class="property-type-checkbox">
                        <input type="checkbox" name="propertyType" value="terraced">
                        <span>Terraced</span>
                    </label>
                    <label class="property-type-checkbox">
                        <input type="checkbox" name="propertyType" value="semi-detached">
                        <span>Semi-detached</span>
                    </label>
                    <label class="property-type-checkbox">
                        <input type="checkbox" name="propertyType" value="detached">
                        <span>Detached</span>
                    </label>
                    <label class="property-type-checkbox">
                        <input type="checkbox" name="propertyType" value="park-home">
                        <span>Park Home</span>
                    </label>
                </div>
            </div>

            <!-- Row 5: Date Added -->
            <div class="form-row" style="margin-top: 1rem;">
                <div class="form-group">
                    <label class="form-label">Date Added</label>
                    <select id="maxDaysSinceAdded" class="form-control">
                        <option value="1">Last 24 hours</option>
                        <option value="3">Last 3 days</option>
                        <option value="7">Last 7 days</option>
                        <option value="14">Last 14 days</option>
                        <option value="">Anytime</option>
                    </select>
                </div>
            </div>

            <!-- Row 6: Tenure Types -->
            <div class="form-row" style="margin-top: 1rem;">
                <div class="form-group" style="grid-column: span 2;">
                    <label class="form-label">Tenure Types</label>
                    <div class="checkbox-group">
                        <label class="property-type-checkbox">
                            <input type="checkbox" name="tenureType" value="FREEHOLD">
                            <span>Freehold</span>
                        </label>
                        <label class="property-type-checkbox">
                            <input type="checkbox" name="tenureType" value="LEASEHOLD">
                            <span>Leasehold</span>
                        </label>
                        <label class="property-type-checkbox">
                            <input type="checkbox" name="tenureType" value="SHARE_OF_FREEHOLD">
                            <span>Share of Freehold</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Row 7: Must Haves -->
            <div class="form-row" style="margin-top: 1rem;">
                <div class="form-group" style="grid-column: span 2;">
                    <label class="form-label">Must Haves</label>
                    <div class="checkbox-group">
                        <label class="property-type-checkbox">
                            <input type="checkbox" name="mustHave" value="garden">
                            <span>Garden</span>
                        </label>
                        <label class="property-type-checkbox">
                            <input type="checkbox" name="mustHave" value="parking">
                            <span>Parking</span>
                        </label>
                        <label class="property-type-checkbox">
                            <input type="checkbox" name="mustHave" value="newHome">
                            <span>New Home</span>
                        </label>
                        <label class="property-type-checkbox">
                            <input type="checkbox" name="mustHave" value="retirementHome">
                            <span>Retirement Home</span>
                        </label>
                        <label class="property-type-checkbox">
                            <input type="checkbox" name="mustHave" value="sharedOwnership">
                            <span>Buying Schemes</span>
                        </label>
                        <label class="property-type-checkbox">
                            <input type="checkbox" name="mustHave" value="auction">
                            <span>Auction Property</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Row 8: Don't Show -->
            <div class="form-row" style="margin-top: 1rem;">
                <div class="form-group" style="grid-column: span 2;">
                    <label class="form-label">Don't Show</label>
                    <div class="checkbox-group">
                        <label class="property-type-checkbox">
                            <input type="checkbox" name="dontShow" value="newHome">
                            <span>New Home</span>
                        </label>
                        <label class="property-type-checkbox">
                            <input type="checkbox" name="dontShow" value="retirement">
                            <span>Retirement Home</span>
                        </label>
                        <label class="property-type-checkbox">
                            <input type="checkbox" name="dontShow" value="sharedOwnership">
                            <span>Buying Schemes</span>
                        </label>
                        <label class="property-type-checkbox">
                            <input type="checkbox" name="dontShow" value="auction">
                            <span>Auction Property</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Generated URL Section -->
            <div class="form-row" style="margin-top: 1.5rem;">
                <div class="form-group" style="grid-column: span 2;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                        <label class="form-label" style="margin: 0;">Rightmove URL <span style="color: var(--error);">*</span></label>
                        <div style="display: flex; gap: 0.5rem;">
                            <button type="button" id="generateUrlBtn" class="btn btn-teal btn-sm" style="padding: 0.4rem 0.75rem; font-size: 0.8rem;">
                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="vertical-align: middle; margin-right: 0.25rem;">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                                </svg>
                                Generate URL
                            </button>
                            <a id="formVisitUrlBtn" href="#" target="_blank" class="btn btn-primary btn-sm" style="padding: 0.4rem 0.75rem; font-size: 0.8rem; display: none; align-items: center;">
                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right: 0.25rem;">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                </svg>
                                Visit URL
                            </a>
                        </div>
                    </div>
                    <textarea id="generatedUrlInput" class="form-control" rows="3" placeholder="Click 'Generate URL' to create URL mostly or paste a URL manually..." style="font-family: monospace; font-size: 0.85rem; resize: vertical; min-height: 80px;"></textarea>
                    <p style="margin-top: 0.5rem; font-size: 0.75rem; color: var(--text-secondary);">You can edit the URL manually or generate it from your selected options above.</p>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="form-actions">
                <button type="button" class="btn" style="background: var(--text-secondary); color: white;" id="cancelModalBtn">Cancel</button>
                <button type="button" class="btn btn-teal" id="saveSearchBtn">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path>
                    </svg>
                    <span id="saveButtonText">Save Search</span>
                </button>
            </div>
                </div><!-- /.modal-body -->
            </div><!-- /.modal-content -->
        </div><!-- /.modal-overlay -->

        <!-- Saved Searches Table Card -->
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.25rem;">
                <h2 class="card-title" style="margin-bottom: 0;">Your Saved Searches</h2>
                <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                    <!-- Search by Location -->
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <label for="locationSearch" style="font-size: 0.875rem; font-weight: 500; color: var(--text-secondary);">Search:</label>
                        <input type="text" id="locationSearch" class="form-control" placeholder="Filter by location..." style="width: 200px; padding: 0.5rem 0.75rem;">
                    </div>
                    <!-- Per Page Selector -->
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <label for="perPageSelect" style="font-size: 0.875rem; font-weight: 500; color: var(--text-secondary);">Show:</label>
                        <select id="perPageSelect" class="form-control" style="width: auto; padding: 0.5rem 0.75rem;">
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Area</th>
                            <th>Price Range</th>
                            <th>Bedrooms</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="searchesBody">
                        <tr>
                            <td colspan="5" class="empty-state">
                                <div class="empty-icon">🔍</div>
                                <p>Loading saved searches...</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <!-- Pagination Controls -->
            <div id="paginationControls" style="display: flex; justify-content: space-between; align-items: center; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--card-border); flex-wrap: wrap; gap: 0.75rem;">
                <div id="paginationInfo" style="font-size: 0.875rem; color: var(--text-secondary);">
                    Showing 0 of 0 results
                </div>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <button type="button" id="prevPageBtn" class="btn btn-sm" style="background: var(--text-secondary); color: white;" disabled>
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="vertical-align: middle;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg>
                        Previous
                    </button>
                    <span id="pageIndicator" style="font-size: 0.875rem; color: var(--text-primary); padding: 0 0.5rem;">Page 1 of 1</span>
                    <button type="button" id="nextPageBtn" class="btn btn-sm" style="background: var(--text-secondary); color: white;" disabled>
                        Next
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="vertical-align: middle;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Property Type Modal -->
        <div class="modal-overlay" id="propertyTypeModal">
            <div class="modal-content" style="max-width: 400px;">
                <div class="modal-header">
                    <h2 class="modal-title">Property Types</h2>
                    <button type="button" class="modal-close" id="closePropertyTypeModal">&times;</button>
                </div>
                <div class="modal-body" id="propertyTypeModalBody">
                    <!-- Property types will be displayed here -->
                </div>
            </div>
        </div>

        <!-- URL Preview Modal -->
        <div class="modal-overlay" id="urlPreviewModal">
            <div class="modal-content" style="max-width: 700px;">
                <div class="modal-header">
                    <h2 class="modal-title">Search URL</h2>
                    <button type="button" class="modal-close" id="closeUrlPreviewModal">&times;</button>
                </div>
                <div class="modal-body">
                    <p style="margin-bottom: 1rem; color: var(--text-secondary); font-size: 0.875rem;">This is the Rightmove URL for your saved search:</p>
                    
                    <!-- Read-only URL display -->
                    <div id="urlPreviewContainer">
                        <div id="urlPreviewText" style="background: var(--bg-secondary); padding: 1rem; border-radius: 8px; font-family: monospace; font-size: 0.8rem; color: var(--text-primary); max-height: 150px; overflow-x: auto; overflow-y: auto; white-space: nowrap;"></div>
                    </div>
                    
                    <!-- Editable URL textarea (hidden by default) -->
                    <div id="urlEditContainer" style="display: none;">
                        <textarea id="urlEditTextarea" style="width: 100%; height: 150px; background: var(--bg-secondary); padding: 1rem; border-radius: 8px; font-family: monospace; font-size: 0.8rem; color: var(--text-primary); border: 2px solid var(--teal); resize: vertical;"></textarea>
                    </div>
                    
                    <input type="hidden" id="urlPreviewSearchId" value="">
                    
                    <div style="margin-top: 1.5rem; display: flex; justify-content: center; gap: 1rem; flex-wrap: wrap;">
                        <button id="editUrlBtn" class="btn" style="background: var(--text-secondary); color: white;">
                            Edit URL
                        </button>
                        <button id="updateUrlBtn" class="btn btn-primary" style="display: none;">
                            Update URL
                        </button>
                        <a id="previewVisitUrlBtn" href="#" target="_blank" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.5rem;">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 18px; height: 18px;">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                            </svg>
                            Visit URL
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div class="modal-overlay" id="deleteConfirmModal">
            <div class="modal-content" style="max-width: 400px;">
                <div class="modal-header" style="background: linear-gradient(to right, #dc3545, #c82333);">
                    <h2 class="modal-title">Confirm Delete</h2>
                    <button type="button" class="modal-close" id="closeDeleteConfirmModal">&times;</button>
                </div>
                <div class="modal-body" style="text-align: center;">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">⚠️</div>
                    <p style="margin-bottom: 0.5rem; font-weight: 600;">Are you sure you want to delete this search?</p>
                    <p id="deleteSearchName" style="color: var(--text-secondary); font-size: 0.875rem; margin-bottom: 1.5rem;"></p>
                    <input type="hidden" id="deleteSearchId" value="">
                    <div style="display: flex; gap: 1rem; justify-content: center;">
                        <button id="cancelDeleteBtn" class="btn" style="background: var(--text-secondary); color: white;">Cancel</button>
                        <button id="confirmDeleteBtn" class="btn btn-danger">Delete</button>
                    </div>
                </div>
            </div>
        </div>
@endsection

@section('scripts')

    <script>
        // CSRF Token Helper - Get the CSRF token from meta tag
        function getCsrfToken() {
            const meta = document.querySelector('meta[name="csrf-token"]');
            if (!meta) {
                console.error('CSRF token meta tag not found!');
                return '';
            }
            return meta.getAttribute('content') || meta.content || '';
        }

        // Elements
        const saveSearchBtn = document.getElementById('saveSearchBtn');
        const saveButtonText = document.getElementById('saveButtonText');
        const searchesBody = document.getElementById('searchesBody');
        const successAlert = document.getElementById('successAlert');
        const errorAlert = document.getElementById('errorAlert');
        
        // Modal Elements
        const searchModal = document.getElementById('searchModal');
        const openModalBtn = document.getElementById('openModalBtn');
        const closeModalBtn = document.getElementById('closeModalBtn');
        const cancelModalBtn = document.getElementById('cancelModalBtn');
        const modalTitle = document.getElementById('modalTitle');
        const editSearchId = document.getElementById('editSearchId');

        // Form Elements
        const areaInput = document.getElementById('areaInput');
        const areaIdentifier = document.getElementById('areaIdentifier');
        const areaName = document.getElementById('areaName');

        // Area Mapping (Name -> Region ID)
        const areaMapping = {
            'Aberdeen': 'REGION^18',
            'Altrincham': 'REGION^47',
            'Bath': 'REGION^116',
            'Belfast': 'REGION^143',
            'Birmingham': 'REGION^162',
            'Blackpool': 'REGION^171',
            'Bolton': 'REGION^179',
            'Bournemouth': 'REGION^185',
            'Bradford': 'REGION^194',
            'Brighton': 'REGION^204',
            'Bristol': 'REGION^219',
            'Cambridge': 'REGION^265',
            'Cardiff': 'REGION^277',
            'Cheltenham': 'REGION^353',
            'Chester': 'REGION^351',
            'Cornwall': 'REGION^24568',
            'Coventry': 'REGION^430',
            'Derby': 'REGION^453',
            'Devon': 'REGION^27195',
            'Edinburgh': 'REGION^550',
            'Essex': 'REGION^27180',
            'Exeter': 'REGION^517',
            'Glasgow': 'REGION^664',
            'Guildford': 'REGION^700',
            'Hampshire': 'REGION^27245',
            'Hull': 'REGION^755',
            'Kent': 'REGION^27738',
            'Leeds': 'REGION^802',
            'Leicester': 'REGION^806',
            'Liverpool': 'REGION^835',
            'London': 'REGION^93965',
            'Manchester': 'REGION^886',
            'Milton Keynes': 'REGION^928',
            'Newcastle Upon Tyne': 'REGION^910',
            'Norwich': 'REGION^983',
            'Nottingham': 'REGION^981',
            'Oxford': 'REGION^1051',
            'Plymouth': 'REGION^1092',
            'Portsmouth': 'REGION^1108',
            'Reading': 'REGION^1140',
            'Sheffield': 'REGION^1190',
            'Southampton': 'REGION^1234',
            'Surrey': 'REGION^27480',
            'Swansea': 'REGION^1268',
            'York': 'REGION^1425',
            'Yorkshire': 'REGION^27585'
        };

        // Update hidden identifier when user types (or when generating URL)
        areaInput.addEventListener('input', function() {
            const name = this.value.trim();
            // Case-insensitive lookup
            const foundName = Object.keys(areaMapping).find(key => key.toLowerCase() === name.toLowerCase());
            
            if (foundName) {
                areaIdentifier.value = areaMapping[foundName];
                areaName.value = foundName; // Normalize casing
            } else {
                areaIdentifier.value = '';
                areaName.value = name;
            }
        });

        const formVisitUrlBtn = document.getElementById('formVisitUrlBtn');
        const previewVisitUrlBtn = document.getElementById('previewVisitUrlBtn');
        
        // Restore missing element references
        const minPriceSelect = document.getElementById('minPrice');
        const maxPriceSelect = document.getElementById('maxPrice');
        const minBedroomsSelect = document.getElementById('minBedrooms');
        const maxBedroomsSelect = document.getElementById('maxBedrooms');
        const maxDaysSinceAddedSelect = document.getElementById('maxDaysSinceAdded');

        // Modal Open/Close
        openModalBtn.addEventListener('click', () => {
            resetForm();
            // Enable inputs for adding
            enableFormInputs();
            modalTitle.textContent = 'Create New Search';
            saveButtonText.textContent = 'Save Search';
            // Show Save button, hide Visit button
            saveSearchBtn.style.display = 'flex';
            formVisitUrlBtn.style.display = 'none';
            document.getElementById('generateUrlBtn').style.display = 'inline-flex';
            
            editSearchId.value = '';
            searchModal.classList.add('active');
        });

        closeModalBtn.addEventListener('click', closeModal);
        cancelModalBtn.addEventListener('click', closeModal);

        searchModal.addEventListener('click', (e) => {
            if (e.target === searchModal) closeModal();
        });

        function closeModal() {
            searchModal.classList.remove('active');
            resetForm();
            enableFormInputs(); // Reset to enabled state
        }

        function resetForm() {
            areaInput.value = '';
            areaIdentifier.value = '';
            areaName.value = '';
            // areaSelect removed
            // areaStatus removed
            if(minPriceSelect) minPriceSelect.value = '';
            if(maxPriceSelect) maxPriceSelect.value = '';
            if(minBedroomsSelect) minBedroomsSelect.value = '';
            if(maxBedroomsSelect) maxBedroomsSelect.value = '';
            if(maxDaysSinceAddedSelect) maxDaysSinceAddedSelect.value = '1';
            
            // Reset bathrooms
            const minBathroomsSelect = document.getElementById('minBathrooms');
            const maxBathroomsSelect = document.getElementById('maxBathrooms');
            if (minBathroomsSelect) minBathroomsSelect.value = '';
            if (maxBathroomsSelect) maxBathroomsSelect.value = '';
            
            // Reset include SSTC
            const includeSSTC = document.getElementById('includeSSTC');
            if (includeSSTC) includeSSTC.checked = true;
            
            document.querySelectorAll('input[name="propertyType"]').forEach(cb => {
                cb.checked = false;
            });
            // Reset tenure types
            document.querySelectorAll('input[name="tenureType"]').forEach(cb => {
                cb.checked = false;
            });
            // Reset must haves
            document.querySelectorAll('input[name="mustHave"]').forEach(cb => {
                cb.checked = false;
            });
            // Reset don't show
            document.querySelectorAll('input[name="dontShow"]').forEach(cb => {
                cb.checked = false;
            });
            editSearchId.value = '';
            
            // Reset generated URL input
            const generatedUrlInput = document.getElementById('generatedUrlInput');
            if (generatedUrlInput) generatedUrlInput.value = '';
        }

        function enableFormInputs() {
            const inputs = searchModal.querySelectorAll('input, select, textarea');
            inputs.forEach(input => {
                input.disabled = false;
            });
        }

        function disableFormInputs() {
            const inputs = searchModal.querySelectorAll('input, select, textarea');
            inputs.forEach(input => {
                // Keep close buttons enabled if they were inputs (though they are usually buttons)
                if (input.type !== 'button') {
                    input.disabled = true;
                }
            });
        }

        // Safe URL parameter extractor (handles Rightmove URLs with ^ better than new URL())
        function getParamFromUrl(url, param) {
            if (!url) return null;
            try {
                // Try standard URL parsing first (for well-formed URLs)
                const urlObj = new URL(url);
                const val = urlObj.searchParams.get(param);
                if (val !== null) return val;
            } catch (e) {
                // Fallback to regex for malformed URLs or those with ^
            }
            
            const results = new RegExp('[?&]' + param + '=([^&#]*)').exec(url);
            return results ? decodeURIComponent(results[1].replace(/\+/g, ' ')) : null;
        }

        // Show Alert
        function showAlert(type, message) {
            const alert = type === 'success' ? successAlert : errorAlert;
            alert.textContent = message;
            alert.classList.add('active');
            
            setTimeout(() => {
                alert.classList.remove('active');
            }, 5000);
        }

        // Build Rightmove URL from form inputs
        function buildRightmoveUrl() {
            const baseUrl = 'https://www.rightmove.co.uk/property-for-sale/find.html';
            const params = new URLSearchParams();

            // Location identifier (preferred) or Search Location (fallback)
            const locationId = areaIdentifier.value;
            const locationName = areaInput.value.trim();

            if (locationId) {
                params.append('locationIdentifier', locationId);
            } else if (locationName) {
                const cleanLocationName = locationName.split(',')[0].trim();
                params.append('searchLocation', cleanLocationName);
            }

            params.append('useLocationIdentifier', 'true');
            params.append('radius', '0.0');
            params.append('sortType', '2');

            if (minPriceSelect.value) {
                params.append('minPrice', minPriceSelect.value);
            }
            if (maxPriceSelect.value) {
                params.append('maxPrice', maxPriceSelect.value);
            }

            if (minBedroomsSelect.value) {
                params.append('minBedrooms', minBedroomsSelect.value);
            }
            if (maxBedroomsSelect.value) {
                params.append('maxBedrooms', maxBedroomsSelect.value);
            }

            const checkedTypes = document.querySelectorAll('input[name="propertyType"]:checked');
            if (checkedTypes.length > 0) {
                const types = Array.from(checkedTypes).map(cb => cb.value).join(',');
                params.append('propertyTypes', types);
            }

            const minBathroomsSelect = document.getElementById('minBathrooms');
            const maxBathroomsSelect = document.getElementById('maxBathrooms');
            if (minBathroomsSelect && minBathroomsSelect.value) {
                params.append('minBathrooms', minBathroomsSelect.value);
            }
            if (maxBathroomsSelect && maxBathroomsSelect.value) {
                params.append('maxBathrooms', maxBathroomsSelect.value);
            }

            const checkedTenures = document.querySelectorAll('input[name="tenureType"]:checked');
            if (checkedTenures.length > 0) {
                const tenures = Array.from(checkedTenures).map(cb => cb.value).join(',');
                params.append('tenure', tenures);
            }

            const includeSSTC = document.getElementById('includeSSTC');
            if (includeSSTC && includeSSTC.checked) {
                params.append('includeSSTC', 'true');
            }

            const checkedMustHaves = document.querySelectorAll('input[name="mustHave"]:checked');
            if (checkedMustHaves.length > 0) {
                const mustHaves = Array.from(checkedMustHaves).map(cb => cb.value).join(',');
                params.append('mustHave', mustHaves);
            }

            const checkedDontShow = document.querySelectorAll('input[name="dontShow"]:checked');
            if (checkedDontShow.length > 0) {
                const dontShows = Array.from(checkedDontShow).map(cb => cb.value).join(',');
                params.append('dontShow', dontShows);
            }

            const maxDaysSinceAddedSelect = document.getElementById('maxDaysSinceAdded');
            if (maxDaysSinceAddedSelect && maxDaysSinceAddedSelect.value) {
                params.append('maxDaysSinceAdded', maxDaysSinceAddedSelect.value);
            }

            return `${baseUrl}?${params.toString()}`;
        }

        // Generate URL button handler
        const generateUrlBtn = document.getElementById('generateUrlBtn');
        const generatedUrlInput = document.getElementById('generatedUrlInput');
        
        generateUrlBtn.addEventListener('click', async () => {
            // Validate that we have either an identifier or a name
            const locationId = areaIdentifier.value;
            let locationName = areaInput.value.trim();
            
            if (!locationId && !locationName) {
                showAlert('error', 'Please enter an area name first.');
                areaInput.focus();
                return;
            }

            // Show loading state
            generateUrlBtn.disabled = true;
            generateUrlBtn.textContent = 'Generating...';

            try {
                // If we don't have an ID but have a name, try to fetch the ID silently
                if (!locationId && locationName) {
                    // Clean the name (e.g. "Bromley, London" -> "Bromley") to improve lookup success
                    const cleanNameForLookup = locationName.split(',')[0].trim();
                    
                    try {
                        const response = await fetch("{{ route('searchproperties.check-area', [], false) }}", {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': getCsrfToken()
                            },
                            body: JSON.stringify({ area: cleanNameForLookup })
                        });
                        
                        const data = await response.json();
                        if (data.success && data.found) {
                            areaIdentifier.value = data.identifier;
                            areaName.value = data.name;
                            // Update locationName to the official name found
                            locationName = data.name; // This ensures buildRightmoveUrl uses the official name if fallback needed (though ID overrides)
                        }
                    } catch (err) {
                        console.warn('Silent area check failed, falling back to basic name', err);
                    }
                }
                
                const url = buildRightmoveUrl();
                generatedUrlInput.value = url;
                showAlert('success', 'URL generated successfully!');
                
            } catch (error) {
                showAlert('error', 'Error generating URL: ' + error.message);
            } finally {
                generateUrlBtn.disabled = false;
                generateUrlBtn.innerHTML = '<i class="fas fa-magic"></i> Generate URL';
            }
        });

        // Initial load
        function initApp() {
            console.log('Initializing app...');
            loadSearches(1);
        }

        if (document.readyState === 'complete' || document.readyState === 'interactive') {
            initApp();
        } else {
            document.addEventListener('DOMContentLoaded', initApp);
        }

        // Save/Update Search
        saveSearchBtn.addEventListener('click', async () => {
            // Validate Area Input
            const locationName = areaInput.value.trim();
            if (!locationName) {
                showAlert('error', 'Please enter an area name.');
                areaInput.focus();
                return;
            }

            // Get URL from the editable input, or generate if empty
            let url = generatedUrlInput.value.trim();
            
            // Auto-generate URL if missing but area is present
            if (!url) {
                saveSearchBtn.disabled = true;
                saveSearchBtn.innerHTML = '<span class="spinner"></span> Generating URL...';
                
                try {
                    // Try to fetch identifier silently if missing
                    if (!areaIdentifier.value) {
                        const cleanName = locationName.split(',')[0].trim();
                        const response = await fetch("{{ route('searchproperties.check-area', [], false) }}", {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': getCsrfToken()
                            },
                            body: JSON.stringify({ area: cleanName })
                        });
                        
                        const data = await response.json();
                        if (data.success && data.found) {
                            areaIdentifier.value = data.identifier;
                            areaName.value = data.name;
                        }
                    }
                    
                    url = buildRightmoveUrl();
                    generatedUrlInput.value = url;
                } catch (err) {
                    console.error('Auto-generation failed:', err);
                } finally {
                    saveSearchBtn.disabled = false;
                }
            }

            if (!url) {
                showAlert('error', 'Rightmove URL is mandatory. Please enter a URL or ensure the area is correct.');
                return;
            }
            
            const isEdit = editSearchId.value !== '';

            const originalContent = saveSearchBtn.innerHTML;
            saveSearchBtn.disabled = true;
            saveSearchBtn.innerHTML = '<span class="spinner"></span> Saving...';

            try {
                const endpoint = isEdit ? `{{ route('searchproperties.update', ['id' => 'REPLACE_ID'], false) }}`.replace('REPLACE_ID', editSearchId.value) : "{{ route('searchproperties.store', [], false) }}";
                const method = isEdit ? 'PUT' : 'POST';

                const response = await fetch(endpoint, {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken()
                    },
                    body: JSON.stringify({ 
                        updates_url: url,
                        area: areaInput.value.trim(),
                        min_price: minPriceSelect.value,
                        max_price: maxPriceSelect.value,
                        min_bed: minBedroomsSelect.value,
                        max_bed: maxBedroomsSelect.value,
                        min_bath: document.getElementById('minBathrooms')?.value || '',
                        max_bath: document.getElementById('maxBathrooms')?.value || '',
                        property_type: Array.from(document.querySelectorAll('input[name="propertyType"]:checked')).map(cb => cb.value).join(','),
                        tenure_types: Array.from(document.querySelectorAll('input[name="tenureType"]:checked')).map(cb => cb.value).join(','),
                        must_have: Array.from(document.querySelectorAll('input[name="mustHave"]:checked')).map(cb => cb.value).join(','),
                        dont_show: Array.from(document.querySelectorAll('input[name="dontShow"]:checked')).map(cb => cb.value).join(','),
                        max_days_since_added: document.getElementById('maxDaysSinceAdded')?.value || '',
                        include_sstc: (document.getElementById('includeSSTC')?.checked) ? 1 : 0
                    })
                });

                const data = await response.json();

                if (data.success) {
                    showAlert('success', isEdit ? 'Search updated successfully!' : 'Search saved successfully!');
                    closeModal();
                    loadSearches();
                } else {
                    showAlert('error', data.message || 'Failed to save search');
                }
            } catch (error) {
                showAlert('error', 'Network error: ' + error.message);
            } finally {
                saveSearchBtn.disabled = false;
                saveSearchBtn.innerHTML = originalContent;
            }
        });

        // Pagination State
        let currentPage = 1;
        let perPage = 10;
        let searchQuery = '';
        let paginationData = null;

        // Pagination Elements
        const locationSearchInput = document.getElementById('locationSearch');
        const perPageSelect = document.getElementById('perPageSelect');
        const prevPageBtn = document.getElementById('prevPageBtn');
        const nextPageBtn = document.getElementById('nextPageBtn');
        const pageIndicator = document.getElementById('pageIndicator');
        const paginationInfo = document.getElementById('paginationInfo');

        // Load Searches with Pagination
        async function loadSearches(page = 1) {
            // Ensure we have a valid page number
            page = parseInt(page, 10) || 1;
            console.log('loadSearches requested page:', page, 'current state:', { currentPage, searchQuery, perPage });
            
            currentPage = page;
            searchesBody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:2rem;">Loading...</td></tr>';

            try {
                const params = new URLSearchParams({
                    page: parseInt(currentPage, 10) || 1,
                    per_page: parseInt(perPage, 10) || 10
                });
                
                if (searchQuery) {
                    params.append('search', searchQuery);
                }

                const endpoint = "{{ route('searchproperties.all', [], false) }}";
                const response = await fetch(endpoint + "?" + params.toString(), {
                    headers: {
                        'Accept': 'application/json'
                    }
                });
                const data = await response.json();

                if (data.success && data.searches) {
                    paginationData = data.pagination;
                    
                    // Sync our local state with what the server returned
                    if (data.pagination) {
                        currentPage = parseInt(data.pagination.current_page, 10) || currentPage;
                        perPage = parseInt(data.pagination.per_page, 10) || perPage;
                    }

                    console.log('Pagination data received:', paginationData);
                    updatePaginationUI();

                    if (data.searches.length === 0) {
                        const emptyMessage = searchQuery 
                            ? `No searches found for "${searchQuery}"` 
                            : 'No saved searches yet';
                        searchesBody.innerHTML = `
                            <tr>
                                <td colspan="5" class="empty-state">
                                    <div class="empty-icon">📋</div>
                                    <p>${emptyMessage}</p>
                                    <p class="text-muted" style="font-size: 0.875rem;">Click "Add New Search" to create one</p>
                                </td>
                            </tr>
                        `;
                        return;
                    }

                    // Store searches globally for edit function
                    window.savedSearchesData = {};
                    
                    searchesBody.innerHTML = data.searches.map(search => {
                        // Store in global object
                        window.savedSearchesData[search.id] = search;
                        
                        const minPrice = search.min_price ? `£${parseInt(search.min_price).toLocaleString()}` : '0';
                        const maxPrice = search.max_price ? `£${parseInt(search.max_price).toLocaleString()}` : 'Max';
                        const minBed = search.min_bed || '0';
                        const maxBed = search.max_bed || 'Max';
                        const area = search.area ? search.area.replace(/\+/g, ' ') : 'Unknown';

                        const schedule = search.schedule;
                        let statusHtml = '<span class="badge" style="background: var(--text-secondary); color: white;">No Schedule</span>';
                        
                        if (schedule) {
                            let badgeStyle = `background: ${schedule.status_color}; color: white; display: inline-block; width: fit-content; padding: 0.2rem 0.6rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600;`;
                            
                            if (schedule.status_label === 'Completed') {
                                badgeStyle = 'background: hsla(142, 70%, 45%, 0.15); color: hsl(142, 70%, 35%); border: 1px solid hsla(142, 70%, 45%, 0.3); display: inline-block; width: fit-content; padding: 0.2rem 0.6rem; border-radius: 4px; font-size: 0.75rem; font-weight: 700;';
                            } else if (schedule.status_label === 'Importing') {
                                badgeStyle = 'background: hsla(170, 85%, 35%, 0.15); color: var(--teal); border: 1px solid hsla(170, 85%, 35%, 0.3); display: inline-block; width: fit-content; padding: 0.2rem 0.6rem; border-radius: 4px; font-size: 0.75rem; font-weight: 700;';
                            } else if (schedule.status_label === 'Pending') {
                                badgeStyle = 'background: hsla(35, 90%, 50%, 0.12); color: hsl(30, 85%, 40%); border: 1px solid hsla(35, 90%, 50%, 0.3); display: inline-block; width: fit-content; padding: 0.2rem 0.6rem; border-radius: 4px; font-size: 0.75rem; font-weight: 700;';
                            } else if (schedule.status_label === 'Failed') {
                                badgeStyle = 'background: hsla(0, 70%, 50%, 0.12); color: hsl(0, 70%, 45%); border: 1px solid hsla(0, 70%, 50%, 0.3); display: inline-block; width: fit-content; padding: 0.2rem 0.6rem; border-radius: 4px; font-size: 0.75rem; font-weight: 700;';
                            }
                            
                            statusHtml = `
                                <div style="display: flex; flex-direction: column; gap: 0.35rem;">
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <span class="badge" style="${badgeStyle}">
                                            ${schedule.status_label}
                                        </span>
                                        ${schedule.is_importing ? `<small style="font-size: 0.75rem; color: var(--text-secondary); font-weight: 700;">${schedule.progress}%</small>` : ''}
                                    </div>
                                    ${schedule.is_importing ? `
                                        <div class="progress-container" style="height: 4px; background: var(--bg-secondary); border-radius: 2px; width: 60px; overflow: hidden;">
                                            <div class="progress-bar" style="height: 100%; background: var(--teal); width: ${schedule.progress}%; transition: width 0.3s ease;"></div>
                                        </div>
                                    ` : ''}
                                </div>
                            `;
                        }

                        return `
                            <tr>
                                <td>${area}</td>
                                <td class="text-muted">${minPrice} - ${maxPrice}</td>
                                <td class="text-muted">${minBed} - ${maxBed} Beds</td>
                                <td>${statusHtml}</td>
                                <td>
                                    <div class="actions">
                                        <a href="/internal-properties/search/${search.id}" class="btn btn-sm" style="background: var(--primary); color: white; font-size: 0.75rem; padding: 0.25rem 0.5rem; text-decoration: none; display: inline-flex; align-items: center; gap: 0.25rem;">
                                            <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                            Property Details
                                        </a>
                                        <button onclick="viewSearch(${search.id})" class="btn btn-secondary btn-sm" title="View Search Details">View</button>
                                        <button onclick="editSearch(${search.id})" class="btn btn-primary btn-sm">Edit</button>
                                        <button onclick="confirmDelete(${search.id}, '${area.replace(/'/g, "\\'")}')" class="btn btn-danger btn-sm">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        `;
                    }).join('');
                } else {
                    searchesBody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:2rem;color:var(--error);">Failed to load searches</td></tr>';
                }
            } catch (error) {
                console.error('Load Error details:', error);
                searchesBody.innerHTML = `<tr><td colspan="5" style="text-align:center;padding:2rem;color:var(--error);">
                    <div>Error loading searches</div>
                    <div style="font-size:0.8em;color:var(--text-secondary);margin-top:0.5rem;">${error.message || error}</div>
                </td></tr>`;
            }
        }

        // Update Pagination UI
        function updatePaginationUI() {
            if (!paginationData) {
                console.log('updatePaginationUI: paginationData is null, skipping');
                return;
            }

            const { current_page, last_page, per_page, total, has_more_pages, has_previous_page } = paginationData;
            
            console.log('updatePaginationUI:', { current_page, last_page, has_more_pages, has_previous_page });
            
            // Update page indicator
            pageIndicator.textContent = `Page ${current_page} of ${last_page}`;
            
            // Update pagination info
            const start = total === 0 ? 0 : (current_page - 1) * per_page + 1;
            const end = Math.min(current_page * per_page, total);
            paginationInfo.textContent = `Showing ${start}-${end} of ${total} results`;
            
            // Update button states
            prevPageBtn.disabled = !has_previous_page;
            nextPageBtn.disabled = !has_more_pages;
            
            console.log('Button states - nextPageBtn.disabled:', nextPageBtn.disabled, 'prevPageBtn.disabled:', prevPageBtn.disabled);
            
            // Update button styles based on state
            prevPageBtn.style.opacity = has_previous_page ? '1' : '0.5';
            nextPageBtn.style.opacity = has_more_pages ? '1' : '0.5';
        }

        // Search Input Handler (debounced)
        let searchTimeout;
        locationSearchInput.addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                searchQuery = e.target.value.trim();
                currentPage = 1; // Reset to first page on new search
                loadSearches(1);
            }, 300);
        });

        // Per Page Selector Handler
        perPageSelect.addEventListener('change', (e) => {
            perPage = parseInt(e.target.value);
            currentPage = 1; // Reset to first page when changing per_page
            loadSearches(1);
        });

        // Previous Page Button Handler
        if (prevPageBtn) {
            prevPageBtn.addEventListener('click', () => {
                const targetPage = (parseInt(currentPage, 10) || 1) - 1;
                console.log('Previous button clicked', { currentPage, targetPage, paginationData });
                if (paginationData && paginationData.has_previous_page) {
                    loadSearches(targetPage);
                } else {
                    console.log('Previous navigation blocked - no previous page');
                }
            });
        } else {
            console.error('prevPageBtn element not found');
        }

        // Next Page Button Handler
        if (nextPageBtn) {
            nextPageBtn.addEventListener('click', () => {
                const targetPage = (parseInt(currentPage, 10) || 1) + 1;
                console.log('Next button clicked', { currentPage, targetPage, paginationData });
                if (paginationData && paginationData.has_more_pages) {
                    loadSearches(targetPage);
                } else {
                    console.log('Next navigation blocked - no more pages or paginationData is null');
                }
            });
        } else {
            console.error('nextPageBtn element not found');
        }

        const propertyTypeModal = document.getElementById('propertyTypeModal');
        const propertyTypeModalBody = document.getElementById('propertyTypeModalBody');

        // Start Queued Import
        async function startImport(scheduleId) {
            try {
                const response = await fetch(`{{ url('schedules', [], false) }}/${scheduleId}/start-queued`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken()
                    }
                });
                
                const data = await response.json();
                if (data.success) {
                    showAlert('success', 'Import started in background');
                    loadSearches(currentPage);
                    startPolling();
                } else {
                    showAlert('error', data.message || 'Failed to start import');
                }
            } catch (error) {
                showAlert('error', 'Error starting import: ' + error.message);
            }
        }

        // Auto-refresh when importing
        let pollingInterval = null;
        function startPolling() {
            if (pollingInterval) return;
            pollingInterval = setInterval(() => {
                const importingRows = document.querySelectorAll('.progress-bar');
                if (importingRows.length > 0) {
                    loadSearches(currentPage);
                } else {
                    stopPolling();
                }
            }, 5000);
        }

        function stopPolling() {
            if (pollingInterval) {
                clearInterval(pollingInterval);
                pollingInterval = null;
            }
        }

        // Note: editSearch, viewSearch, confirmDelete are defined directly on window later in the script
        // using `window.functionName = (id) => {...}` syntax
        window.startImport = startImport;
        
        // Start polling if needed on load
        startPolling();
        const closePropertyTypeModalBtn = document.getElementById('closePropertyTypeModal');

        // Property Type Modal Close
        closePropertyTypeModalBtn.addEventListener('click', () => {
            propertyTypeModal.classList.remove('active');
        });
        propertyTypeModal.addEventListener('click', (e) => {
            if (e.target === propertyTypeModal) propertyTypeModal.classList.remove('active');
        });

        // Show Property Types Modal
        window.showPropertyTypes = (types) => {
            const typesArray = types.split(', ').filter(t => t && t !== 'Any');
            if (typesArray.length === 0) {
                propertyTypeModalBody.innerHTML = '<p style="color: var(--text-secondary);">No specific property types selected</p>';
            } else {
                propertyTypeModalBody.innerHTML = `
                    <ul style="list-style: none; padding: 0; margin: 0;">
                        ${typesArray.map(t => `<li style="padding: 0.5rem 0; border-bottom: 1px solid var(--card-border); text-transform: capitalize;">${t}</li>`).join('')}
                    </ul>
                `;
            }
            propertyTypeModal.classList.add('active');
        };

        // URL Preview Modal Elements
        const urlPreviewModal = document.getElementById('urlPreviewModal');
        const urlPreviewText = document.getElementById('urlPreviewText');
        const urlPreviewContainer = document.getElementById('urlPreviewContainer');
        const urlEditContainer = document.getElementById('urlEditContainer');
        const urlEditTextarea = document.getElementById('urlEditTextarea');
        const urlPreviewSearchId = document.getElementById('urlPreviewSearchId');

        const editUrlBtn = document.getElementById('editUrlBtn');
        const updateUrlBtn = document.getElementById('updateUrlBtn');
        const closeUrlPreviewModalBtn = document.getElementById('closeUrlPreviewModal');

        // URL Preview Modal Close
        function closeUrlPreviewModal() {
            urlPreviewModal.classList.remove('active');
            // Reset to view mode
            urlPreviewContainer.style.display = 'block';
            urlEditContainer.style.display = 'none';
            editUrlBtn.style.display = 'inline-block';
            updateUrlBtn.style.display = 'none';
        }
        
        closeUrlPreviewModalBtn.addEventListener('click', closeUrlPreviewModal);
        urlPreviewModal.addEventListener('click', (e) => {
            if (e.target === urlPreviewModal) closeUrlPreviewModal();
        });

        // Show URL Preview Modal
        window.showUrlPreview = (url, searchId) => {
            urlPreviewText.textContent = url;
            urlEditTextarea.value = url;
            previewVisitUrlBtn.href = url;
            urlPreviewSearchId.value = searchId || '';
            urlPreviewModal.classList.add('active');
        };

        // Show URL Preview from data attributes (safer for URLs with special characters)
        window.showUrlPreviewFromData = (button) => {
            const url = decodeURIComponent(button.dataset.url);
            const searchId = button.dataset.id;
            showUrlPreview(url, searchId);
        };

        // Edit URL Button
        editUrlBtn.addEventListener('click', () => {
            urlPreviewContainer.style.display = 'none';
            urlEditContainer.style.display = 'block';
            editUrlBtn.style.display = 'none';
            updateUrlBtn.style.display = 'inline-block';
            urlEditTextarea.focus();
        });

        // Update URL Button
        updateUrlBtn.addEventListener('click', async () => {
            const newUrl = urlEditTextarea.value.trim();
            const searchId = urlPreviewSearchId.value;
            
            if (!newUrl) {
                showAlert('error', 'URL cannot be empty');
                return;
            }
            
            if (!searchId) {
                showAlert('error', 'Search ID not found');
                return;
            }

            updateUrlBtn.disabled = true;
            updateUrlBtn.textContent = 'Updating...';

            try {
                const response = await fetch(`{{ url('searchproperties/update') }}/${searchId}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken()
                    },
                    body: JSON.stringify({ updates_url: newUrl })
                });

                const data = await response.json();

                if (data.success) {
                    showAlert('success', 'URL updated successfully!');
                    closeUrlPreviewModal();
                    loadSearches();
                } else {
                    showAlert('error', data.message || 'Failed to update URL');
                }
            } catch (error) {
                showAlert('error', 'Network error: ' + error.message);
            } finally {
                updateUrlBtn.disabled = false;
                updateUrlBtn.textContent = 'Update URL';
            }
        });


        // Edit Search - populate form and open modal
        window.editSearch = (id) => {
            const search = window.savedSearchesData[id];
            if (!search) {
                showAlert('error', 'Search data not found');
                return;
            }

            try {
                // Reset form first to clear any previous data
                resetForm();
                
                // Enable inputs for editing
                enableFormInputs();

                editSearchId.value = id;
                modalTitle.textContent = 'Edit Search';
                saveButtonText.textContent = 'Edit Search';
                
                // Show Save button and Generate URL button for edit mode
                saveSearchBtn.style.display = 'flex';
                formVisitUrlBtn.style.display = 'none';
                document.getElementById('generateUrlBtn').style.display = 'inline-flex';

                // Population with DB-first, URL-fallback strategy
                const url = search.updates_url || '';
                
                // 1. Area
                const dbArea = (search.area || '').replace(/\+/g, ' ');
                const urlArea = getParamFromUrl(url, 'searchLocation');
                const urlLocId = getParamFromUrl(url, 'locationIdentifier');
                
                areaInput.value = dbArea || urlArea || urlLocId || '';
                areaName.value = dbArea || urlArea || urlLocId || '';
                
                // Try to find identifier
                const foundId = areaMapping[areaInput.value] || urlLocId;
                if (foundId) {
                    areaIdentifier.value = foundId;
                }

                // 2. Prices
                const minPrice = search.min_price || getParamFromUrl(url, 'minPrice');
                const maxPrice = search.max_price || getParamFromUrl(url, 'maxPrice');
                // Ensure we don't set '0' if it should be empty, and handle numeric strings
                if (minPriceSelect) minPriceSelect.value = (minPrice && minPrice != '0' && minPrice != 'null') ? Math.round(parseFloat(minPrice)) : '';
                if (maxPriceSelect) maxPriceSelect.value = (maxPrice && maxPrice != '0' && maxPrice != 'null') ? Math.round(parseFloat(maxPrice)) : '';

                // 3. Bedrooms
                const minBed = search.min_bed || getParamFromUrl(url, 'minBedrooms');
                const maxBed = search.max_bed || getParamFromUrl(url, 'maxBedrooms');
                if (minBedroomsSelect) minBedroomsSelect.value = (minBed && minBed != 'null') ? minBed : '';
                if (maxBedroomsSelect) maxBedroomsSelect.value = (maxBed && maxBed != 'null') ? maxBed : '';

                // 4. Bathrooms
                const minBath = search.min_bath || getParamFromUrl(url, 'minBathrooms');
                const maxBath = search.max_bath || getParamFromUrl(url, 'maxBathrooms');
                const minBathSelect = document.getElementById('minBathrooms');
                const maxBathSelect = document.getElementById('maxBathrooms');
                if (minBathSelect) minBathSelect.value = (minBath && minBath != 'null') ? minBath : '';
                if (maxBathSelect) maxBathSelect.value = (maxBath && maxBath != 'null') ? maxBath : '';

                // 5. include SSTC
                const includeSSTC = document.getElementById('includeSSTC');
                if (includeSSTC) {
                    if (search.include_sstc !== undefined && search.include_sstc !== null) {
                        includeSSTC.checked = !!search.include_sstc;
                    } else {
                        const sstcVal = getParamFromUrl(url, 'includeSSTC');
                        includeSSTC.checked = sstcVal === 'true' || url.includes('includeSSTC=true');
                    }
                }

                // 6. Property Types
                const dbTypes = search.property_type || '';
                const urlTypes = getParamFromUrl(url, 'propertyTypes') || '';
                const types = (dbTypes || urlTypes).split(',').filter(t => t);
                document.querySelectorAll('input[name="propertyType"]').forEach(cb => {
                    cb.checked = types.includes(cb.value);
                });

                // 7. Date Added
                const dbDays = search.max_days_since_added;
                const urlDays = getParamFromUrl(url, 'maxDaysSinceAdded');
                if (maxDaysSinceAddedSelect) {
                    maxDaysSinceAddedSelect.value = dbDays || urlDays || '';
                }

                // 8. Tenure Types
                const dbTenures = search.tenure_types || '';
                const urlTenure = getParamFromUrl(url, 'tenure');
                const urlTenureTypes = getParamFromUrl(url, 'tenureTypes');
                const tenures = (dbTenures || urlTenure || urlTenureTypes || '').split(',').filter(t => t);
                document.querySelectorAll('input[name="tenureType"]').forEach(cb => {
                    cb.checked = tenures.includes(cb.value);
                });

                // 9. Must Haves
                const dbMust = search.must_have || '';
                const urlMust = getParamFromUrl(url, 'mustHave');
                const mustHaves = (dbMust || urlMust || '').split(',').filter(t => t);
                document.querySelectorAll('input[name="mustHave"]').forEach(cb => {
                    cb.checked = mustHaves.includes(cb.value);
                });

                // 10. Don't Show
                const dbDont = search.dont_show || '';
                const urlDont = getParamFromUrl(url, 'dontShow');
                const dontShows = (dbDont || urlDont || '').split(',').filter(t => t);
                document.querySelectorAll('input[name="dontShow"]').forEach(cb => {
                    cb.checked = dontShows.includes(cb.value);
                });

                // Populate the generated URL input with the existing URL
                const generatedUrlInput = document.getElementById('generatedUrlInput');
                if (generatedUrlInput) {
                    generatedUrlInput.value = search.updates_url;
                }

                searchModal.classList.add('active');

            } catch (err) {
                console.error('Error in editSearch:', err);
                showAlert('error', 'Error populating search data: ' + err.message);
            }
        };

        // View Search - populate form in read-only mode
        window.viewSearch = (id) => {
            const search = window.savedSearchesData[id];
            if (!search) {
                showAlert('error', 'Search data not found');
                return;
            }

            // Reuse logic to populate form
            window.editSearch(id);

            // Override title and buttons
            modalTitle.textContent = 'View Search';
            
            // Disable all inputs
            disableFormInputs();
            
            // Hide Save button
            saveSearchBtn.style.display = 'none';
            // Hide Generate URL button
            document.getElementById('generateUrlBtn').style.display = 'none';
            
            // Change Cancel button to "Close"
            cancelModalBtn.textContent = 'Close';
            
            // Show Visit URL button and View Results button
            if (search.updates_url) {
                formVisitUrlBtn.href = search.updates_url;
                formVisitUrlBtn.style.display = 'inline-flex';
                
                // Add "View Results" button dynamically if not exists
                let viewResultsBtn = document.getElementById('modalViewResultsBtn');
                if (!viewResultsBtn) {
                    viewResultsBtn = document.createElement('a');
                    viewResultsBtn.id = 'modalViewResultsBtn';
                    viewResultsBtn.className = 'btn btn-primary';
                    viewResultsBtn.innerHTML = '<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg> View Results';
                    document.querySelector('.form-actions').prepend(viewResultsBtn);
                }
                viewResultsBtn.href = `/internal-properties/search/${id}`;
                viewResultsBtn.style.display = 'inline-flex';
            } else {
                formVisitUrlBtn.style.display = 'none';
            }

            // Ensure modal is active
            searchModal.classList.add('active');
        };

        // Modify resetForm to handle additional cleanup
        const originalResetForm = resetForm;
        function resetFormWithCleanup() {
            originalResetForm();
            cancelModalBtn.textContent = 'Cancel';
            // Reset modal title and button text to create mode
            modalTitle.textContent = 'Create New Search';
            saveButtonText.textContent = 'Save Search';
            const viewResultsBtn = document.getElementById('modalViewResultsBtn');
            if (viewResultsBtn) viewResultsBtn.style.display = 'none';
        }
        window.resetForm = resetFormWithCleanup;

        // Delete Confirmation Modal Elements
        const deleteConfirmModal = document.getElementById('deleteConfirmModal');
        const deleteSearchName = document.getElementById('deleteSearchName');
        const deleteSearchIdInput = document.getElementById('deleteSearchId');
        const closeDeleteConfirmModalBtn = document.getElementById('closeDeleteConfirmModal');
        const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
        const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');

        function closeDeleteModal() {
            deleteConfirmModal.classList.remove('active');
        }

        closeDeleteConfirmModalBtn.addEventListener('click', closeDeleteModal);
        cancelDeleteBtn.addEventListener('click', closeDeleteModal);
        deleteConfirmModal.addEventListener('click', (e) => {
            if (e.target === deleteConfirmModal) closeDeleteModal();
        });

        // Show Delete Confirmation Modal
        window.confirmDelete = (id, name) => {
            deleteSearchIdInput.value = id;
            deleteSearchName.textContent = `"${name}"`;
            deleteConfirmModal.classList.add('active');
        };

        // Confirm Delete Button
        confirmDeleteBtn.addEventListener('click', async () => {
            const id = deleteSearchIdInput.value;
            if (!id) return;

            confirmDeleteBtn.disabled = true;
            confirmDeleteBtn.textContent = 'Deleting...';

            try {
                const response = await fetch(`{{ route('searchproperties.destroy', ['id' => 'REPLACE_ID'], false) }}`.replace('REPLACE_ID', id), {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': getCsrfToken()
                    }
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showAlert('success', 'Search deleted successfully');
                    closeDeleteModal();
                    loadSearches();
                } else {
                    showAlert('error', data.message || 'Failed to delete search');
                }
            } catch (error) {
                console.error('Delete error:', error);
                showAlert('error', 'Error deleting search: ' + (error.message || 'Unknown error'));
            } finally {
                confirmDeleteBtn.disabled = false;
                confirmDeleteBtn.textContent = 'Delete';
            }
        });
    </script>
@endsection
