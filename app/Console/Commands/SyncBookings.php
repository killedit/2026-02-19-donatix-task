<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Jobs\SyncBookingJob;

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

            SyncBookingJob::dispatch($id);

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Full sync completed.');

        return Command::SUCCESS;
    }
}
