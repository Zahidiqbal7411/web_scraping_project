@extends('layouts.app')

@section('styles')
<style>
    /* Dashboard Specific Styles */
    .welcome-section {
        margin-bottom: 2rem;
    }

    .welcome-title {
        font-size: 2rem;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
    }

    .welcome-subtitle {
        color: var(--text-secondary);
        font-size: 1rem;
    }

    /* Quick Actions Grid */
    .section-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 1rem;
    }

    .actions-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .action-card {
        background: var(--card-bg);
        border: 1px solid var(--card-border);
        border-radius: 12px;
        padding: 1.5rem;
        text-decoration: none;
        color: inherit;
        transition: all 0.2s ease;
        box-shadow: var(--shadow-sm);
        display: block;
    }

    .action-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-md);
        border-color: var(--primary);
    }

    .action-icon {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 1rem;
        font-size: 1.5rem;
    }

    .action-icon.blue {
        background: linear-gradient(135deg, hsl(220, 85%, 55%), hsl(200, 80%, 50%));
        color: white;
    }

    .action-icon.green {
        background: linear-gradient(135deg, hsl(142, 70%, 45%), hsl(160, 70%, 45%));
        color: white;
    }

    .action-icon.purple {
        background: linear-gradient(135deg, hsl(280, 70%, 55%), hsl(300, 70%, 50%));
        color: white;
    }

    .action-icon.orange {
        background: linear-gradient(135deg, hsl(30, 90%, 55%), hsl(40, 90%, 50%));
        color: white;
    }

    .action-title {
        font-size: 1.125rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
        color: var(--text-primary);
    }

    .action-description {
        font-size: 0.875rem;
        color: var(--text-secondary);
        line-height: 1.5;
    }

    /* Stats Section */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
    }

    .stat-card {
        background: var(--card-bg);
        border: 1px solid var(--card-border);
        border-radius: 10px;
        padding: 1.25rem;
        box-shadow: var(--shadow-sm);
    }

    .stat-label {
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 0.5rem;
    }

    .stat-value {
        font-size: 1.75rem;
        font-weight: 700;
        color: var(--text-primary);
    }
</style>
@endsection

@section('content')
    <!-- Welcome Section -->
    <div class="welcome-section">
        <h1 class="welcome-title">Welcome back, {{ Auth::user()->name }}!</h1>
        <p class="welcome-subtitle">Manage your property searches and scraped data from here.</p>
    </div>

    <!-- Quick Actions removed as they are now in the sidebar -->
@endsection