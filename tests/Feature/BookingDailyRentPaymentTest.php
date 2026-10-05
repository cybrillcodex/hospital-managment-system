<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Customer;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BookingDailyRentPaymentTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-29 15:30:00'));

        $this->tenant = Tenant::create([
            'name' => 'Test Hotel',
            'slug' => 'test-hotel',
        ]);

        TenantContext::set($this->tenant->id);

        $this->user = User::factory()->create([
            'role' => 'owner',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    protected function tearDown(): void
    {
        TenantContext::set(null);
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_overstay_lists_one_rent_line_per_occupied_day(): void
    {
        $booking = $this->makeOverstayBooking();

        $response = $this->actingAs($this->user)->get(route('bookings.show', $booking));

        $response->assertOk();
        $response->assertSee('Daily Rent');
        $response->assertSee('23 Sep 2026');
        $response->assertSee('28 Sep 2026');

        $booking->refresh();

        $this->assertGreaterThanOrEqual(6, $booking->billedNights());
        $this->assertCount($booking->billedNights(), $booking->rentNightDates());
    }

    public function test_paying_one_day_does_not_pay_other_days(): void
    {
        $booking = $this->makeOverstayBooking();
        $booking->syncAccruedStayCharges();

        $this->actingAs($this->user)
            ->from(route('bookings.show', $booking))
            ->post(route('bookings.payments.store', $booking), [
                'type' => 'room',
                'method' => 'cash',
                'rent_date' => '2026-09-23',
            ])
            ->assertRedirect();

        $booking->refresh()->load('payments');

        $payment = $booking->payments->first();

        $this->assertNotNull($payment);
        $this->assertSame('room', $payment->type);
        $this->assertSame('cash', $payment->method);
        $this->assertSame('2026-09-23', $payment->rent_date?->toDateString());
        $this->assertSame(600.0, (float) $payment->amount);

        $lines = collect($booking->payableLines())->where('type', 'room')->values();

        $this->assertSame(0.0, (float) $lines->firstWhere('rent_date', '2026-09-23')['remaining']);
        $this->assertSame(600.0, (float) $lines->firstWhere('rent_date', '2026-09-24')['remaining']);
        $this->assertSame(600.0, (float) $lines->firstWhere('rent_date', '2026-09-28')['remaining']);
    }

    public function test_pos_item_stays_a_separate_payable_line(): void
    {
        $booking = $this->makeOverstayBooking(withPosItem: true);
        $booking->syncAccruedStayCharges();

        $item = $booking->items()->firstOrFail();

        $this->actingAs($this->user)
            ->from(route('bookings.show', $booking))
            ->post(route('bookings.payments.store', $booking), [
                'type' => 'extras',
                'method' => 'online',
                'booking_item_id' => $item->id,
            ])
            ->assertRedirect();

        $booking->refresh()->load(['items', 'payments']);
        $lines = collect($booking->payableLines());
        $posLine = $lines->firstWhere('booking_item_id', $item->id);
        $roomLines = $lines->where('type', 'room');

        $this->assertNotNull($posLine);
        $this->assertSame('Club sandwich', $posLine['label']);
        $this->assertSame(0.0, (float) $posLine['remaining']);
        $this->assertSame(450.0, (float) $posLine['paid']);
        $this->assertTrue($roomLines->every(fn (array $line) => (float) $line['remaining'] === 600.0));

        $this->assertDatabaseHas('booking_payments', [
            'booking_id' => $booking->id,
            'type' => 'extras',
            'booking_item_id' => $item->id,
            'method' => 'online',
            'amount' => 450,
        ]);
    }

    public function test_checked_out_booking_can_be_edited(): void
    {
        $booking = $this->makeOverstayBooking();
        $booking->update(['status' => 'checked_out']);

        $this->actingAs($this->user)
            ->get(route('bookings.edit', $booking))
            ->assertOk();

        $this->actingAs($this->user)
            ->put(route('bookings.update', $booking), [
                'room_id' => $booking->room_id,
                'customer_id' => $booking->customer_id,
                'guest_name' => 'Updated Guest',
                'father_name' => '',
                'phone' => '03001234567',
                'cnic' => '',
                'address' => '',
                'vehicle_number' => '',
                'males' => 1,
                'females' => 0,
                'children' => 0,
                'check_in' => '2026-09-23',
                'check_out' => '2026-09-25',
                'price_per_night' => 600,
                'notes' => '',
                'companions' => [],
            ])
            ->assertRedirect(route('bookings.show', $booking));

        $this->assertSame('Updated Guest', $booking->fresh()->customer->name);
        $this->assertSame('checked_out', $booking->fresh()->status);
    }

    public function test_checked_out_booking_can_be_cancelled(): void
    {
        $booking = $this->makeOverstayBooking();
        $booking->update(['status' => 'checked_out']);

        $this->actingAs($this->user)
            ->post(route('bookings.cancel', $booking))
            ->assertRedirect(route('bookings.index'));

        $this->assertSame('cancelled', $booking->fresh()->status);
    }

    private function makeOverstayBooking(bool $withPosItem = false): Booking
    {
        $room = Room::create([
            'tenant_id' => $this->tenant->id,
            'number' => '101',
            'type' => 'deluxe',
            'bed_type' => 'double',
            'floor' => 1,
            'max_capacity' => 3,
            'status' => 'occupied',
        ]);

        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Guest',
            'phone' => '03001234567',
        ]);

        $booking = Booking::create([
            'tenant_id' => $this->tenant->id,
            'room_id' => $room->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'check_in' => '2026-09-23',
            'check_out' => '2026-09-25',
            'nights' => 2,
            'price_per_night' => 600,
            'room_amount' => 1200,
            'extras_amount' => 0,
            'grand_total' => 1200,
            'status' => 'checked_in',
            'checked_in_at' => '2026-09-23 14:00:00',
            'males' => 1,
            'payment_status' => 'unpaid',
            'balance_due' => 1200,
        ]);

        if ($withPosItem) {
            BookingItem::create([
                'tenant_id' => $this->tenant->id,
                'booking_id' => $booking->id,
                'name' => 'Club sandwich',
                'qty' => 1,
                'unit_price' => 450,
                'total' => 450,
            ]);

            $booking->recalculateTotals();
        }

        return $booking->fresh(['items']);
    }
}
