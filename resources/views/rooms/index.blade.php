@extends('layouts.app')

@section('title', 'Rooms')
@section('subtitle', 'Physical rooms only — pricing is set at booking')
@section('actions')
    <a href="{{ route('rooms.create') }}" class="btn btn-primary">
        <x-icon name="plus" size="sm" /> Add room
    </a>
@endsection

@section('content')
    @php
        $totalRooms = $rooms->count();
        $stats = [
            ['label' => 'Available', 'key' => 'available', 'hint' => 'Ready for a guest', 'tone' => 'green', 'icon' => 'bed'],
            ['label' => 'Occupied', 'key' => 'occupied', 'hint' => 'Guests staying now', 'tone' => 'crimson', 'icon' => 'users'],
            ['label' => 'Reserved', 'key' => 'reserved', 'hint' => 'In-house reservations', 'tone' => 'amber', 'icon' => 'calendar'],
            ['label' => 'Maintenance', 'key' => 'maintenance', 'hint' => 'Unavailable rooms', 'tone' => 'zinc', 'icon' => 'info'],
        ];
    @endphp

    <section class="card hero-band mb-5 overflow-hidden p-5 sm:p-7">
        <div class="flex flex-wrap items-center justify-between gap-5">
            <div class="max-w-2xl">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-crimson">Property overview</p>
                <h2 class="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">Your rooms, at a glance</h2>
                <p class="mt-2 text-sm leading-6 text-muted">See what’s ready, welcome guests already staying, and spot rooms that need attention.</p>
            </div>
            <div class="flex items-center gap-3 rounded-2xl border border-line bg-white/80 px-4 py-3">
                <x-icon-box size="sm"><x-icon name="building" size="sm" /></x-icon-box>
                <div>
                    <div class="stat-num text-2xl font-semibold">{{ $totalRooms }}</div>
                    <div class="mt-1 text-xs text-muted">Total rooms</div>
                </div>
            </div>
        </div>
    </section>

    <section class="mb-8 grid grid-cols-2 gap-3 xl:grid-cols-4" aria-label="Room status summary">
        @foreach($stats as $stat)
            <div class="card p-4 sm:p-5">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-sm font-medium text-muted">{{ $stat['label'] }}</span>
                    <x-icon-box :tone="$stat['tone']" size="sm"><x-icon :name="$stat['icon']" size="sm" /></x-icon-box>
                </div>
                <div class="stat-num mt-4 text-3xl font-semibold">{{ $roomStatusCounts->get($stat['key'], 0) }}</div>
                <p class="mt-1 text-xs text-muted">{{ $stat['hint'] }}</p>
            </div>
        @endforeach
    </section>

    <section>
        <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold tracking-tight">All rooms</h2>
                <p class="mt-1 text-sm text-muted">Room details and today’s live status</p>
            </div>
            <span class="badge bg-white text-muted">{{ $totalRooms }} {{ $totalRooms === 1 ? 'room' : 'rooms' }}</span>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
            @forelse($rooms as $room)
                @php
                    $status = $room->occupancyStatus();
                    $roomPhoto = asset('images/rooms/room-'.(($loop->index % 6) + 1).'.jpg');
                    $tone = match ($status) {
                        'available' => 'green',
                        'occupied' => 'crimson',
                        'reserved' => 'amber',
                        default => 'zinc',
                    };
                @endphp
                <article class="card room-tile {{ $status }} group overflow-hidden p-0 transition duration-200 hover:-translate-y-1 hover:shadow-xl">
                    <div class="relative aspect-[16/9] overflow-hidden bg-panel-2">
                        <img
                            src="{{ $roomPhoto }}"
                            alt="{{ ucfirst($room->type) }} room {{ $room->number }}"
                            class="h-full w-full object-cover transition duration-500 group-hover:scale-105"
                            loading="lazy"
                        >
                        <div class="absolute inset-0 bg-gradient-to-t from-stone-950/40 via-transparent to-stone-950/10"></div>
                        <x-status-badge :status="$status" class="absolute left-3 top-3 shadow-sm">
                            {{ ucfirst($status) }}
                        </x-status-badge>
                        <span class="absolute bottom-3 left-3 rounded-lg bg-stone-950/45 px-2.5 py-1 text-[11px] font-medium capitalize text-white backdrop-blur-sm">
                            {{ $room->type }} room
                        </span>
                    </div>

                    <div class="p-4 sm:p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted">Room</p>
                                <h3 class="stat-num mt-1 text-2xl font-semibold">#{{ $room->number }}</h3>
                            </div>
                            <span class="mt-1 inline-flex items-center gap-1.5 rounded-lg bg-panel-2 px-2.5 py-1.5 text-xs font-medium text-muted">
                                <x-icon name="building" size="sm" />
                                Floor {{ $room->floor ?: '—' }}
                            </span>
                        </div>

                        <div class="mt-4 space-y-2.5 border-t border-line pt-3.5 text-xs text-muted">
                            <div class="flex items-center gap-2">
                                <x-icon name="bed" size="sm" class="text-crimson" />
                                <span class="capitalize">{{ $room->type }} · {{ $room->bed_type }} bed</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <x-icon name="users" size="sm" class="text-crimson" />
                                <span>Capacity: {{ $room->max_capacity }} {{ $room->max_capacity === 1 ? 'guest' : 'guests' }}</span>
                            </div>
                        </div>

                        <div class="mt-4 flex items-center justify-between gap-3">
                            <a class="inline-flex items-center gap-1.5 text-xs font-semibold text-crimson-soft hover:text-crimson" href="{{ route('rooms.edit', $room) }}">
                                Edit <x-icon name="edit" size="sm" />
                            </a>
                        </div>

                        <a
                            href="{{ route('rooms.edit', $room) }}"
                            class="mt-4 inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-xl border border-crimson/40 bg-white px-3 py-2 text-xs font-semibold text-crimson transition hover:border-crimson hover:bg-rose-50"
                        >
                            View details <x-icon name="arrow-right" size="sm" />
                        </a>
                    </div>
                </article>
            @empty
                <div class="card col-span-full flex flex-col items-center px-5 py-12 text-center">
                    <x-icon-box size="lg"><x-icon name="bed" /></x-icon-box>
                    <h3 class="mt-4 text-lg font-semibold">No rooms added yet</h3>
                    <p class="mt-1 max-w-sm text-sm text-muted">Add your first room to start organizing your property.</p>
                    <a href="{{ route('rooms.create') }}" class="btn btn-primary mt-5">
                        <x-icon name="plus" size="sm" /> Add your first room
                    </a>
                </div>
            @endforelse
        </div>
    </section>
@endsection
