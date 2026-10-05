@extends('layouts.app')

@section('title', 'POS')
@section('subtitle', 'Add tea, food, and extras to a stay')
@section('actions')
    @if(auth()->user()->canManageHotel())
        <a href="{{ route('pos.catalog') }}" class="btn btn-ghost">
            <x-icon name="food" size="sm" solid /> Catalog
        </a>
    @endif
@endsection

@section('content')
    <section class="pos-hero mb-6" style="--pos-hero-image: url('{{ asset('images/pos-hotel.jpg') }}')">
        <div class="pos-hero-copy">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-white/75">Front desk till</p>
            <h2 class="mt-2 text-3xl font-semibold tracking-tight text-white">Point of sale</h2>
            <p class="mt-1 max-w-2xl text-sm text-white/85">Pick a room, tap an item, done. Charges attach to the stay and show on the bill.</p>
        </div>
        <div class="pos-stats">
            <div class="pos-stat">
                <x-icon-box tone="crimson"><x-icon name="users" solid /></x-icon-box>
                <div>
                    <div class="text-xs font-semibold uppercase tracking-wider text-muted">Open stays</div>
                    <div class="stat-num mt-1 text-2xl font-semibold">{{ $activeBookings->count() }}</div>
                </div>
            </div>
            <div class="pos-stat">
                <x-icon-box tone="crimson"><x-icon name="bookmark" solid /></x-icon-box>
                <div>
                    <div class="text-xs font-semibold uppercase tracking-wider text-muted">Catalog</div>
                    <div class="stat-num mt-1 text-2xl font-semibold">{{ $items->count() }}</div>
                </div>
            </div>
            <div class="pos-stat">
                <x-icon-box tone="crimson"><x-icon name="cart" solid /></x-icon-box>
                <div>
                    <div class="text-xs font-semibold uppercase tracking-wider text-muted">Selected extras</div>
                    <div class="stat-num mt-1 text-2xl font-semibold">{{ $booking ? $booking->items->count() : 0 }}</div>
                </div>
            </div>
            <div class="pos-stat">
                <x-icon-box tone="crimson"><x-icon name="money" solid /></x-icon-box>
                <div>
                    <div class="text-xs font-semibold uppercase tracking-wider text-muted">Today's sales</div>
                    <div class="stat-num mt-1 text-2xl font-semibold">@money($todaySales)</div>
                </div>
            </div>
        </div>
    </section>

    <section class="mb-6">
        <div class="pos-section-heading">
            <x-icon-box tone="crimson"><x-icon name="bed" solid /></x-icon-box>
            <div>
                <h3 class="text-lg font-semibold tracking-tight">Choose a stay</h3>
                <p class="mt-0.5 text-sm text-muted">Only reserved and in-house stays</p>
            </div>
        </div>

        @if($activeBookings->isEmpty())
            <div class="pos-no-stays">
                <x-icon-box tone="crimson"><x-icon name="bed" solid /></x-icon-box>
                <div>
                    <p class="font-semibold">No active stays right now</p>
                    <p class="mt-1 text-sm text-muted">Create a booking first, then add extras to the guest's bill here.</p>
                </div>
                <a href="{{ route('bookings.create') }}" class="btn btn-primary sm:ml-auto">
                    <x-icon name="plus" size="sm" solid /> New booking
                </a>
            </div>
        @else
            <div class="pos-stays">
                @foreach($activeBookings as $active)
                    <a href="{{ route('pos.index', ['booking_id' => $active->id]) }}" class="pos-stay-card {{ $booking?->id === $active->id ? 'is-selected' : '' }}" aria-current="{{ $booking?->id === $active->id ? 'true' : 'false' }}">
                        <img class="pos-room-photo" src="{{ asset('images/cyberstay-room.jpg') }}" alt="" loading="lazy">
                        <div class="pos-stay-details">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <h4 class="stat-num text-lg font-semibold">Room {{ $active->room->number }}</h4>
                                <x-status-badge :status="$active->status">{{ $active->statusLabel() }}</x-status-badge>
                            </div>
                            <p class="mt-1 truncate text-sm font-medium">{{ $active->customer->name }}</p>
                            <p class="mt-2 flex items-center gap-1.5 text-xs text-muted">
                                <x-icon name="calendar" size="sm" solid />
                                {{ $active->check_in->format('d M') }} → {{ $active->check_out->format('d M') }}
                            </p>
                        </div>
                        <span class="pos-stay-arrow" aria-hidden="true"><x-icon name="arrow-right" size="sm" solid /></span>
                    </a>
                @endforeach
            </div>
        @endif
    </section>

    @if($booking)
        <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="space-y-5">
                <section class="card p-5 sm:p-6">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <x-section-title icon="cart" hint="One tap adds 1 quantity" solid>Quick items</x-section-title>
                        <a class="text-sm font-medium text-crimson" href="{{ route('bookings.show', $booking) }}">Open stay</a>
                    </div>

                    @if($items->isNotEmpty())
                        <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3">
                            @foreach($items as $item)
                                <form method="POST" action="{{ route('pos.items.store', $booking) }}">
                                    @csrf
                                    <input type="hidden" name="name" value="{{ $item->name }}">
                                    <input type="hidden" name="qty" value="1">
                                    <input type="hidden" name="unit_price" value="{{ $item->price }}">
                                    <button class="pos-tile">
                                        <span class="text-sm font-semibold">{{ $item->name }}</span>
                                        <span class="stat-num text-lg font-semibold text-crimson">@money($item->price)</span>
                                        <span class="text-[11px] text-muted">Tap to add</span>
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    @else
                        <p class="mt-4 text-sm text-muted">No catalog items yet. Add a custom charge below{{ auth()->user()->canManageHotel() ? ', or build a catalog' : '' }}.</p>
                    @endif
                </section>

                <section class="card p-5 sm:p-6">
                    <x-section-title icon="plus" tone="amber" hint="Use this for anything not in the catalog" solid>Custom charge</x-section-title>
                    <form method="POST" action="{{ route('pos.items.store', $booking) }}" class="mt-5 grid gap-3 sm:grid-cols-12">
                        @csrf
                        <div class="sm:col-span-6">
                            <label class="field-label"><x-icon name="food" size="sm" solid /> Item</label>
                            <input class="input" name="name" placeholder="Tea, laundry, extra bed…" required>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="field-label"><x-icon name="users" size="sm" solid /> Qty</label>
                            <input class="input" type="number" min="1" name="qty" value="1" required>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="field-label"><x-icon name="money" size="sm" solid /> Price</label>
                            <input class="input" type="number" min="0" step="0.01" name="unit_price" placeholder="0" required>
                        </div>
                        <div class="flex items-end sm:col-span-2">
                            <button class="btn btn-primary w-full"><x-icon name="plus" size="sm" solid /> Add</button>
                        </div>
                    </form>
                </section>
            </div>

            <aside class="card bill-sticky p-5 sm:p-6">
                <x-section-title icon="receipt" hint="Room {{ $booking->room->number }}" solid>Ticket</x-section-title>
                <div class="mt-4 rounded-2xl bg-panel-2 p-4">
                    <div class="font-semibold">{{ $booking->customer->name }}</div>
                    <div class="mt-1 text-sm text-muted">Room {{ $booking->room->number }} · {{ $booking->statusLabel() }}</div>
                    <div class="mt-1 text-xs text-muted">{{ $booking->check_in->format('d M') }} → {{ $booking->check_out->format('d M') }}</div>
                </div>

                <div class="mt-2">
                    @forelse($booking->items as $item)
                        <div class="ticket-line">
                            <div class="min-w-0">
                                <div class="truncate font-medium">{{ $item->name }}</div>
                                <div class="text-xs text-muted">{{ $item->qty }} × @money($item->unit_price)</div>
                            </div>
                            <div class="stat-num text-sm font-semibold">@money($item->total)</div>
                            <form method="POST" action="{{ route('pos.items.destroy', [$booking, $item]) }}">
                                @csrf
                                @method('DELETE')
                                <button class="inline-flex h-9 w-9 items-center justify-center rounded-xl text-crimson hover:bg-rose-50" aria-label="Remove">
                                    <x-icon name="trash" size="sm" solid />
                                </button>
                            </form>
                        </div>
                    @empty
                        <p class="py-6 text-sm text-muted">No extras on this stay yet.</p>
                    @endforelse
                </div>

                <div class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between"><span class="text-muted">Room</span><span class="stat-num">@money($booking->room_amount)</span></div>
                    <div class="flex justify-between"><span class="text-muted">Extras</span><span class="stat-num">@money($booking->extras_amount)</span></div>
                    <div class="flex items-end justify-between border-t border-line pt-3">
                        <span class="text-muted">Stay total</span>
                        <span class="stat-num text-3xl font-semibold">@money($booking->grand_total)</span>
                    </div>
                </div>

                <a class="btn btn-ghost mt-5 w-full" href="{{ route('bookings.show', $booking) }}">
                    <x-icon name="arrow-right" size="sm" solid /> Open booking
                </a>
                <a class="btn btn-primary mt-2 w-full" href="{{ route('bookings.bill', $booking) }}">
                    <x-icon name="print" size="sm" solid /> Print bill
                </a>
            </aside>
        </div>
    @elseif($activeBookings->isNotEmpty())
        <div class="pos-empty-state mb-6">
            <x-icon-box class="mx-auto" tone="crimson"><x-icon name="cart" solid /></x-icon-box>
            <p class="mt-3 text-lg font-semibold tracking-tight">Select a stay to start charging</p>
            <p class="mt-1 text-sm text-muted">Tap a room card above. Items will be added to that guest's bill.</p>
        </div>
    @endif

    @unless($booking)
        <div class="grid items-start gap-6 lg:grid-cols-2">
            <section class="card p-5 sm:p-6">
                <x-section-title icon="spark" hint="Three quick steps" solid>How POS works</x-section-title>
                <ol class="pos-steps mt-5">
                    <li><span class="pos-step-num">1</span><div><p class="font-semibold">Choose a stay</p><p class="text-sm text-muted">Pick a reserved or checked-in room from the cards above.</p></div></li>
                    <li><span class="pos-step-num">2</span><div><p class="font-semibold">Tap items</p><p class="text-sm text-muted">Tea, food or laundry — one tap adds one quantity, or add a custom charge.</p></div></li>
                    <li><span class="pos-step-num">3</span><div><p class="font-semibold">Settle on the bill</p><p class="text-sm text-muted">Extras are added to the stay total and appear on the printed bill.</p></div></li>
                </ol>
            </section>

            <section class="card p-5 sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <x-section-title icon="receipt" hint="{{ $todayCount }} item(s) sold today" solid>Recent charges</x-section-title>
                </div>
                <div class="mt-3">
                    @forelse($recentCharges as $charge)
                        <div class="ticket-line">
                            <div class="min-w-0">
                                <div class="truncate font-medium">{{ $charge->name }}</div>
                                <div class="text-xs text-muted">
                                    Room {{ $charge->booking?->room?->number ?? '—' }} · {{ $charge->qty }} × @money($charge->unit_price) · {{ $charge->created_at->diffForHumans() }}
                                </div>
                            </div>
                            <div class="stat-num text-sm font-semibold">@money($charge->total)</div>
                        </div>
                    @empty
                        <p class="py-6 text-sm text-muted">No extras charged yet. Sales will show up here.</p>
                    @endforelse
                </div>
            </section>
        </div>
    @endunless
@endsection
