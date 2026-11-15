<?php

namespace App\Console\Commands;

use App\Actions\Booking\TransitionEndedBookings;
use Illuminate\Console\Command;

class TransitionEndedBookingsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'bookings:transition-ended';

    /**
     * @var string
     */
    protected $description = 'Transition ended checked_in bookings to completed and confirmed bookings to no_show';

    public function handle(TransitionEndedBookings $transitionEndedBookings): int
    {
        $result = $transitionEndedBookings->execute();

        $this->info("Completed: {$result['completed']}, No-show: {$result['no_show']}");

        return self::SUCCESS;
    }
}
