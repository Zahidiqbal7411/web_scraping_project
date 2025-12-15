<nav class="navbar">
    <a href="{{ route('dashboard') }}" class="navbar-brand">{{ config('app.name') }}</a>
    <div class="navbar-user">
        <span class="user-name">{{ Auth::user()->name }}</span>
    </div>
</nav>
