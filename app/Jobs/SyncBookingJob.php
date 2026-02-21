<?php

namespace App\Jobs;

use App\Models\Booking;
use App\Models\Guest;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncBookingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $bookingId;

    public $timeout = 120;

    public $tries = 3;

    public function __construct(int $bookingId)
    {
        $this->bookingId = $bookingId;
    }

    public function handle(): void
    {
        Log::info("Processing booking {$this->bookingId}");

        $existingBooking = Booking::where('external_id', $this->bookingId)->first();
        if ($existingBooking) {
            Log::info("Booking {$this->bookingId} already exists. Skipping.");

            return;
        }

        $response = Http::timeout(10)->get(
            config('services.donatix.url')."/api/bookings/{$this->bookingId}"
        );

        if (! $response->successful()) {
            Log::warning("Failed to fetch booking {$this->bookingId}");

            return;
        }

        $data = $response->json();

        $roomType = $this->ensureRoomTypeExists($data['room_type_id']);
        if (! $roomType) {
            Log::warning("Skipping booking {$this->bookingId} — missing room type.");

            return;
        }

        $room = $this->ensureRoomExists($data['room_id'], $roomType);
        if (! $room) {
            Log::warning("Skipping booking {$this->bookingId} — missing room.");

            return;
        }

        DB::transaction(function () use ($data, $room, $roomType) {
            $booking = Booking::create([
                'external_id' => $data['id'],
                'arrival_date' => $data['arrival_date'],
                'departure_date' => $data['departure_date'],
                'room_id' => $room->id,
                'room_type_id' => $roomType->id,
                'status' => $data['status'],
                'notes' => $data['notes'],
            ]);

            Log::info("Created booking {$booking->external_id}");

            if (! empty($data['guest_ids'])) {
                $validGuestIds = [];

                foreach ($data['guest_ids'] as $externalGuestId) {
                    $guest = $this->ensureGuestExists($externalGuestId);

                    if ($guest && $guest->id) {
                        $validGuestIds[] = $guest->id;
                    }
                }

                if (! empty($validGuestIds)) {
                    $booking->guests()->sync($validGuestIds);
                    Log::info("Synced guests for booking {$booking->external_id}");
                }
            }
        });

        Log::info("Finished booking {$this->bookingId}");
    }

    /**
     * DB first, API only if missing
     */
    private function ensureRoomTypeExists($apiId): ?RoomType
    {
        $roomType = RoomType::where('external_id', $apiId)->first();
        if ($roomType) {
            Log::info("RoomType {$apiId} loaded from DB.");

            return $roomType;
        }

        $response = Http::get(config('services.donatix.url')."/api/room-types/{$apiId}");

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        return RoomType::create([
            'external_id' => $apiId,
            'name' => $data['name'],
            'description' => $data['description'],
        ]);
    }

    private function ensureRoomExists($apiId, RoomType $roomType): ?Room
    {
        $room = Room::where('external_id', $apiId)->first();
        if ($room) {
            Log::info("Room {$apiId} loaded from DB.");

            return $room;
        }

        $response = Http::get(config('services.donatix.url')."/api/rooms/{$apiId}");

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        return Room::create([
            'external_id' => $apiId,
            'number' => $data['number'],
            'floor' => $data['floor'] ?? 0,
            'room_type_id' => $roomType->id,
        ]);
    }

    private function ensureGuestExists($apiId): ?Guest
    {
        $guest = Guest::where('external_id', $apiId)->first();
        if ($guest) {
            Log::info("Guest {$apiId} loaded from DB.");

            return $guest;
        }

        $response = Http::get(config('services.donatix.url')."/api/guests/{$apiId}");

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        return Guest::create([
            'external_id' => $apiId,
            'first_name' => $data['first_name'] ?? $data['name'] ?? 'Guest',
            'last_name' => $data['last_name'] ?? '',
            'email' => $data['email'] ?? null,
        ]);
    }
}
