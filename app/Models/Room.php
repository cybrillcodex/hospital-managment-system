<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'number', 'type', 'bed_type', 'floor', 'max_capacity', 'status', 'notes'])]
class Room extends Model
{
    use BelongsToTenant;

    public const TYPES = ['standard', 'single', 'double', 'twin', 'deluxe', 'suite', 'family', 'master'];

    public const BED_TYPES = ['single', 'double', 'triple', 'master'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function currentStay(): ?Booking
    {
        $today = now()->toDateString();

        return $this->bookings()
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
            ->orderBy('check_in')
            ->with('customer')
            ->first();
    }

    public function occupancyStatus(?string $date = null): string
    {
        if ($this->status === 'maintenance') {
            return 'maintenance';
        }

        $date ??= now()->toDateString();

        $booking = $this->relationLoaded('bookings')
            ? $this->bookings->first(function (Booking $booking) use ($date) {
                if ($booking->status === 'checked_in') {
                    return $booking->checked_in_at !== null;
                }

                if ($booking->status === 'reserved') {
                    return $booking->check_in->toDateString() <= $date
                        && $booking->check_out->toDateString() > $date;
                }

                return false;
            })
            : $this->bookings()
                ->where(function ($query) use ($date) {
                    $query->where(function ($checkedIn) {
                        $checkedIn->where('status', 'checked_in')
                            ->whereNotNull('checked_in_at');
                    })->orWhere(function ($reserved) use ($date) {
                        $reserved->where('status', 'reserved')
                            ->whereDate('check_in', '<=', $date)
                            ->whereDate('check_out', '>', $date);
                    });
                })
                ->orderByRaw("CASE WHEN status = 'checked_in' THEN 0 ELSE 1 END")
                ->orderBy('check_in')
                ->first();

        if (! $booking) {
            return 'available';
        }

        return $booking->status === 'checked_in' ? 'occupied' : 'reserved';
    }

    public function isAvailableFor(string $checkIn, string $checkOut, ?int $ignoreBookingId = null): bool
    {
        if ($this->status === 'maintenance') {
            return false;
        }

        return ! $this->bookings()
            ->overlapping($checkIn, $checkOut, $ignoreBookingId)
            ->exists();
    }
}
