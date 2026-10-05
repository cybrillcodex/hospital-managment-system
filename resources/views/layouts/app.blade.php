<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <x-pwa-head />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · CyberStay</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-night text-ink antialiased app-body {{ request()->routeIs('bookings.show') ? 'booking-detail-body' : '' }}">
    @php
        $hotel = \App\Support\TenantContext::tenant();
        $user = auth()->user();
        $solidIcons = request()->routeIs('pos.*');
    @endphp

    <div id="sidebar-overlay" class="fixed inset-0 z-30 hidden bg-stone-900/30 lg:hidden"></div>

    <aside id="sidebar" class="app-sidebar fixed inset-y-0 left-0 z-40 flex w-[17.5rem] -translate-x-full flex-col border-r border-line bg-panel lg:translate-x-0">
        <div class="flex items-center gap-3 border-b border-line px-5 py-5">
            <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-crimson text-white shadow-[0_8px_20px_rgb(159_18_57_/_0.25)]">
                <x-icon name="spark" size="md" :solid="$solidIcons" />
            </div>
            <div class="min-w-0">
                <div class="truncate text-sm font-semibold tracking-tight">CyberStay</div>
                <div class="truncate text-xs text-muted">{{ $hotel->name ?? 'Select hotel' }}</div>
            </div>
            <button type="button" data-sidebar-toggle class="ml-auto inline-flex h-10 w-10 items-center justify-center rounded-xl hover:bg-stone-100 lg:hidden" aria-label="Close menu">
                <x-icon name="x" :solid="$solidIcons" />
            </button>
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-3">
            @if($user->isSuperAdmin())
                <div class="nav-kicker">Platform</div>
                <a class="nav-link {{ request()->routeIs('hotels.*') ? 'active' : '' }}" href="{{ route('hotels.index') }}">
                    <x-icon name="building" :solid="$solidIcons" /> Hotels
                </a>
            @endif

            @if($hotel)
                <div class="nav-kicker">Front desk</div>
                <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                    <x-icon name="home" :solid="$solidIcons" /> Dashboard
                </a>
                <a class="nav-link {{ request()->routeIs('bookings.create') ? 'active' : '' }}" href="{{ route('bookings.create') }}">
                    <x-icon name="calendar-plus" :solid="$solidIcons" /> New booking
                </a>
                <a class="nav-link {{ request()->routeIs('pos.*') ? 'active' : '' }}" href="{{ route('pos.index') }}">
                    <x-icon name="cart" :solid="$solidIcons" /> POS
                </a>
                @if(! $user->isReceptionist())
                    <a class="nav-link {{ request()->routeIs('bookings.*') && !request()->routeIs('bookings.create') ? 'active' : '' }}" href="{{ route('bookings.index') }}">
                        <x-icon name="calendar" :solid="$solidIcons" /> Bookings
                    </a>
                    <a class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}" href="{{ route('reports.index') }}">
                        <x-icon name="trend" :solid="$solidIcons" /> Reports
                    </a>

                    <div class="nav-kicker">Property</div>
                    <a class="nav-link {{ request()->routeIs('rooms.*') ? 'active' : '' }}" href="{{ route('rooms.index') }}">
                        <x-icon name="bed" :solid="$solidIcons" /> Rooms
                    </a>
                    <a class="nav-link {{ request()->routeIs('customers.*') ? 'active' : '' }}" href="{{ route('customers.index') }}">
                        <x-icon name="users" :solid="$solidIcons" /> Customers
                    </a>
                @endif

                @if($user->isReceptionist())
                    <a class="nav-link {{ request()->routeIs('customers.*') ? 'active' : '' }}" href="{{ route('customers.index') }}">
                        <x-icon name="search" :solid="$solidIcons" /> Customer search
                    </a>
                @endif

                @if($user->canManageHotel())
                    <div class="nav-kicker">Operations</div>
                    <a class="nav-link {{ request()->routeIs('expenses.*') ? 'active' : '' }}" href="{{ route('expenses.index') }}">
                        <x-icon name="wallet" :solid="$solidIcons" /> Expenses
                    </a>
                    <a class="nav-link {{ request()->routeIs('employees.*') ? 'active' : '' }}" href="{{ route('employees.index') }}">
                        <x-icon name="briefcase" :solid="$solidIcons" /> Employees
                    </a>
                    <a class="nav-link {{ request()->routeIs('staff.*') ? 'active' : '' }}" href="{{ route('staff.index') }}">
                        <x-icon name="staff" :solid="$solidIcons" /> Staff
                    </a>
                    <a class="nav-link {{ request()->routeIs('bookings-report') ? 'active' : '' }}" href="{{ route('bookings-report') }}">
                        <x-icon name="receipt" :solid="$solidIcons" /> Bookings Report
                    </a>

                    <div class="nav-kicker">Settings</div>
                    <a class="nav-link {{ request()->routeIs('hotel-settings.*') ? 'active' : '' }}" href="{{ route('hotel-settings.show') }}">
                        <x-icon name="settings" :solid="$solidIcons" /> Hotel Settings
                    </a>
                @endif
            @endif
        </nav>

        <div class="border-t border-line p-4">
            <div class="mb-3 flex items-center gap-3">
                <x-icon-box tone="zinc" size="sm">
                    <x-icon name="user" size="sm" :solid="$solidIcons" />
                </x-icon-box>
                <div class="min-w-0">
                    <div class="truncate text-sm font-medium">{{ $user->name }}</div>
                    <div class="truncate text-xs text-muted">{{ $user->roleLabel() }}</div>
                </div>
            </div>
            @if($user->isSuperAdmin() && $hotel)
                <form method="POST" action="{{ route('hotels.leave') }}" class="mb-2">
                    @csrf
                    <button class="btn btn-ghost w-full text-sm">
                        <x-icon name="switch" size="sm" :solid="$solidIcons" /> Switch hotel
                    </button>
                </form>
            @endif
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="btn btn-ghost w-full text-sm">
                    <x-icon name="logout" size="sm" :solid="$solidIcons" /> Logout
                </button>
            </form>
        </div>
    </aside>

    <div class="app-shell lg:pl-[17.5rem]">
        <header class="app-header sticky top-0 z-20 flex items-center justify-between gap-3 border-b border-line bg-night/90 px-4 py-3 backdrop-blur lg:px-8">
            <div class="flex min-w-0 items-center gap-3">
                <button type="button" data-sidebar-toggle class="btn btn-ghost px-3 lg:hidden" aria-label="Open menu">
                    <x-icon name="menu" :solid="$solidIcons" />
                </button>
                <div class="min-w-0">
                    <h1 class="truncate text-lg font-semibold tracking-tight">@yield('title', 'Dashboard')</h1>
                    <p class="truncate text-xs text-muted">@yield('subtitle', $hotel->name ?? 'Hotel operations')</p>
                </div>
            </div>
            <div class="app-header-actions flex shrink-0 items-center gap-2">
                @yield('actions')
            </div>
        </header>

        <main class="app-main px-4 py-6 lg:px-8">
            @if(session('success'))
                <div class="alert mb-4 border border-emerald-200 bg-emerald-50 text-emerald-800">
                    <x-icon name="check" size="sm" :solid="$solidIcons" />
                    <span>{{ session('success') }}</span>
                </div>
            @endif
            @if(session('error'))
                <div class="alert mb-4 border border-rose-200 bg-rose-50 text-rose-800">
                    <x-icon name="alert" size="sm" :solid="$solidIcons" />
                    <span>{{ session('error') }}</span>
                </div>
            @endif
            @if(session('info'))
                <div class="alert mb-4 border border-line bg-panel text-muted">
                    <x-icon name="info" size="sm" :solid="$solidIcons" />
                    <span>{{ session('info') }}</span>
                </div>
            @endif
            @if($errors->any())
                <div class="alert mb-4 border border-rose-200 bg-rose-50 text-rose-800">
                    <x-icon name="alert" size="sm" :solid="$solidIcons" />
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            @yield('content')
        </main>
    </div>

    @if($hotel)
        <nav class="app-bottom-nav lg:hidden" aria-label="Primary">
            <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'is-active' : '' }}">
                <x-icon name="home" size="sm" :solid="$solidIcons" />
                <span>Home</span>
            </a>
            @if(! $user->isReceptionist())
                <a href="{{ route('bookings.index') }}" class="{{ request()->routeIs('bookings.*') && ! request()->routeIs('bookings.create') ? 'is-active' : '' }}">
                    <x-icon name="calendar" size="sm" :solid="$solidIcons" />
                    <span>Bookings</span>
                </a>
            @endif
            <a href="{{ route('bookings.create') }}" class="app-nav-fab {{ request()->routeIs('bookings.create') ? 'is-active' : '' }}" aria-label="New booking">
                <x-icon name="plus" size="sm" :solid="$solidIcons" />
            </a>
            <a href="{{ route('pos.index') }}" class="{{ request()->routeIs('pos.*') ? 'is-active' : '' }}">
                <x-icon name="cart" size="sm" :solid="$solidIcons" />
                <span>POS</span>
            </a>
            <button type="button" data-sidebar-toggle>
                <x-icon name="menu" size="sm" :solid="$solidIcons" />
                <span>Menu</span>
            </button>
        </nav>
    @endif
</body>
</html>
