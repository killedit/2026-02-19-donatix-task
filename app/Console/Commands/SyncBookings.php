<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Guest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SyncBookings extends Command
{
    protected $signature = 'app:sync-bookings';
    protected $description = 'Sync bookings synchronously with progress bar';

    public function handle()
    {
        $this->info('Fetching booking list...');

        $response = Http::timeout(20)->get(config('services.donatix.url') . '/api/bookings');

        if (!$response->successful()) {
            $this->error('Failed to fetch bookings.');
            return Command::FAILURE;
        }

        $bookingIds = collect($response->json('data'))->flatten()->filter();
        $total = $bookingIds->count();

        $this->info("Total bookings: {$total}");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($bookingIds as $id) {

            $booking = Booking::where('external_id', $id)->first();

            if (!$booking) {

                usleep(600000); // 0.6s delay to avoid `x-ratelimit`

                $bookingResponse = Http::timeout(10)->get(config('services.donatix.url') . "/api/bookings/{$id}");
                if (!$bookingResponse->successful()) {
                    Log::warning("Failed to fetch booking {$id}.");
                }
                $data = $bookingResponse->json();

                $roomType = $this->ensureRoomTypeExists($data['room_type_id']);
                if (!$roomType) {
                    Log::warning("Skipping Booking: {$data['id']}. Missing room type {$data['room_type_id']}.");
                }

                $room = $this->ensureRoomExists($data['room_id'], $roomType);
                if (!$room) {
                    Log::warning("Skipping Booking {$data['id']}. Missing room {$data['room_id']}.");
                }

                $booking = Booking::updateOrCreate(
                    ['external_id'    => $data['id']],
                    [
                        'arrival_date'   => $data['arrival_date'],
                        'departure_date' => $data['departure_date'],
                        'room_id'        => $room->id,
                        'room_type_id'   => $roomType->id,
                        'status'         => $data['status'],
                        'notes'          => $data['notes'],
                    ]
                );

                Log::info("Created new booking {$booking->external_id}.");


                if ($booking && !empty($data['guest_ids'])) {

                    $validGuestIds = [];
                    foreach ($data['guest_ids'] as $externalGuestId) {
                        $guest = $this->ensureGuestExists($externalGuestId);

                        if ($guest && $guest->id) {
                            $validGuestIds[] = $guest->id;
                        }
                    }

                    if (!empty($validGuestIds)) {
                        $booking->guests()->sync($validGuestIds);
                    }

                    if (!$booking->id) {
                        dd(
                            'Booking ID is null',
                            $booking
                        );
                    }
                }
            } else {
                Log::info("Booking {$booking->external_id} already exists. Skipping");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Full sync completed.');

        return Command::SUCCESS;
    }

    private function ensureRoomTypeExists($apiId): ?RoomType
    {
        $roomType = RoomType::where('external_id', $apiId)->first();
        if ($roomType) {
            Log::info("Reading RoomType with id: {$apiId} from the db.");
            return $roomType;
        }

        $response = Http::get(config('services.donatix.url') . "/api/room-types/{$apiId}");

        if (!$response->successful()) {
            return null;
        }

        $data = $response->json();

        return RoomType::updateOrCreate(
            ['external_id' => $apiId],
            [
                'name' => $data['name'],
                'description' => $data['description']
            ]
        );
    }

    private function ensureRoomExists($apiId, RoomType $roomType): ?Room
    {
        $room = Room::where('external_id', $apiId)->first();
        if ($room) {
            Log::info("Reading Room with id: {$apiId} from the db.");
            return $room;
        }

        $response = Http::get(config('services.donatix.url') . "/api/rooms/{$apiId}");

        if (!$response->successful()) {
            return null;
        }
        $data = $response->json();

        return Room::updateOrCreate(
            ['external_id' => $apiId],
            [
                'number'       => $data['number'],
                'floor'        => $data['floor'] ?? 0,
                'room_type_id' => $roomType->id,
            ]
        );
    }

    private function ensureGuestExists($apiId): ?Guest
    {
        $guest = Guest::where('external_id', $apiId)->first();
        if ($guest) {
            Log::info("Reading Guest with id: {$apiId} from the db.");
            return $guest;
        }

        $response = Http::get(config('services.donatix.url') . "/api/guests/{$apiId}");

        if (!$response->successful()) {
            return null;
        }

        $data = $response->json();

        return Guest::updateOrCreate(
            ['external_id' => $apiId,],
            [
                'first_name'  => $data['first_name'] ?? $data['name'] ?? 'Guest',
                'last_name'   => $data['last_name'] ?? '',
                'email'       => $data['email'] ?? null,
            ]
        );
    }
}
