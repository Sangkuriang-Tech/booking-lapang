<?php

namespace App\Console\Commands;

use App\Actions\Booking\ExpireStaleBookings;
use Illuminate\Console\Command;

class ExpireBookingsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'bookings:expire';

    /**
     * @var string
     */
    protected $description = 'Expire stale held and awaiting_reupload bookings and release their slots.';

    public function handle(ExpireStaleBookings $expireStaleBookings): int
    {
        $result = $expireStaleBookings->execute();

        $this->info("Expired: {$result['expired']}, Rejected: {$result['rejected']}");

        return self::SUCCESS;
    }
}
