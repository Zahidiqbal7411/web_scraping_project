<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Save and manage Rightmove property searches">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Saved Searches - Property Search Manager</title>
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
            min-width: 800px;
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
</head>
<body>
    <div class="container">
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
                    
                    <!-- Row 1: Area Selection -->
            <div class="form-row">
                <div class="form-group" style="grid-column: span 2;">
                    <label class="form-label">Select Area / Region</label>
                    <select id="areaSelect" class="form-control">
                        <option value="">-- Select an Area --</option>
                        
                        <!-- South West -->
                        <optgroup label="South West">
                            <option value="REGION^116" data-name="Bath, Somerset">Bath, Somerset</option>
                            <option value="REGION^219" data-name="Bristol">Bristol</option>
                            <option value="REGION^353" data-name="Cheltenham">Cheltenham</option>
                            <option value="REGION^517" data-name="Exeter">Exeter</option>
                            <option value="REGION^665" data-name="Gloucester">Gloucester</option>
                            <option value="REGION^1092" data-name="Plymouth">Plymouth</option>
                            <option value="REGION^1261" data-name="Swindon">Swindon</option>
                            <option value="REGION^1284" data-name="Taunton">Taunton</option>
                            <option value="REGION^24568" data-name="Cornwall">Cornwall</option>
                        </optgroup>
                        
                        <!-- South East -->
                        <optgroup label="South East">
                            <option value="REGION^93965" data-name="London">London</option>
                            <option value="REGION^219" data-name="Brighton">Brighton</option>
                            <option value="REGION^274" data-name="Canterbury">Canterbury</option>
                            <option value="REGION^1051" data-name="Oxford">Oxford</option>
                            <option value="REGION^1140" data-name="Reading">Reading</option>
                            <option value="REGION^1234" data-name="Southampton">Southampton</option>
                            <option value="REGION^1108" data-name="Portsmouth">Portsmouth</option>
                            <option value="REGION^928" data-name="Milton Keynes">Milton Keynes</option>
                        </optgroup>
                        
                        <!-- Midlands -->
                        <optgroup label="Midlands">
                            <option value="REGION^162" data-name="Birmingham">Birmingham</option>
                            <option value="REGION^430" data-name="Coventry">Coventry</option>
                            <option value="REGION^453" data-name="Derby">Derby</option>
                            <option value="REGION^806" data-name="Leicester">Leicester</option>
                            <option value="REGION^981" data-name="Nottingham">Nottingham</option>
                            <option value="REGION^1255" data-name="Stoke-on-Trent">Stoke-on-Trent</option>
                            <option value="REGION^1398" data-name="Worcester">Worcester</option>
                        </optgroup>
                        
                        <!-- North West -->
                        <optgroup label="North West">
                            <option value="REGION^886" data-name="Manchester">Manchester</option>
                            <option value="REGION^835" data-name="Liverpool">Liverpool</option>
                            <option value="REGION^351" data-name="Chester">Chester</option>
                            <option value="REGION^1113" data-name="Preston">Preston</option>
                            <option value="REGION^171" data-name="Blackpool">Blackpool</option>
                            <option value="REGION^288" data-name="Carlisle">Carlisle</option>
                        </optgroup>
                        
                        <!-- North East & Yorkshire -->
                        <optgroup label="North East & Yorkshire">
                            <option value="REGION^802" data-name="Leeds">Leeds</option>
                            <option value="REGION^1190" data-name="Sheffield">Sheffield</option>
                            <option value="REGION^910" data-name="Newcastle upon Tyne">Newcastle upon Tyne</option>
                            <option value="REGION^1425" data-name="York">York</option>
                            <option value="REGION^755" data-name="Hull">Hull</option>
                            <option value="REGION^2828" data-name="Durham">Durham</option>
                            <option value="REGION^194" data-name="Bradford">Bradford</option>
                        </optgroup>
                        
                        <!-- East of England -->
                        <optgroup label="East of England">
                            <option value="REGION^265" data-name="Cambridge">Cambridge</option>
                            <option value="REGION^983" data-name="Norwich">Norwich</option>
                            <option value="REGION^1080" data-name="Peterborough">Peterborough</option>
                            <option value="REGION^768" data-name="Ipswich">Ipswich</option>
                            <option value="REGION^847" data-name="Luton">Luton</option>
                        </optgroup>
                        
                        <!-- Wales -->
                        <optgroup label="Wales">
                            <option value="REGION^277" data-name="Cardiff">Cardiff</option>
                            <option value="REGION^1268" data-name="Swansea">Swansea</option>
                            <option value="REGION^962" data-name="Newport">Newport</option>
                        </optgroup>
                        
                        <!-- Scotland -->
                        <optgroup label="Scotland">
                            <option value="REGION^550" data-name="Edinburgh">Edinburgh</option>
                            <option value="REGION^664" data-name="Glasgow">Glasgow</option>
                            <option value="REGION^18" data-name="Aberdeen">Aberdeen</option>
                            <option value="REGION^548" data-name="Dundee">Dundee</option>
                        </optgroup>
                        
                        <!-- Northern Ireland -->
                        <optgroup label="Northern Ireland">
                            <option value="REGION^143" data-name="Belfast">Belfast</option>
                            <option value="REGION^457" data-name="Derry">Derry</option>
                        </optgroup>
                    </select>
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
            <h2 class="card-title">Your Saved Searches</h2>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Area</th>
                            <th>Price Range</th>
                            <th>Bedrooms</th>
                            <th>Property Type</th>
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
                        <a id="visitUrlBtn" href="#" target="_blank" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.5rem;">
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
    </div>

    <script>
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
        const areaSelect = document.getElementById('areaSelect');
        const minPriceSelect = document.getElementById('minPrice');
        const maxPriceSelect = document.getElementById('maxPrice');
        const minBedroomsSelect = document.getElementById('minBedrooms');
        const maxBedroomsSelect = document.getElementById('maxBedrooms');
        const maxDaysSinceAddedSelect = document.getElementById('maxDaysSinceAdded');

        // Modal Open/Close
        openModalBtn.addEventListener('click', () => {
            resetForm();
            modalTitle.textContent = 'Create New Search';
            saveButtonText.textContent = 'Save Search';
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
        }

        function resetForm() {
            areaSelect.value = '';
            minPriceSelect.value = '';
            maxPriceSelect.value = '';
            minBedroomsSelect.value = '';
            maxBedroomsSelect.value = '';
            maxDaysSinceAddedSelect.value = '1';
            
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

            // Location identifier (required for search to work)
            const locationId = areaSelect.value;
            if (locationId) {
                params.append('locationIdentifier', locationId);
            }

            // Search location for display
            const selectedOption = areaSelect.options[areaSelect.selectedIndex];
            const locationName = selectedOption.dataset.name || '';
            if (locationName) {
                params.append('searchLocation', locationName);
            }

            // Price
            if (minPriceSelect.value) {
                params.append('minPrice', minPriceSelect.value);
            }
            if (maxPriceSelect.value) {
                params.append('maxPrice', maxPriceSelect.value);
            }

            // Bedrooms
            if (minBedroomsSelect.value) {
                params.append('minBedrooms', minBedroomsSelect.value);
            }
            if (maxBedroomsSelect.value) {
                params.append('maxBedrooms', maxBedroomsSelect.value);
            }

            // Bathrooms
            const minBathroomsSelect = document.getElementById('minBathrooms');
            const maxBathroomsSelect = document.getElementById('maxBathrooms');
            if (minBathroomsSelect && minBathroomsSelect.value) {
                params.append('minBathrooms', minBathroomsSelect.value);
            }
            if (maxBathroomsSelect && maxBathroomsSelect.value) {
                params.append('maxBathrooms', maxBathroomsSelect.value);
            }

            // Property Types
            const checkedTypes = document.querySelectorAll('input[name="propertyType"]:checked');
            if (checkedTypes.length > 0) {
                const types = Array.from(checkedTypes).map(cb => cb.value).join(',');
                params.append('propertyTypes', types);
            }

            // Include Under Offer / Sold STC
            const includeSSTC = document.getElementById('includeSSTC');
            if (includeSSTC && includeSSTC.checked) {
                params.append('includeSSTC', 'true');
            }

            // Date Added
            if (maxDaysSinceAddedSelect.value) {
                params.append('maxDaysSinceAdded', maxDaysSinceAddedSelect.value);
            }

            // Tenure Types
            const checkedTenures = document.querySelectorAll('input[name="tenureType"]:checked');
            if (checkedTenures.length > 0) {
                const tenures = Array.from(checkedTenures).map(cb => cb.value).join(',');
                params.append('tenureTypes', tenures);
            }

            // Must Haves
            const checkedMustHaves = document.querySelectorAll('input[name="mustHave"]:checked');
            if (checkedMustHaves.length > 0) {
                const mustHaves = Array.from(checkedMustHaves).map(cb => cb.value).join(',');
                params.append('mustHave', mustHaves);
            }

            // Don't Show
            const checkedDontShow = document.querySelectorAll('input[name="dontShow"]:checked');
            if (checkedDontShow.length > 0) {
                const dontShows = Array.from(checkedDontShow).map(cb => cb.value).join(',');
                params.append('dontShow', dontShows);
            }

            // Sorting - most recent
            params.append('sortType', '6');

            // Radius
            params.append('radius', '0.0');

            // Index for pagination
            params.append('index', '0');

            return `${baseUrl}?${params.toString()}`;
        }

        // Load Searches on Page Load
        document.addEventListener('DOMContentLoaded', loadSearches);

        // Save/Update Search
        saveSearchBtn.addEventListener('click', async () => {
            const url = buildRightmoveUrl();
            const isEdit = editSearchId.value !== '';

            const originalContent = saveSearchBtn.innerHTML;
            saveSearchBtn.disabled = true;
            saveSearchBtn.innerHTML = '<span class="spinner"></span> Saving...';

            try {
                const endpoint = isEdit ? `/api/saved-searches/${editSearchId.value}` : '/api/saved-searches';
                const method = isEdit ? 'PUT' : 'POST';

                const response = await fetch(endpoint, {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ updates_url: url })
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

        // Load Searches
        async function loadSearches() {
            searchesBody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:2rem;">Loading...</td></tr>';

            try {
                const response = await fetch('/api/saved-searches');
                const data = await response.json();

                if (data.success && data.searches) {
                    if (data.searches.length === 0) {
                        searchesBody.innerHTML = `
                            <tr>
                                <td colspan="5" class="empty-state">
                                    <div class="empty-icon">📋</div>
                                    <p>No saved searches yet</p>
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
                        const type = search.property_type ? search.property_type.replace(/,/g, ', ') : 'Any';
                        const typeShort = type.length > 20 ? type.substring(0, 20) + '...' : type;
                        const area = search.area ? search.area.replace(/\+/g, ' ') : 'Unknown';

                        return `
                            <tr>
                                <td>${area}</td>
                                <td class="text-muted">${minPrice} - ${maxPrice}</td>
                                <td class="text-muted">${minBed} - ${maxBed} Beds</td>
                                <td class="text-muted capitalize">
                                    <button onclick="showPropertyTypes('${type.replace(/'/g, "\\'")}')" class="btn btn-sm" style="background: var(--teal); color: white; font-size: 0.75rem; padding: 0.25rem 0.5rem;">
                                        View Types
                                    </button>
                                </td>
                                <td>
                                    <div class="actions">
                                        <button onclick="editSearch(${search.id})" class="btn btn-primary btn-sm">Edit</button>
                                        <button onclick="showUrlPreview('${search.updates_url.replace(/'/g, "\\'")}', ${search.id})" class="btn btn-secondary btn-sm">View</button>
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
                console.error(error);
                searchesBody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:2rem;color:var(--error);">Error loading searches</td></tr>';
            }
        }

        // Property Type Modal Elements
        const propertyTypeModal = document.getElementById('propertyTypeModal');
        const propertyTypeModalBody = document.getElementById('propertyTypeModalBody');
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
        const visitUrlBtn = document.getElementById('visitUrlBtn');
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
            visitUrlBtn.href = url;
            urlPreviewSearchId.value = searchId || '';
            urlPreviewModal.classList.add('active');
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
                const response = await fetch(`/api/saved-searches/${searchId}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
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

            editSearchId.value = id;
            modalTitle.textContent = 'Edit Search';
            saveButtonText.textContent = 'Update Search';

            // Parse URL to populate form
            try {
                const urlObj = new URL(search.updates_url);
                const params = new URLSearchParams(urlObj.search);

                // Set area by locationIdentifier
                const locationId = params.get('locationIdentifier') || '';
                if (locationId) {
                    areaSelect.value = locationId;
                }

                // Set prices
                minPriceSelect.value = params.get('minPrice') || '';
                maxPriceSelect.value = params.get('maxPrice') || '';

                // Set bedrooms
                minBedroomsSelect.value = params.get('minBedrooms') || '';
                maxBedroomsSelect.value = params.get('maxBedrooms') || '';

                // Set bathrooms
                const minBathroomsSelect = document.getElementById('minBathrooms');
                const maxBathroomsSelect = document.getElementById('maxBathrooms');
                if (minBathroomsSelect) minBathroomsSelect.value = params.get('minBathrooms') || '';
                if (maxBathroomsSelect) maxBathroomsSelect.value = params.get('maxBathrooms') || '';

                // Set include SSTC
                const includeSSTC = document.getElementById('includeSSTC');
                if (includeSSTC) includeSSTC.checked = params.has('includeSSTC') || params.get('includeSSTC') === 'true';

                // Set property types
                const propertyTypes = params.get('propertyTypes') || '';
                const types = propertyTypes.split(',').filter(t => t);
                document.querySelectorAll('input[name="propertyType"]').forEach(cb => {
                    cb.checked = types.includes(cb.value);
                });

                // Set date added
                maxDaysSinceAddedSelect.value = params.get('maxDaysSinceAdded') || '';

                // Set tenure types
                const tenureTypes = params.get('tenureTypes') || '';
                const tenures = tenureTypes.split(',').filter(t => t);
                document.querySelectorAll('input[name="tenureType"]').forEach(cb => {
                    cb.checked = tenures.includes(cb.value);
                });

                // Set must haves
                const mustHave = params.get('mustHave') || '';
                const mustHaves = mustHave.split(',').filter(t => t);
                document.querySelectorAll('input[name="mustHave"]').forEach(cb => {
                    cb.checked = mustHaves.includes(cb.value);
                });

                // Set don't show
                const dontShow = params.get('dontShow') || '';
                const dontShows = dontShow.split(',').filter(t => t);
                document.querySelectorAll('input[name="dontShow"]').forEach(cb => {
                    cb.checked = dontShows.includes(cb.value);
                });

            } catch (e) {
                console.error('Error parsing URL:', e);
            }

            searchModal.classList.add('active');
        };

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
                const response = await fetch(`/api/saved-searches/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showAlert('success', 'Search deleted successfully');
                    closeDeleteModal();
                    loadSearches();
                } else {
                    showAlert('error', 'Failed to delete search');
                }
            } catch (error) {
                showAlert('error', 'Error deleting search');
            } finally {
                confirmDeleteBtn.disabled = false;
                confirmDeleteBtn.textContent = 'Delete';
            }
        });
    </script>
</body>
</html>
