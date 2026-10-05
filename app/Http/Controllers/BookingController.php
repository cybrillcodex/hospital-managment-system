<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingRoomHistory;
use App\Models\Customer;
use App\Models\Room;
use App\Services\BookingService;
use App\Support\IdCardStorage;
use App\Support\TenantContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class BookingController extends Controller
{
    public function __construct(
        private BookingService $bookings,
        private IdCardStorage $idCards,
    ) {}

    public function index(Request $request)
    {
        $status = $request->get('status', 'active');

        $bookings = Booking::with(['room', 'customer'])
            ->when($status === 'active', fn ($q) => $q->whereIn('status', ['reserved', 'checked_in']))
            ->when(in_array($status, ['reserved', 'checked_in', 'checked_out', 'cancelled'], true), fn ($q) => $q->where('status', $status))
            ->when($status === 'today', function ($q) {
                $today = now()->toDateString();
                $q->where(function ($inner) use ($today) {
                    $inner->whereDate('check_in', $today)->orWhereDate('check_out', $today);
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('bookings.index', compact('bookings', 'status'));
    }

    public function create(Request $request)
    {
        $rooms = $this->roomsForDesk();
        $selectedRoom = $request->integer('room_id') ?: null;

        return view('bookings.create', compact('rooms', 'selectedRoom'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $nights = $this->bookings->nights($data['check_in'], $data['check_out']);
        $persons = (int) $data['males'] + (int) $data['females'] + (int) $data['children'];

        $room = Room::query()->findOrFail((int) $data['room_id']);

        if ($nights < 1) {
            return back()->withInput()->withErrors(['check_out' => 'Check-out must be after check-in.']);
        }

        if ($persons < 1) {
            return back()->withInput()->withErrors(['males' => 'Enter at least one person staying.']);
        }

        if ($persons > (int) ($room->max_capacity ?? 0)) {
            return back()->withInput()->withErrors(['room_id' => 'Selected room max capacity is '.$room->max_capacity.' guests.']);
        }

        $this->bookings->assertRoomAvailable((int) $data['room_id'], $data['check_in'], $data['check_out']);
        $totals = $this->bookings->totals($nights, (float) $data['price_per_night']);
        $payNow = round((float) ($data['payment_now_amount'] ?? 0), 2);

        if ($payNow > 0 && empty($data['payment_now_method'])) {
            return back()->withInput()->withErrors(['payment_now_method' => 'Select cash or online for the rent paid now.']);
        }

        if ($payNow > (float) $totals['room_amount']) {
            return back()->withInput()->withErrors(['payment_now_amount' => 'Paid rent cannot exceed room amount.']);
        }

        $customer = $this->syncCustomer($data);

        $booking = Booking::create([
            'room_id' => $data['room_id'],
            'customer_id' => $customer->id,
            'check_in' => $data['check_in'],
            'check_out' => $data['check_out'],
            'price_per_night' => $data['price_per_night'],
            'notes' => $data['notes'] ?? null,
            'vehicle_number' => $data['vehicle_number'] ?? null,
            'males' => $data['males'],
            'females' => $data['females'],
            'children' => $data['children'],
            ...$totals,
            'created_by' => $request->user()->id,
            'status' => 'reserved',
            'id_card_front' => $this->idCards->store($request->file('id_card_front'), 'id-cards'),
            'id_card_back' => $this->idCards->store($request->file('id_card_back'), 'id-cards'),
        ]);

        $this->syncCompanions($booking, $request, $data['companions'] ?? []);
        $this->openRoomHistory($booking, now(), $request->user()->id);

        $payNow = round((float) ($data['payment_now_amount'] ?? 0), 2);
        if ($payNow > 0) {
            if (empty($data['payment_now_method'])) {
                return redirect()->route('bookings.show', $booking)
                    ->with('error', 'Select cash or online for the rent paid now.');
            }

            $booking->recordPayment(
                'room',
                $payNow,
                (string) $data['payment_now_method'],
                $request->user()->id,
                'Paid at booking'
            );
        }

        return redirect()->route('bookings.show', $booking)->with('success', 'Booking created.');
    }

    public function show(Booking $booking)
    {
        $booking->syncAccruedStayCharges();
        $booking->load(['room', 'customer', 'items', 'creator', 'companions', 'payments.receiver', 'roomHistories.room', 'roomHistories.switcher']);
        $switchableRooms = $this->switchableRooms($booking);

        return view('bookings.show', compact('booking', 'switchableRooms'));
    }

    public function edit(Booking $booking)
    {
        if (! $booking->isActive() && $booking->status !== 'checked_out') {
            return back()->with('error', 'Only active or checked-out bookings can be edited.');
        }

        $rooms = $this->roomsForDesk();
        $booking->load(['customer', 'companions']);

        return view('bookings.edit', compact('booking', 'rooms'));
    }

    public function update(Request $request, Booking $booking)
    {
        if (! $booking->isActive() && $booking->status !== 'checked_out') {
            return back()->with('error', 'Only active or checked-out bookings can be edited.');
        }

        $isActive = $booking->isActive();
        $data = $this->validated($request, $booking->id);
        $nights = $this->bookings->nights($data['check_in'], $data['check_out']);
        $persons = (int) $data['males'] + (int) $data['females'] + (int) $data['children'];

        $room = Room::query()->findOrFail((int) $data['room_id']);

        if ($nights < 1) {
            return back()->withInput()->withErrors(['check_out' => 'Check-out must be after check-in.']);
        }

        if ($persons < 1) {
            return back()->withInput()->withErrors(['males' => 'Enter at least one person staying.']);
        }

        if ($persons > (int) ($room->max_capacity ?? 0)) {
            return back()->withInput()->withErrors(['room_id' => 'Selected room max capacity is '.$room->max_capacity.' guests.']);
        }

        $this->bookings->assertRoomAvailable((int) $data['room_id'], $data['check_in'], $data['check_out'], $booking->id);
        $totals = $this->bookings->totals($nights, (float) $data['price_per_night'], (float) $booking->extras_amount);
        $customer = $this->syncCustomer($data, $booking->customer_id);

        $switchedAt = now();
        $oldRoomId = (int) $booking->room_id;

        DB::transaction(function () use ($request, $booking, $data, $customer, $totals, $switchedAt, $oldRoomId, $isActive): void {
            if ($oldRoomId !== (int) $data['room_id']) {
                if ($isActive) {
                    $this->ensureCurrentRoomHistory($booking, $switchedAt, $request->user()->id);
                    $this->closeCurrentRoomHistory($booking, $switchedAt);
                } else {
                    $booking->roomHistories()
                        ->latest('started_at')
                        ->first()
                        ?->update(['room_id' => $data['room_id']]);
                }
            }

            $booking->update([
                'room_id' => $data['room_id'],
                'customer_id' => $customer->id,
                'check_in' => $data['check_in'],
                'check_out' => $data['check_out'],
                'price_per_night' => $data['price_per_night'],
                'notes' => $data['notes'] ?? null,
                'vehicle_number' => $data['vehicle_number'] ?? null,
                'males' => $data['males'],
                'females' => $data['females'],
                'children' => $data['children'],
                ...$totals,
                'id_card_front' => $this->idCards->store($request->file('id_card_front'), 'id-cards', $booking->id_card_front),
                'id_card_back' => $this->idCards->store($request->file('id_card_back'), 'id-cards', $booking->id_card_back),
            ]);

            if ($isActive && $oldRoomId !== (int) $data['room_id']) {
                $this->openRoomHistory($booking, $switchedAt, $request->user()->id);
            }
        });

        $booking->refresh();
        $booking->applyPaymentTotals();
        $booking->save();

        $this->syncCompanions($booking, $request, $data['companions'] ?? []);

        return redirect()->route('bookings.show', $booking)->with('success', 'Booking updated.');
    }

    public function checkIn(Booking $booking)
    {
        if ($booking->status !== 'reserved') {
            return back()->with('error', 'Only reserved bookings can be checked in.');
        }

        $booking->update([
            'status' => 'checked_in',
            'checked_in_at' => now(),
        ]);

        return back()->with('success', 'Guest checked in.');
    }

    public function checkOut(Booking $booking)
    {
        if ($booking->status !== 'checked_in') {
            return back()->with('error', 'Only checked-in bookings can be checked out.');
        }

        $data = request()->validate([
            'checkout_note' => ['required', 'string', 'max:500'],
            'room_paid_now' => ['nullable', 'numeric', 'min:0'],
            'extras_paid_now' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'in:cash,online'],
            'waive_late_checkout_charge' => ['nullable', 'boolean'],
        ]);

        $checkedOutAt = now();
        $waiveLateCheckoutCharge = (bool) ($data['waive_late_checkout_charge'] ?? false);

        $updatedNights = $waiveLateCheckoutCharge
            ? $booking->plannedNights()
            : $booking->billedNights($checkedOutAt);
        $updatedRoomAmount = round($updatedNights * (float) $booking->price_per_night, 2);
        $updatedCheckOut = $waiveLateCheckoutCharge
            ? $booking->check_out->toDateString()
            : $booking->check_in->copy()->addDays($updatedNights)->toDateString();

        $updatedGrandTotal = round($updatedRoomAmount + (float) $booking->extras_amount, 2);
        $roomPaidNow = round((float) ($data['room_paid_now'] ?? 0), 2);
        $extrasPaidNow = round((float) ($data['extras_paid_now'] ?? 0), 2);

        if (($roomPaidNow > 0 || $extrasPaidNow > 0) && empty($data['payment_method'])) {
            return back()->withInput()->withErrors(['payment_method' => 'Select cash or online for this checkout payment.']);
        }

        $booking->update([
            'status' => 'checked_out',
            'checked_out_at' => $checkedOutAt,
            'check_out' => $updatedCheckOut,
            'nights' => $updatedNights,
            'room_amount' => $updatedRoomAmount,
            'grand_total' => $updatedGrandTotal,
            'checkout_note' => $data['checkout_note'],
        ]);

        $booking->refresh();

        if ($roomPaidNow > 0) {
            $booking->recordPayment('room', $roomPaidNow, (string) $data['payment_method'], request()->user()->id, 'Paid at checkout');
        }

        if ($extrasPaidNow > 0) {
            $booking->recordPayment('extras', $extrasPaidNow, (string) $data['payment_method'], request()->user()->id, 'Paid at checkout');
        }

        $booking->applyPaymentTotals();
        $booking->save();

        $this->closeCurrentRoomHistory($booking, $checkedOutAt);

        return back()->with('success', 'Guest checked out.');
    }

    public function recordPayment(Request $request, Booking $booking)
    {
        if (! $booking->isActive()) {
            return back()->with('error', 'Payments can only be recorded on active bookings.');
        }

        $booking->syncAccruedStayCharges();
        $booking->load(['items', 'payments']);

        $rentDates = collect($booking->rentNightDates())->map->toDateString()->all();

        $data = $request->validate([
            'type' => ['required', 'in:room,extras'],
            'method' => ['required', 'in:cash,online'],
            'rent_date' => [
                Rule::requiredIf($request->input('type') === 'room'),
                'nullable',
                'date',
                Rule::in($rentDates),
            ],
            'booking_item_id' => [
                'nullable',
                Rule::requiredIf($request->input('type') === 'extras'),
                Rule::exists('booking_items', 'id')->where('booking_id', $booking->id),
            ],
        ]);

        $itemId = isset($data['booking_item_id']) ? (int) $data['booking_item_id'] : null;
        $rentDate = $data['rent_date'] ?? null;
        $amount = $data['type'] === 'room'
            ? $booking->lineBalance('room', null, $rentDate)
            : $booking->lineBalance('extras', $itemId);

        if ($amount <= 0) {
            return back()->with('error', 'This charge is already paid.');
        }

        $label = $data['type'] === 'room'
            ? 'Room rent '.Carbon::parse((string) $rentDate)->format('d M Y')
            : (string) ($booking->items->firstWhere('id', $itemId)?->name ?? 'POS item');

        $booking->recordPayment(
            $data['type'],
            $amount,
            $data['method'],
            $request->user()->id,
            $label,
            $itemId,
            $rentDate
        );

        $how = $data['method'] === 'online' ? 'paid online' : 'paid (cash)';

        return back()->with('success', $label.' marked '.$how.'.');
    }

    public function switchRoom(Request $request, Booking $booking)
    {
        if (! $booking->isActive()) {
            return back()->with('error', 'Only active bookings can switch rooms.');
        }

        $tenantId = TenantContext::id();
        $data = $request->validate([
            'room_id' => ['required', Rule::exists('rooms', 'id')->where('tenant_id', $tenantId)],
        ]);

        $newRoom = Room::query()->findOrFail((int) $data['room_id']);
        $oldRoom = $booking->room;

        if ((int) $oldRoom->id === (int) $newRoom->id) {
            return back()->with('error', 'Selected room is already assigned to this booking.');
        }

        $guests = $booking->guestCount();
        if ($guests > (int) ($newRoom->max_capacity ?? 0)) {
            return back()->with('error', 'Selected room max capacity is '.$newRoom->max_capacity.' guests.');
        }

        $this->bookings->assertRoomAvailable(
            (int) $newRoom->id,
            $booking->check_in->toDateString(),
            $booking->check_out->toDateString(),
            $booking->id
        );

        $switchedAt = now();

        DB::transaction(function () use ($booking, $newRoom, $request, $switchedAt): void {
            $this->ensureCurrentRoomHistory($booking, $switchedAt, $request->user()->id);
            $this->closeCurrentRoomHistory($booking, $switchedAt);

            $booking->update([
                'room_id' => $newRoom->id,
            ]);

            $this->openRoomHistory($booking, $switchedAt, $request->user()->id);
        });

        return back()->with('success', 'Room switched from '.$oldRoom->number.' to '.$newRoom->number.'.');
    }

    private function lateCheckoutSummary(Booking $booking, ?Carbon $checkedOutAt = null): array
    {
        $checkedOutAt ??= now();
        $scheduledCheckOutAt = $booking->check_out->copy()->setTime(12, 0);

        if (! $checkedOutAt->greaterThan($scheduledCheckOutAt)) {
            return [
                'late_days' => 0,
                'late_charge' => 0.0,
                'scheduled_checkout_at' => $scheduledCheckOutAt,
            ];
        }

        $lateSeconds = $scheduledCheckOutAt->diffInSeconds($checkedOutAt);
        $lateDays = (int) ceil($lateSeconds / 86400);
        $lateCharge = round($lateDays * (float) $booking->price_per_night, 2);

        return [
            'late_days' => $lateDays,
            'late_charge' => $lateCharge,
            'scheduled_checkout_at' => $scheduledCheckOutAt,
        ];
    }

    public function cancel(Booking $booking)
    {
        if (! $booking->isActive() && $booking->status !== 'checked_out') {
            return back()->with('error', 'This booking cannot be cancelled.');
        }

        $booking->update(['status' => 'cancelled']);
        $this->closeCurrentRoomHistory($booking, now());

        return redirect()->route('bookings.index')->with('success', 'Booking cancelled.');
    }

    public function bill(Booking $booking)
    {
        $booking->load(['room', 'customer', 'items', 'tenant', 'companions']);

        return view('bookings.bill', compact('booking'));
    }

    public function downloadServices(Booking $booking): Response
    {
        $booking->load(['room', 'customer', 'items', 'tenant', 'companions']);

        $fileName = sprintf('booking-%d-services.pdf', $booking->id);

        return Pdf::loadView('bookings.services-pdf', [
            'booking' => $booking,
            'generatedAt' => now(),
        ])->setPaper('a4')->download($fileName);
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $tenantId = TenantContext::id();

        return $request->validate([
            'room_id' => ['required', Rule::exists('rooms', 'id')->where('tenant_id', $tenantId)],
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'guest_name' => ['required', 'string', 'max:120'],
            'father_name' => ['nullable', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30'],
            'cnic' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'vehicle_number' => ['nullable', 'string', 'max:40'],
            'males' => ['required', 'integer', 'min:0'],
            'females' => ['required', 'integer', 'min:0'],
            'children' => ['required', 'integer', 'min:0'],
            'check_in' => ['required', 'date'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'price_per_night' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
            'id_card_front' => ['nullable', 'image', 'max:4096'],
            'id_card_back' => ['nullable', 'image', 'max:4096'],
            'companions' => ['nullable', 'array'],
            'companions.*.id' => ['nullable', 'integer'],
            'companions.*.name' => ['nullable', 'string', 'max:120'],
            'companions.*.father_name' => ['nullable', 'string', 'max:120'],
            'companions.*.cnic' => ['nullable', 'string', 'max:30'],
            'companions.*.address' => ['nullable', 'string', 'max:500'],
            'companions.*.id_card_front' => ['nullable', 'image', 'max:4096'],
            'companions.*.id_card_back' => ['nullable', 'image', 'max:4096'],
            'payment_now_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_now_method' => ['nullable', 'in:cash,online'],
        ]);
    }

    private function syncCustomer(array $data, ?int $fallbackId = null): Customer
    {
        $customer = null;

        if (! empty($data['customer_id'])) {
            $customer = Customer::find($data['customer_id']);
        } elseif ($fallbackId) {
            $customer = Customer::find($fallbackId);
        }

        $payload = [
            'name' => $data['guest_name'],
            'father_name' => $data['father_name'] ?? null,
            'phone' => $data['phone'],
            'cnic' => $data['cnic'] ?? null,
            'address' => $data['address'] ?? null,
        ];

        if ($customer) {
            $customer->update($payload);

            return $customer;
        }

        return Customer::create($payload);
    }

    private function syncCompanions(Booking $booking, Request $request, array $companions): void
    {
        $keptIds = [];

        foreach ($companions as $index => $companion) {
            $name = trim((string) ($companion['name'] ?? ''));
            $cnic = trim((string) ($companion['cnic'] ?? ''));

            if ($name === '' && $cnic === '') {
                continue;
            }

            if ($name === '') {
                continue;
            }

            $existing = null;
            if (! empty($companion['id'])) {
                $existing = $booking->companions()->whereKey($companion['id'])->first();
            }

            $front = $this->idCards->store(
                $request->file("companions.$index.id_card_front"),
                'id-cards',
                $existing?->id_card_front
            );
            $back = $this->idCards->store(
                $request->file("companions.$index.id_card_back"),
                'id-cards',
                $existing?->id_card_back
            );

            if ($existing) {
                $existing->update([
                    'name' => $name,
                    'father_name' => trim((string) ($companion['father_name'] ?? '')) ?: null,
                    'cnic' => $cnic ?: null,
                    'address' => trim((string) ($companion['address'] ?? '')) ?: null,
                    'id_card_front' => $front,
                    'id_card_back' => $back,
                ]);
                $keptIds[] = $existing->id;
            } else {
                $created = $booking->companions()->create([
                    'name' => $name,
                    'father_name' => trim((string) ($companion['father_name'] ?? '')) ?: null,
                    'cnic' => $cnic ?: null,
                    'address' => trim((string) ($companion['address'] ?? '')) ?: null,
                    'id_card_front' => $front,
                    'id_card_back' => $back,
                ]);
                $keptIds[] = $created->id;
            }
        }

        $removed = $booking->companions()->when($keptIds, fn ($q) => $q->whereNotIn('id', $keptIds))->get();

        foreach ($removed as $row) {
            if ($row->id_card_front) {
                Storage::disk('public')->delete($row->id_card_front);
            }
            if ($row->id_card_back) {
                Storage::disk('public')->delete($row->id_card_back);
            }
            $row->delete();
        }
    }

    private function switchableRooms(Booking $booking)
    {
        return Room::query()
            ->get()
            ->filter(function (Room $room) use ($booking): bool {
                if ($room->id === $booking->room_id) {
                    return true;
                }

                return $room->isAvailableFor(
                    $booking->check_in->toDateString(),
                    $booking->check_out->toDateString(),
                    $booking->id
                );
            })
            ->sortBy(function (Room $room): string {
                return $this->floorSortKey($room->floor).$this->roomSortKey($room->number);
            })
            ->values();
    }

    private function ensureCurrentRoomHistory(Booking $booking, Carbon $switchedAt, ?int $userId): void
    {
        $openEntry = $booking->roomHistories()->whereNull('ended_at')->latest('started_at')->first();

        if ($openEntry) {
            return;
        }

        $this->openRoomHistory($booking, $switchedAt, $userId);
    }

    private function openRoomHistory(Booking $booking, Carbon $startedAt, ?int $userId): void
    {
        BookingRoomHistory::create([
            'tenant_id' => $booking->tenant_id,
            'booking_id' => $booking->id,
            'room_id' => $booking->room_id,
            'switched_by' => $userId,
            'started_at' => $startedAt,
            'ended_at' => null,
        ]);
    }

    private function closeCurrentRoomHistory(Booking $booking, Carbon $endedAt): void
    {
        $booking->roomHistories()
            ->whereNull('ended_at')
            ->update(['ended_at' => $endedAt]);
    }

    private function roomsForDesk()
    {
        $today = now()->toDateString();

        return Room::query()
            ->with(['bookings' => function ($q) use ($today) {
                $q->whereIn('status', ['reserved', 'checked_in'])
                    ->where('check_in', '<=', $today)
                    ->where('check_out', '>', $today)
                    ->with('customer');
            }])
            ->get()
            ->sortBy(function (Room $room): string {
                return $this->floorSortKey($room->floor).$this->roomSortKey($room->number);
            })
            ->values();
    }

    private function floorSortKey(?string $floor): string
    {
        $label = trim((string) $floor);

        if ($label === '') {
            return '99|';
        }

        $normalized = mb_strtolower($label);

        if (str_contains($normalized, 'ground')) {
            return '00|';
        }

        if (preg_match('/(\d+)/', $normalized, $matches) === 1) {
            return sprintf('01|%04d|', (int) $matches[1]);
        }

        return '50|'.$normalized.'|';
    }

    private function roomSortKey(string $roomNumber): string
    {
        $roomNumber = trim($roomNumber);

        if ($roomNumber === '') {
            return 'zzzzzzzzzz';
        }

        if (is_numeric($roomNumber)) {
            return sprintf('%010d', (int) $roomNumber);
        }

        return mb_strtolower($roomNumber);
    }
}
