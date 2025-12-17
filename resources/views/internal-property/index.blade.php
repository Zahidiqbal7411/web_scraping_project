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

        /* Properties Grid - 2 Columns (Requested) */
        .properties-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
            overflow: hidden;
            width: 100%;
            max-width: 100%;
        }

        /* Property Card - Split Layout */
        .property-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
            position: relative;
            display: flex;
            flex-direction: row;
            align-items: stretch;
            min-height: 400px;
            max-width: 100%;
            box-sizing: border-box;
        }

        /* Left Side: Property Details */
        .property-main-content {
            flex: 0 0 60%;
            display: flex;
            flex-direction: column;
            border-right: 1px solid var(--card-border);
            min-width: 0;
            max-width: 60%;
            overflow: hidden;
        }

        /* Right Side: Sold History */
        .property-sold-sidebar {
            flex: 0 0 40%;
            max-width: 40%;
            background: #fafafa;
            display: flex;
            flex-direction: column;
            border-left: 1px solid var(--card-border);
            overflow: hidden;
        }

        .property-image-section {
            position: relative;
            width: 100%;
            aspect-ratio: 4/3; /* Taller image as requested */
            overflow: hidden;
            background: #f0f0f0;
        }

        .property-info-section {
            padding: 1.5rem;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        /* Sold Sidebar Styles */
        .sold-sidebar-header {
            padding: 1rem 1.5rem;
            background: white;
            border-bottom: 1px solid var(--card-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .sold-sidebar-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .sold-list-container {
            padding: 0.75rem;
            overflow-y: auto;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            max-height: 500px;
        }
        
        /* Sold Card Style for Sidebar */
        .sold-item-card {
            background: white;
            border: 1px solid var(--card-border);
            border-radius: 8px;
            padding: 0.75rem;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        
        /* Sold Property Info Section (TOP) */
        .sold-property-info {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 6px;
            padding: 0.75rem;
            margin-bottom: 0.75rem;
        }
        
        .sold-property-type {
            font-weight: 700;
            font-size: 0.85rem;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }
        
        .sold-property-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.35rem;
            font-size: 0.75rem;
            color: var(--text-secondary);
        }
        
        .sold-property-details span {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        /* Sold Prices Section (BELOW) - scrollable if more than 3 */
        .sold-prices-section {
            border-top: 1px dashed var(--card-border);
            padding-top: 0.5rem;
            max-height: 120px;
            overflow-y: auto;
        }
        
        .sold-prices-title {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.35rem;
        }
        
        .sold-history-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.75rem;
            color: var(--text-secondary);
            padding: 0.25rem 0;
            border-bottom: 1px dotted var(--card-border);
        }
        
        .sold-history-row:last-child {
            border-bottom: none;
        }
        
        .sold-history-price {
            font-weight: 700;
            color: var(--primary);
        }

        /* Layout Overrides to fill screen but prevent overflow */
        .main-content {
            width: 100% !important;
            max-width: calc(100vw - 260px) !important; /* Account for sidebar */
            padding: 1rem !important;
            margin: 0 !important;
            box-sizing: border-box !important;
            overflow-x: hidden !important;
        }
        
        .container {
            width: 100% !important;
            max-width: 100% !important;
            padding: 0 0.5rem !important;
            margin: 0 !important;
            box-sizing: border-box !important;
            overflow: hidden !important;
        }

        /* Global fix for this page */
        body {
            overflow-x: hidden;
        }
        
        /* Hide old sold styles if they conflict */
        .sold-history-section { display: none; }

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

        /* Layout Overrides to fill screen */
        .main-content {
            width: 100% !important;
            padding: 1.5rem !important;
            box-sizing: border-box !important;
        }
        
        .container {
            width: 100% !important;
            max-width: 100% !important;
            padding: 0 !important;
            margin: 0 !important;
        }

        /* Sold Property History */
        .sold-history-section {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 2px dashed var(--card-border);
            background: #fafafa;
            /* Removed negative margins to prevent horizontal scroll */
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 0.5rem;
        }

        .sold-history-title {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .sold-count-badge {
            background: var(--secondary);
            color: white;
            font-size: 0.75rem;
            padding: 0.15rem 0.5rem;
            border-radius: 6px;
            font-weight: 600;
        }

        .sold-properties-container {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            max-height: 400px;
            overflow-y: auto;
            padding-right: 0.5rem;
            /* Ensure no horizontal scroll inside */
            overflow-x: hidden;
        }
        
        .sold-property-card {
            background: white;
            border: 1px solid var(--card-border);
            border-radius: 8px;
            padding: 1rem;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            transition: all 0.2s;
        }
        
        .sold-property-card:hover {
            border-color: var(--secondary);
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .sold-property-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.75rem;
        }
        
        .sold-property-type {
            font-weight: 700;
            color: var(--text-primary);
            font-size: 0.95rem;
        }
        
        .sold-property-price {
            font-weight: 700;
            color: var(--primary);
            font-size: 1.1rem;
        }

        .sold-details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
            margin-bottom: 0.75rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px dashed var(--card-border);
        }
        
        /* Re-use detail-item styles but smaller */
        .sold-details-grid .detail-label {
            font-size: 0.65rem;
        }
        
        .sold-details-grid .detail-value {
            font-size: 0.8rem;
        }
        
        .sold-transactions-list {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }
        
        .sold-transaction-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.8rem;
            color: var(--text-secondary);
            padding: 0.25rem 0;
            border-bottom: 1px dotted var(--card-border);
        }
        
        .sold-transaction-item:last-child {
            border-bottom: none;
        }
        
        .sold-transaction-price {
            font-weight: 600;
            color: var(--text-primary);
        }

        /* Property URL */
        .property-url-wrapper {
            margin-bottom: 0.75rem;
        }

        .property-url-display {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .property-url {
            font-size: 0.75rem;
            color: var(--text-secondary);
            word-break: break-all;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            flex: 1;
            min-width: 0;
        }

        .property-url a {
            color: var(--secondary);
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .property-url a:hover {
            color: var(--primary);
            text-decoration: underline;
        }

        .url-edit-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 0.25rem;
            color: var(--text-secondary);
            transition: color 0.2s ease;
            flex-shrink: 0;
        }

        .url-edit-btn:hover {
            color: var(--primary);
        }

        .property-url-edit {
            display: none;
        }

        .property-url-edit.active {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .property-url-edit input {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid var(--card-border);
            border-radius: 4px;
            font-size: 0.75rem;
            font-family: inherit;
        }

        .property-url-edit input:focus {
            outline: none;
            border-color: var(--primary);
        }

        .url-edit-actions {
            display: flex;
            gap: 0.5rem;
        }

        .url-save-btn, .url-cancel-btn {
            padding: 0.375rem 0.75rem;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .url-save-btn {
            background: var(--success);
            color: white;
            border: none;
        }

        .url-save-btn:hover {
            background: hsl(142, 70%, 40%);
        }

        .url-cancel-btn {
            background: transparent;
            color: var(--text-secondary);
            border: 1px solid var(--card-border);
        }

        .url-cancel-btn:hover {
            background: rgba(0, 0, 0, 0.05);
        }

        /* Search URL Bar */
        .search-url-bar {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 8px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-sm);
        }

        .search-url-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }

        .search-url-display {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .search-url-text {
            flex: 1;
            font-size: 0.875rem;
            color: var(--secondary);
            word-break: break-all;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .search-url-text a {
            color: inherit;
            text-decoration: none;
        }

        .search-url-text a:hover {
            text-decoration: underline;
        }

        .search-url-edit-section {
            display: none;
        }

        .search-url-edit-section.active {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .search-url-edit-section input {
            width: 100%;
            padding: 0.625rem;
            border: 1px solid var(--card-border);
            border-radius: 6px;
            font-size: 0.875rem;
            font-family: inherit;
        }

        .search-url-edit-section input:focus {
            outline: none;
            border-color: var(--primary);
        }

        .search-url-actions {
            display: flex;
            gap: 0.5rem;
        }

        .search-url-btn {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .search-url-btn.save {
            background: var(--success);
            color: white;
            border: none;
        }

        .search-url-btn.save:hover {
            background: hsl(142, 70%, 40%);
        }

        .search-url-btn.cancel {
            background: transparent;
            color: var(--text-secondary);
            border: 1px solid var(--card-border);
        }

        .search-url-btn.cancel:hover {
            background: rgba(0, 0, 0, 0.05);
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
@endsection

@section('content')
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
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                </svg>
                Import Properties (Step: 01)
            </button>
        </div>

        @if(isset($search) && $search->updates_url)
        <!-- Search URL Bar -->
        <div class="search-url-bar">
            <div class="search-url-label">Search URL</div>
            <div class="search-url-display" id="searchUrlDisplay">
                <div class="search-url-text" title="{{ $search->updates_url }}">
                    <a href="{{ $search->updates_url }}" target="_blank" id="searchUrlLink">{{ $search->updates_url }}</a>
                </div>
                <button class="url-edit-btn" onclick="toggleSearchUrlEdit()" title="Edit URL">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                </button>
            </div>
            <div class="search-url-edit-section" id="searchUrlEdit">
                <input type="text" id="searchUrlInput" value="{{ $search->updates_url }}" placeholder="Enter Rightmove URL">
                <div class="search-url-actions">
                    <button class="search-url-btn save" onclick="saveSearchUrl()">Save</button>
                    <button class="search-url-btn cancel" onclick="cancelSearchUrlEdit()">Cancel</button>
                </div>
            </div>
        </div>
        @endif

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
            <p class="empty-text">Click the "Import Properties" button to load property listings</p>
            <p style="color: var(--text-secondary); font-size: 0.875rem;">This will fetch data from approximately 620 property URLs</p>
        </div>

        <!-- Properties Grid -->
        <div class="properties-grid" id="propertiesGrid"></div>
@endsection

@section('scripts')
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
        let currentPage = 1;
        const itemsPerPage = 9; // Show 9 items per page

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

        // Import button handler
        syncBtn.addEventListener('click', async () => {
            await importAllProperties(true);
        });

        // Load properties on startup
        document.addEventListener('DOMContentLoaded', async () => {
            // On page load, try to load complete property data from database
            await loadFromDatabaseOnStartup();
        });

        // Load full property data from database on page startup
        async function loadFromDatabaseOnStartup() {
            try {
                loading.classList.add('active');
                
                // Call the new endpoint that returns complete property data from DB
                const url = window.searchContext 
                    ? `/api/internal-property/load-from-db?search_id=${window.searchContext.id}` 
                    : `/api/internal-property/load-from-db`;
                
                console.log('Loading from DB, URL:', url);
                
                const response = await fetch(url, {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                console.log('Response status:', response.status);
                
                if (!response.ok) {
                    const text = await response.text();
                    console.error('Error response:', text.substring(0, 500));
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                console.log('Data received:', data.success, 'count:', data.count);
                
                loading.classList.remove('active');
                
                if (!data.success || !data.properties || data.properties.length === 0) {
                    // No data in database - show empty state
                    emptyState.classList.add('active');
                    console.log('No properties in database. Click Import to fetch from source.');
                    return;
                }
                
                // We have data from database - display it directly!
                console.log(`Loaded ${data.properties.length} complete properties from database`);
                
                emptyState.classList.remove('active');
                statsBar.style.display = 'flex';
                totalCount.textContent = data.properties.length;
                loadedCount.textContent = data.properties.length; // All loaded!
                
                // Properties from DB are already complete - no loading state
                loadedProperties = data.properties;
                propertyUrls = data.properties.map(p => ({ url: p.url, id: p.id }));
                
                displayProperties(loadedProperties);
                
                showAlert('success', `Loaded ${data.properties.length} properties from database`);
                
            } catch (error) {
                console.error('Error loading from database:', error);
                loading.classList.remove('active');
                emptyState.classList.add('active');
            }
        }

        // Import all properties with OPTIMIZED concurrent progressive loading
        // This is called when user clicks the Import button
        async function importAllProperties(isImport = true) {
            try {
                // Hide empty state if we are importing
                emptyState.classList.remove('active');
                
                successAlert.classList.remove('active');
                errorAlert.classList.remove('active');
                
                // Show loading
                loading.classList.add('active');
                syncBtn.disabled = true;

                showAlert('success', 'Importing property URLs from source website...');
                
                // Always fetch from source when importing (isImport=true)
                const url = window.searchContext 
                    ? `/api/internal-property/fetch-urls?search_id=${window.searchContext.id}&import=true` 
                    : `/api/internal-property/fetch-urls?import=true`;
                
                const urlsResponse = await fetch(url);
                
                if (!urlsResponse.ok) {
                    throw new Error(`HTTP error! status: ${urlsResponse.status}`);
                }
                
                const urlsData = await urlsResponse.json();

                if (!urlsData.success || !urlsData.urls || urlsData.urls.length === 0) {
                    throw new Error(urlsData.message || urlsData.hint || 'No property URLs found');
                }
                
                // Hide empty state now that we have data
                emptyState.classList.remove('active');

                showAlert('success', `Imported ${urlsData.urls.length} property URLs. Loading details...`);

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
                    address: urlData.address || 'Loading...',
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
                
                showAlert('success', `Showing ${loadedProperties.length} properties. Fetching details from source...`);

                // Load details with CONCURRENT batching for MAXIMUM SPEED
                // This scrapes full property details from source website
                await loadDetailsConcurrently(propertyUrls);

            } catch (error) {
                console.error('Error importing properties:', error);
                showAlert('error', error.message || 'An error occurred while importing properties');
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

        // Display properties in grid with pagination
        function displayProperties(props) {
            // Calculate slice for current page
            const startIndex = (currentPage - 1) * itemsPerPage;
            const endIndex = startIndex + itemsPerPage;
            const pageProps = props.slice(startIndex, endIndex);

            // Render current page items
            propertiesGrid.innerHTML = pageProps.map((property, index) => {
                currentImageIndexes[property.id] = 0;
                return createPropertyCard(property, index);
            }).join('');

            // Render pagination controls
            renderPagination(props.length);

            // Add event listeners for image navigation
            pageProps.forEach(property => {
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

        // Render pagination controls
        function renderPagination(totalItems) {
            const totalPages = Math.ceil(totalItems / itemsPerPage);
            if (totalPages <= 1) {
                // Remove pagination if exists
                const existingNav = document.getElementById('paginationNav');
                if (existingNav) existingNav.remove();
                return;
            }

            let paginationNav = document.getElementById('paginationNav');
            if (!paginationNav) {
                paginationNav = document.createElement('div');
                paginationNav.id = 'paginationNav';
                paginationNav.className = 'pagination-nav';
                // Insert after properties grid
                propertiesGrid.parentNode.insertBefore(paginationNav, propertiesGrid.nextSibling);

                // Add pagination styles if not present
                if (!document.getElementById('paginationStyles')) {
                    const style = document.createElement('style');
                    style.id = 'paginationStyles';
                    style.textContent = `
                        .pagination-nav {
                            display: flex;
                            justify-content: center;
                            align-items: center;
                            gap: 1rem;
                            margin: 2rem 0;
                            padding: 1rem;
                            background: var(--card-bg);
                            border-radius: 8px;
                            box-shadow: var(--shadow-sm);
                        }
                        .page-btn {
                            padding: 0.5rem 1rem;
                            border: 1px solid var(--card-border);
                            background: white;
                            border-radius: 4px;
                            cursor: pointer;
                            font-weight: 500;
                            transition: all 0.2s;
                        }
                        .page-btn:hover:not(:disabled) {
                            background: var(--primary-light);
                            border-color: var(--primary);
                            color: var(--primary);
                        }
                        .page-btn:disabled {
                            opacity: 0.5;
                            cursor: not-allowed;
                        }
                        .page-info {
                            font-size: 0.9rem;
                            color: var(--text-secondary);
                            font-weight: 500;
                        }
                        .page-controls {
                            display: flex;
                            align-items: center;
                            gap: 1rem;
                        }
                        .goto-page {
                            display: flex;
                            align-items: center;
                            gap: 0.5rem;
                        }
                        .goto-input {
                            width: 60px;
                            padding: 0.4rem;
                            border: 1px solid var(--card-border);
                            border-radius: 4px;
                            text-align: center;
                        }
                        .page-btn.small {
                            padding: 0.4rem 0.8rem;
                            font-size: 0.8rem;
                        }
                    `;
                    document.head.appendChild(style);
                }
            }

            paginationNav.innerHTML = `
                <button class="page-btn" onclick="changePage(-1)" ${currentPage === 1 ? 'disabled' : ''}>Previous</button>
                <div class="page-controls">
                    <span class="page-info">Page ${currentPage} of ${totalPages}</span>
                    <div class="goto-page">
                        <input type="number" min="1" max="${totalPages}" value="${currentPage}" 
                               class="goto-input" onchange="goToPage(this.value)" 
                               onkeydown="if(event.key === 'Enter') goToPage(this.value)">
                        <button class="page-btn small" onclick="goToPage(this.previousElementSibling.value)">Go</button>
                    </div>
                </div>
                <button class="page-btn" onclick="changePage(1)" ${currentPage === totalPages ? 'disabled' : ''}>Next</button>
            `;
        }

        // Go to specific page
        function goToPage(page) {
            page = parseInt(page);
            const totalPages = Math.ceil(loadedProperties.length / itemsPerPage);
            
            if (page >= 1 && page <= totalPages) {
                currentPage = page;
                displayProperties(loadedProperties);
                document.getElementById('header')?.scrollIntoView({ behavior: 'smooth' });
            } else {
                // Reset input to current page if invalid
                const input = document.querySelector('.goto-input');
                if (input) input.value = currentPage;
                showAlert('error', `Please enter a page between 1 and ${totalPages}`);
            }
        }

        // Change page
        function changePage(direction) {
            const totalPages = Math.ceil(loadedProperties.length / itemsPerPage);
            const newPage = currentPage + direction;

            if (newPage >= 1 && newPage <= totalPages) {
                currentPage = newPage;
                displayProperties(loadedProperties);
                document.getElementById('header')?.scrollIntoView({ behavior: 'smooth' });
            }
        }

        // Create property card HTML - matches screenshot design exactly
        function createPropertyCard(property, index) {
            const hasImages = property.images && property.images.length > 0;
            const imageCount = hasImages ? property.images.length : 0;
            const loadingClass = property.loading ? 'loading' : '';
            
            // Use first image or placeholder
            const mainImage = hasImages ? property.images[0] : `https://via.placeholder.com/600x400/e0e0e0/666666?text=Loading+Image`;

            // Create property card HTML - Split Layout
            return `
                <div class="property-card ${loadingClass}" id="card-${property.id}" style="animation-delay: ${index * 0.01}s;">
                    <!-- LEFT SIDE: Property Details -->
                    <div class="property-main-content">
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
                            <div class="property-url-wrapper" onclick="event.stopPropagation()">
                                <div class="property-url-display" id="url-display-${property.id}">
                                    <div class="property-url" title="${property.url}">
                                        <a href="${property.url}" target="_blank" id="url-link-${property.id}">${property.url}</a>
                                    </div>
                                    <button class="url-edit-btn" onclick="toggleUrlEdit('${property.id}', '${property.url.replace(/'/g, "\\'")}')"
                                            title="Edit URL">
                                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                  d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                    </button>
                                </div>
                                <div class="property-url-edit" id="url-edit-${property.id}">
                                    <input type="text" id="url-input-${property.id}" value="${property.url}" placeholder="Enter URL">
                                    <div class="url-edit-actions">
                                        <button class="url-save-btn" onclick="saveUrl('${property.id}')">Save</button>
                                        <button class="url-cancel-btn" onclick="cancelUrlEdit('${property.id}')">Cancel</button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="property-price-section">
                                <span class="price-amount">${property.price}</span>
                                ${property.reduced_on ? `<span class="info-icon" title="Price information">ⓘ</span>` : ''}
                            </div>
                            
                            ${property.reduced_on ? `
                                <div class="reduced-date">Reduced on ${property.reduced_on}</div>
                            ` : ''}
                            
                            <div class="property-details-grid">
                                <div class="detail-item">
                                    <div class="detail-label">PROPERTY TYPE</div>
                                    <div class="detail-value">
                                        <span>${property.property_type || '-'}</span>
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">BEDROOMS</div>
                                    <div class="detail-value">
                                        <span>${property.bedrooms || '-'}</span>
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">BATHROOMS</div>
                                    <div class="detail-value">
                                        <span>${property.bathrooms || '-'}</span>
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">SIZE</div>
                                    <div class="detail-value">
                                        <span>${property.size || 'Ask agent'}</span>
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">TENURE</div>
                                    <div class="detail-value">
                                        <span>${property.tenure || 'Freehold'}</span>
                                    </div>
                                </div>
                            </div>
                            
                            <a href="${property.url}" target="_blank" class="view-btn" onclick="event.stopPropagation()">
                                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                </svg>
                                View on Rightmove
                            </a>
                        </div>
                    </div>

                    <!-- RIGHT SIDE: Sold History Sidebar -->
                    <div class="property-sold-sidebar">
                        <div class="sold-sidebar-header">
                            <div class="sold-sidebar-title">
                                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Sold History (${property.sold_properties ? property.sold_properties.length : 0})
                            </div>
                        </div>
                        
                        <div class="sold-list-container">
                            ${property.sold_properties && property.sold_properties.length > 0 ? 
                                property.sold_properties.map(sold => {
                                    return `
                                    <div class="sold-item-card">
                                        <!-- Property Details (TOP) -->
                                        <div class="sold-property-info">
                                            <div class="sold-property-type">${sold.property_type || 'Property'} (${sold.tenure || 'Unknown'})</div>
                                            <div class="sold-property-details">
                                                <span>🛏️ ${sold.bedrooms || '-'} Beds</span>
                                                <span>🛁 ${sold.bathrooms || '-'} Baths</span>
                                            </div>
                                        </div>
                                        
                                        <!-- All Prices (BELOW) -->
                                        ${sold.prices && sold.prices.length > 0 ? `
                                            <div class="sold-prices-section">
                                                <div class="sold-prices-title">Sale History</div>
                                                ${sold.prices.map(price => `
                                                    <div class="sold-history-row">
                                                        <span>${price.sold_date || '-'}</span>
                                                        <span class="sold-history-price">${price.sold_price || '-'}</span>
                                                    </div>
                                                `).join('')}
                                            </div>
                                        ` : `<div style="font-size:0.75rem; color:var(--text-secondary); font-style:italic;">No price data</div>`}
                                    </div>
                                    `;
                                }).join('') 
                            :   `<div style="color:var(--text-secondary); text-align:center; padding:2rem; font-style:italic;">
                                    No sold history available
                                </div>`
                            }
                        </div>
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

        // Toggle URL edit mode
        function toggleUrlEdit(propertyId, currentUrl) {
            const urlDisplay = document.getElementById(`url-display-${propertyId}`);
            const urlEdit = document.getElementById(`url-edit-${propertyId}`);
            const urlInput = document.getElementById(`url-input-${propertyId}`);
            
            if (urlDisplay && urlEdit) {
                urlDisplay.style.display = 'none';
                urlEdit.classList.add('active');
                urlInput.value = currentUrl;
                urlInput.focus();
                urlInput.select();
            }
        }
        
        // Save URL
        function saveUrl(propertyId) {
            const urlDisplay = document.getElementById(`url-display-${propertyId}`);
            const urlEdit = document.getElementById(`url-edit-${propertyId}`);
            const urlInput = document.getElementById(`url-input-${propertyId}`);
            const urlLink = document.getElementById(`url-link-${propertyId}`);
            
            const newUrl = urlInput.value.trim();
            
            if (newUrl && urlLink) {
                // Update the displayed URL
                urlLink.href = newUrl;
                urlLink.textContent = newUrl;
                urlLink.parentElement.title = newUrl;
                
                // Update the edit button's onclick with new URL
                const editBtn = urlDisplay.querySelector('.url-edit-btn');
                if (editBtn) {
                    editBtn.onclick = function() { toggleUrlEdit(propertyId, newUrl.replace(/'/g, "\\'")); };
                }
                
                // Also update the View on Rightmove button
                const card = document.getElementById(`card-${propertyId}`);
                if (card) {
                    const viewBtn = card.querySelector('.view-btn');
                    if (viewBtn) {
                        viewBtn.href = newUrl;
                    }
                }
                
                // Update the property in loadedProperties array
                const property = loadedProperties.find(p => p.id == propertyId);
                if (property) {
                    property.url = newUrl;
                }
                
                showAlert('success', 'URL updated successfully');
            }
            
            // Hide edit mode, show display mode
            urlEdit.classList.remove('active');
            urlDisplay.style.display = 'flex';
        }
        
        // Cancel URL edit
        function cancelUrlEdit(propertyId) {
            const urlDisplay = document.getElementById(`url-display-${propertyId}`);
            const urlEdit = document.getElementById(`url-edit-${propertyId}`);
            
            urlEdit.classList.remove('active');
            urlDisplay.style.display = 'flex';
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

        // Toggle Search URL edit mode
        function toggleSearchUrlEdit() {
            const urlDisplay = document.getElementById('searchUrlDisplay');
            const urlEdit = document.getElementById('searchUrlEdit');
            const urlInput = document.getElementById('searchUrlInput');
            
            if (urlDisplay && urlEdit) {
                urlDisplay.style.display = 'none';
                urlEdit.classList.add('active');
                urlInput.focus();
                urlInput.select();
            }
        }
        
        // Save Search URL
        async function saveSearchUrl() {
            const urlDisplay = document.getElementById('searchUrlDisplay');
            const urlEdit = document.getElementById('searchUrlEdit');
            const urlInput = document.getElementById('searchUrlInput');
            const urlLink = document.getElementById('searchUrlLink');
            const saveBtn = urlEdit.querySelector('.search-url-btn.save');
            
            const newUrl = urlInput.value.trim();
            
            if (!newUrl) {
                showAlert('error', 'URL cannot be empty');
                return;
            }
            
            // Disable save button while saving
            if (saveBtn) {
                saveBtn.disabled = true;
                saveBtn.textContent = 'Saving...';
            }
            
            try {
                // Save to database if we have a search context
                if (window.searchContext && window.searchContext.id) {
                    const response = await fetch(`/api/saved-searches/${window.searchContext.id}`, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                        },
                        body: JSON.stringify({
                            updates_url: newUrl
                        })
                    });
                    
                    const data = await response.json();
                    
                    if (!response.ok || !data.success) {
                        throw new Error(data.message || 'Failed to save URL');
                    }
                }
                
                // Update the displayed URL
                if (urlLink) {
                    urlLink.href = newUrl;
                    urlLink.textContent = newUrl;
                    urlLink.parentElement.title = newUrl;
                }
                
                // Update the search context for fetching properties
                if (window.searchContext) {
                    window.searchContext.updates_url = newUrl;
                }
                
                showAlert('success', 'Search URL updated successfully');
                
            } catch (error) {
                console.error('Error saving URL:', error);
                showAlert('error', 'Failed to save URL: ' + error.message);
            } finally {
                // Re-enable save button
                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Save';
                }
            }
            
            // Hide edit mode, show display mode
            urlEdit.classList.remove('active');
            urlDisplay.style.display = 'flex';
        }
        
        // Cancel Search URL edit
        function cancelSearchUrlEdit() {
            const urlDisplay = document.getElementById('searchUrlDisplay');
            const urlEdit = document.getElementById('searchUrlEdit');
            
            urlEdit.classList.remove('active');
            urlDisplay.style.display = 'flex';
        }
    </script>
@endsection
