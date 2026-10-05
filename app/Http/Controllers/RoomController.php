<?php

namespace App\Http\Controllers;

use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoomController extends Controller
{
    public function index()
    {
        $today = now()->toDateString();
        $rooms = Room::query()
            ->with(['bookings' => fn ($query) => $query
                ->select(['id', 'room_id', 'status', 'checked_in_at', 'check_in', 'check_out'])
                ->where(function ($query) use ($today) {
                    $query->where(function ($checkedIn) {
                        $checkedIn->where('status', 'checked_in')
                            ->whereNotNull('checked_in_at');
                    })->orWhere(function ($reserved) use ($today) {
                        $reserved->where('status', 'reserved')
                            ->whereDate('check_in', '<=', $today)
                            ->whereDate('check_out', '>', $today);
                    });
                })
                ->orderByRaw("CASE WHEN status = 'checked_in' THEN 0 ELSE 1 END")
                ->orderBy('check_in')])
            ->orderBy('number')
            ->get();
        $roomStatusCounts = $rooms->countBy(fn (Room $room): string => $room->occupancyStatus());

        return view('rooms.index', compact('rooms', 'roomStatusCounts'));
    }

    public function create()
    {
        return view('rooms.create');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        Room::create($data);

        return redirect()->route('rooms.index')->with('success', 'Room added.');
    }

    public function edit(Room $room)
    {
        return view('rooms.edit', compact('room'));
    }

    public function update(Request $request, Room $room)
    {
        $room->update($this->validated($request, $room->id));

        return redirect()->route('rooms.index')->with('success', 'Room updated.');
    }

    public function destroy(Room $room)
    {
        if ($room->bookings()->exists()) {
            return back()->with('error', 'This room has bookings and cannot be deleted.');
        }

        $room->delete();

        return redirect()->route('rooms.index')->with('success', 'Room removed.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $tenantId = $request->user()->isSuperAdmin()
            ? session('current_tenant_id')
            : $request->user()->tenant_id;

        return $request->validate([
            'number' => [
                'required',
                'string',
                'max:20',
                Rule::unique('rooms', 'number')->where('tenant_id', $tenantId)->ignore($ignoreId),
            ],
            'type' => ['required', Rule::in(Room::TYPES)],
            'bed_type' => ['required', Rule::in(Room::BED_TYPES)],
            'floor' => ['nullable', 'string', 'max:20'],
            'max_capacity' => ['required', 'integer', 'min:1', 'max:20'],
            'status' => ['required', Rule::in(['available', 'maintenance'])],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
    }
}
