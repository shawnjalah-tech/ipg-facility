<?php

namespace App\Notifications;

use App\Models\BookingRescheduleRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingRescheduleReviewed extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $bookingId;
    public string $decision;
    public string $dateRange;
    public string $timeRange;
    public ?string $reason;

    public function __construct(BookingRescheduleRequest $request)
    {
        $this->bookingId = $request->booking_id;
        $this->decision = $request->status;
        $this->dateRange = $request->date_range;
        $this->timeRange = $request->time_range;
        $this->reason = $request->review_notes;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Rescheduling request for booking #'.$this->bookingId.' '.$this->decision)
            ->line('Your request to reschedule booking #'.$this->bookingId.' was '.$this->decision.'.')
            ->line('Requested new time: '.$this->dateRange.' ('.$this->timeRange.').')
            ->line($this->reason ?: 'Review your booking for more details.')
            ->action('View booking', route('bookings.show', $this->bookingId));
    }
}
