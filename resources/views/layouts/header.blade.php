<nav class="navbar">
    <a href="{{ route('dashboard') }}" class="navbar-brand">{{ config('app.name') }}</a>
    <div class="navbar-user">
        <span class="user-name">{{ Auth::user() ? Auth::user()->name : 'Guest' }}</span>
    </div>
</nav>
