<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RoomsOverviewTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-05 12:00:00'));

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

    public function test_room_overview_shows_live_occupancy_and_status_totals(): void
    {
        $this->createRoom('101');
        $occupiedRoom = $this->createRoom('102');
        $reservedRoom = $this->createRoom('103');
        $this->createRoom('201', 'maintenance');

        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Guest',
            'phone' => '03001234567',
        ]);

        $this->createBooking($occupiedRoom, $customer, 'checked_in');
        $this->createBooking($reservedRoom, $customer, 'reserved');

        $this->assertSame('2026-10-05', now()->toDateString());
        $this->assertSame('occupied', $occupiedRoom->fresh()->occupancyStatus());
        $this->assertSame('reserved', $reservedRoom->fresh()->occupancyStatus());
        $this->assertSame('reserved', $reservedRoom->currentStay()?->status);

        $response = $this->actingAs($this->user)->get(route('rooms.index'));

        $response->assertOk()
            ->assertViewHas('roomStatusCounts', function ($counts): bool {
                $this->assertSame([
                    'available' => 1,
                    'occupied' => 1,
                    'reserved' => 1,
                    'maintenance' => 1,
                ], $counts->all());

                return true;
            })
            ->assertSee('Your rooms, at a glance')
            ->assertSee('#101')
            ->assertSee('#102')
            ->assertSee('#103')
            ->assertSee('#201')
            ->assertSee('images/rooms/room-1.jpg')
            ->assertSee('images/rooms/room-2.jpg')
            ->assertSee('View details')
            ->assertSee('Occupied')
            ->assertSee('Reserved')
            ->assertSee('Maintenance');
    }

    private function createRoom(string $number, string $status = 'available'): Room
    {
        return Room::create([
            'tenant_id' => $this->tenant->id,
            'number' => $number,
            'type' => 'standard',
            'bed_type' => 'single',
            'floor' => '1',
            'max_capacity' => 2,
            'status' => $status,
        ]);
    }

    private function createBooking(Room $room, Customer $customer, string $status): void
    {
        Booking::create([
            'tenant_id' => $this->tenant->id,
            'room_id' => $room->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'check_in' => now()->toDateString(),
            'check_out' => now()->copy()->addDay()->toDateString(),
            'nights' => 1,
            'price_per_night' => 600,
            'room_amount' => 600,
            'extras_amount' => 0,
            'grand_total' => 600,
            'status' => $status,
            'checked_in_at' => $status === 'checked_in' ? now() : null,
            'males' => 1,
            'payment_status' => 'unpaid',
            'balance_due' => 600,
        ]);
    }
}
