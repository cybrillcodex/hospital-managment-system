@extends('layouts.app')

@section('title', 'POS catalog')
@section('subtitle', 'Quick extras the desk can tap')
@section('actions')
    <a href="{{ route('pos.index') }}" class="btn btn-ghost">
        <x-icon name="cart" size="sm" solid /> Back to POS
    </a>
@endsection

@section('content')
    <div class="card hero-band mb-6 p-5 sm:p-6">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-crimson">Menu</p>
        <h2 class="mt-2 text-2xl font-semibold tracking-tight">Catalog items</h2>
        <p class="mt-1 text-sm text-muted">These appear as one-tap buttons on the POS. Price can still be overridden with a custom charge.</p>
    </div>

    <form method="POST" class="card mb-6 grid gap-3 p-5 sm:grid-cols-12" action="{{ route('pos.catalog.store') }}">
        @csrf
        <div class="sm:col-span-6">
            <label class="field-label"><x-icon name="food" size="sm" solid /> Item name</label>
            <input class="input" name="name" placeholder="Tea, breakfast, laundry…" required>
        </div>
        <div class="sm:col-span-4">
            <label class="field-label"><x-icon name="money" size="sm" solid /> Default price</label>
            <input class="input" type="number" min="0" step="0.01" name="price" placeholder="0" required>
        </div>
        <div class="flex items-end sm:col-span-2">
            <button class="btn btn-primary w-full"><x-icon name="plus" size="sm" solid /> Add</button>
        </div>
    </form>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        @forelse($items as $item)
            <div class="card flex items-center justify-between gap-3 p-4">
                <div>
                    <div class="font-semibold">{{ $item->name }}</div>
                    <div class="stat-num mt-1 text-lg font-semibold text-crimson">@money($item->price)</div>
                </div>
                <form method="POST" action="{{ route('pos.catalog.destroy', $item) }}">
                    @csrf
                    @method('DELETE')
                    <button class="inline-flex h-10 w-10 items-center justify-center rounded-xl text-crimson hover:bg-rose-50" aria-label="Remove">
                        <x-icon name="trash" size="sm" solid />
                    </button>
                </form>
            </div>
        @empty
            <p class="col-span-full text-sm text-muted">No catalog items yet.</p>
        @endforelse
    </div>
@endsection
