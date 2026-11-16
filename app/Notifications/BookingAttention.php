<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/** Immediate, persistent in-app notice (does not require a queue worker). */
class BookingAttention extends Notification
{
    public function __construct(
        public string $kind,
        public string $title,
        public string $message,
        public string $url,
        public int $bookingId,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
            'booking_id' => $this->bookingId,
        ];
    }
}
