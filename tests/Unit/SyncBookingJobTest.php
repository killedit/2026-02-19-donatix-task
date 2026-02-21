<?php

namespace Tests\Unit;

use App\Jobs\SyncBookingJob;
use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncBookingJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_skips_existing_booking(): void
    {
        $roomType = RoomType::create(['external_id' => 10, 'name' => 'Test', 'description' => 'Test']);
        $room = Room::create(['external_id' => 100, 'number' => '101', 'floor' => 1, 'room_type_id' => $roomType->id]);

        Booking::create([
            'external_id' => 1,
            'arrival_date' => '2025-01-01',
            'departure_date' => '2025-01-05',
            'room_id' => $room->id,
            'room_type_id' => $roomType->id,
            'status' => 'confirmed',
        ]);

        $job = new SyncBookingJob(1);
        $job->handle();

        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_creates_booking_with_relations(): void
    {
        Http::fake([
            config('services.donatix.url').'/api/bookings/1' => Http::response([
                'id' => 1,
                'room_type_id' => 10,
                'room_id' => 100,
                'arrival_date' => '2025-01-01',
                'departure_date' => '2025-01-05',
                'status' => 'confirmed',
                'notes' => 'Test booking',
                'guest_ids' => [],
            ]),
            config('services.donatix.url').'/api/room-types/10' => Http::response([
                'id' => 10,
                'name' => 'Deluxe',
                'description' => 'Deluxe room',
            ]),
            config('services.donatix.url').'/api/rooms/100' => Http::response([
                'id' => 100,
                'number' => '101',
                'floor' => 1,
            ]),
        ]);

        $job = new SyncBookingJob(1);
        $job->handle();

        $this->assertDatabaseHas('bookings', ['external_id' => 1]);
        $this->assertDatabaseHas('rooms', ['external_id' => 100]);
        $this->assertDatabaseHas('room_types', ['external_id' => 10]);
    }

    public function test_creates_guests_for_booking(): void
    {
        Http::fake([
            config('services.donatix.url').'/api/bookings/1' => Http::response([
                'id' => 1,
                'room_type_id' => 10,
                'room_id' => 100,
                'arrival_date' => '2025-01-01',
                'departure_date' => '2025-01-05',
                'status' => 'confirmed',
                'notes' => '',
                'guest_ids' => [50],
            ]),
            config('services.donatix.url').'/api/room-types/10' => Http::response([
                'id' => 10,
                'name' => 'Deluxe',
                'description' => 'Deluxe room',
            ]),
            config('services.donatix.url').'/api/rooms/100' => Http::response([
                'id' => 100,
                'number' => '101',
                'floor' => 1,
            ]),
            config('services.donatix.url').'/api/guests/50' => Http::response([
                'id' => 50,
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john@example.com',
            ]),
        ]);

        $job = new SyncBookingJob(1);
        $job->handle();

        $this->assertDatabaseHas('guests', ['external_id' => 50]);
    }
}
