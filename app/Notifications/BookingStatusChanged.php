<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $bookingId;

    public string $status;

    public ?string $remarks;

    public function __construct(Booking $booking)
    {
        $this->bookingId = $booking->id;
        $this->status = $booking->status;
        $this->remarks = $booking->admin_remarks;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Booking #'.$this->bookingId.' '.$this->status)
            ->line('Your booking #'.$this->bookingId.' is now '.$this->status.'.')
            ->line($this->remarks ?: 'View your booking for details.')
            ->action('View booking', route('bookings.show', $this->bookingId));
    }
}
