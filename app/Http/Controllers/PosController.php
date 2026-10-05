<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\PosItem;
use Illuminate\Http\Request;

class PosController extends Controller
{
    public function index(Request $request)
    {
        $booking = null;

        if ($request->filled('booking_id')) {
            $booking = Booking::with(['room', 'customer', 'items'])
                ->whereIn('status', ['reserved', 'checked_in'])
                ->findOrFail($request->integer('booking_id'));
        }

        $activeBookings = Booking::with(['room', 'customer'])
            ->whereIn('status', ['reserved', 'checked_in'])
            ->latest()
            ->get();

        $items = PosItem::query()->where('is_active', true)->orderBy('name')->get();

        $todayItems = BookingItem::query()->whereDate('created_at', today());
        $todaySales = (float) (clone $todayItems)->sum('total');
        $todayCount = (int) (clone $todayItems)->sum('qty');

        $recentCharges = BookingItem::with('booking.room')
            ->latest()
            ->limit(6)
            ->get();

        return view('pos.index', compact('booking', 'activeBookings', 'items', 'todaySales', 'todayCount', 'recentCharges'));
    }

    public function addItem(Request $request, Booking $booking)
    {
        if (! $booking->isActive()) {
            return back()->with('error', 'Charges can only be added to active bookings.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'qty' => ['required', 'integer', 'min:1'],
            'unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        $qty = (int) $data['qty'];
        $unit = (float) $data['unit_price'];

        BookingItem::create([
            'booking_id' => $booking->id,
            'name' => $data['name'],
            'qty' => $qty,
            'unit_price' => $unit,
            'total' => round($qty * $unit, 2),
        ]);

        $booking->recalculateTotals();

        return back()->with('success', 'Charge added.');
    }

    public function removeItem(Booking $booking, BookingItem $item)
    {
        if ($item->booking_id !== $booking->id) {
            abort(404);
        }

        $item->delete();
        $booking->recalculateTotals();

        return back()->with('success', 'Charge removed.');
    }

    public function catalog()
    {
        $items = PosItem::query()->orderBy('name')->get();

        return view('pos.catalog', compact('items'));
    }

    public function storeCatalogItem(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'price' => ['required', 'numeric', 'min:0'],
        ]);

        PosItem::create([
            'name' => $data['name'],
            'price' => $data['price'],
            'is_active' => true,
        ]);

        return back()->with('success', 'POS item saved.');
    }

    public function destroyCatalogItem(PosItem $item)
    {
        $item->delete();

        return back()->with('success', 'POS item removed.');
    }
}
