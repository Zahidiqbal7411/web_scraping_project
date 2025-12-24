<nav class="navbar">
    <div style="display: flex; align-items: center; gap: 1rem;">
        <button class="mobile-menu-btn" onclick="toggleMobileSidebar()" title="Toggle Menu">☰</button>
        <a href="{{ route('dashboard') }}" class="navbar-brand">{{ config('app.name') }}</a>
    </div>
    <div class="navbar-user">
        <span class="user-name">{{ Auth::user() ? Auth::user()->name : 'Guest' }}</span>
    </div>
</nav>

