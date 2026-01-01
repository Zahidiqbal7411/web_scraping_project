@extends('layouts.app')

@section('content')
<style>
    .welcome-container {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: calc(100vh - 200px);
        padding: 2rem;
    }

    .welcome-card {
        background: var(--card-bg);
        border: 1px solid var(--card-border);
        border-radius: 16px;
        padding: 60px 80px;
        width: 100%;
        max-width: 900px;
        text-align: center;
        box-shadow: var(--shadow-lg);
        animation: fadeIn 0.6s ease-out;
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

    .welcome-icon {
        width: 90px;
        height: 90px;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 30px;
        box-shadow: 0 8px 30px rgba(59, 130, 246, 0.3);
    }

    .welcome-icon i {
        font-size: 40px;
        color: white;
    }

    .user-greeting {
        font-size: 1rem;
        color: var(--text-secondary);
        margin-bottom: 10px;
    }

    .user-greeting strong {
        color: var(--primary);
    }

    .dashboard-heading {
        font-size: 3rem;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 5px;
    }

    .welcome-title {
        font-size: 1.75rem;
        font-weight: 600;
        color: var(--primary);
        margin-bottom: 20px;
    }

    .welcome-subtitle {
        font-size: 1rem;
        color: var(--text-secondary);
        margin-bottom: 40px;
        max-width: 600px;
        margin-left: auto;
        margin-right: auto;
        line-height: 1.6;
    }

    .btn-get-started {
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        border: none;
        padding: 16px 50px;
        font-size: 1.1rem;
        font-weight: 600;
        border-radius: 50px;
        color: white;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 12px;
        transition: all 0.3s ease;
        box-shadow: 0 6px 25px rgba(59, 130, 246, 0.35);
    }

    .btn-get-started:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 35px rgba(59, 130, 246, 0.45);
        color: white;
    }

    .btn-get-started i {
        transition: transform 0.3s ease;
    }

    .btn-get-started:hover i {
        transform: translateX(5px);
    }

    @media (max-width: 768px) {
        .welcome-card {
            padding: 40px 30px;
        }
        .welcome-title {
            font-size: 1.8rem;
        }
    }
</style>

<div class="welcome-container">
    <div class="welcome-card">
        <div class="welcome-icon">
            <i class="fas fa-chart-line"></i>
        </div>
        
        <p class="user-greeting">
            Welcome back, <strong>{{ Auth::user()->name ?? 'User' }}</strong>
        </p>
        
        <h1 class="dashboard-heading">Scraping Dashboard</h1>
        <h2 class="welcome-title">Property Scraping Software</h2>
        <p class="welcome-subtitle">
            Your intelligent solution for real estate data collection, analysis, and market insights
        </p>

        <a href="{{ route('internal-property.index') }}" class="btn-get-started">
            <span>Explore Properties</span>
            <i class="fas fa-arrow-right"></i>
        </a>
    </div>
</div>
@endsection