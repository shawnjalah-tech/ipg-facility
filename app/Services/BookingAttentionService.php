<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingRescheduleRequest;
use App\Models\User;
use App\Notifications\AdminAttentionEmail;
use App\Notifications\BookingAttention;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/** Nonessential notifications never turn a committed booking into an HTTP 500. */
class BookingAttentionService
{
    private function safely(callable $callback): void
    {
        // Database notifications participate in the booking transaction. If the
        // booking rolls back, its new notices roll back with it. This also works
        // with RefreshDatabase tests, which use an outer transaction.
        try {
            $callback();
        } catch (Throwable $exception) {
            Log::warning('Booking attention delivery failed', ['error' => $exception->getMessage()]);
        }
    }

    public function bookingRequested(Booking $booking): void
    {
        $this->safely(function () use ($booking): void {
            if ($booking->fresh()?->status !== 'pending') {
                return;
            }
            $this->admins('booking_requested', 'New booking #'.$booking->id,
                'A staff booking needs an administrator decision.', route('admin.bookings.manage', ['tab' => 'pending']), $booking->id);
        });
    }

    public function rescheduleRequested(BookingRescheduleRequest $request): void
    {
        $this->safely(function () use ($request): void {
            if ($request->fresh()?->status !== 'pending') {
                return;
            }
            $this->admins('reschedule_requested', 'New reschedule request #'.$request->id,
                'Booking #'.$request->booking_id.' has a proposed new schedule.', route('admin.reschedules.show', $request), $request->booking_id);
        });
    }

    public function staffStatusChanged(Booking $booking): void
    {
        $this->safely(function () use ($booking): void {
            $owner = $booking->user;
            if (! $owner) {
                return;
            }
            $owner->notify(new BookingAttention('booking_status', 'Booking #'.$booking->id.' updated',
                'Your booking status is now '.ucfirst($booking->status).'.', route('bookings.show', $booking), $booking->id));
        });
    }

    public function rescheduleReviewed(BookingRescheduleRequest $request): void
    {
        $this->safely(function () use ($request): void {
            $owner = $request->requester;
            if (! $owner) {
                return;
            }
            $owner->notify(new BookingAttention('reschedule_reviewed', 'Reschedule #'.$request->id.' '.$request->status,
                'The proposed schedule for booking #'.$request->booking_id.' was '.$request->status.'.',
                route('bookings.show', $request->booking_id), $request->booking_id));
        });
    }

    /** Called by the scheduler inside a transaction; must throw on failed persistence. */
    public function overdueReminder(Booking $booking, string $threshold): void
    {
        $admins = User::where('role', 'admin')->get();
        if ($admins->isEmpty()) {
            return;
        }
        $title = 'Booking #'.$booking->id.' needs review';
        $message = 'This pending booking starts in under '.$threshold.'. Please approve or reject it before it expires.';
        Notification::send($admins, new BookingAttention('booking_deadline', $title, $message,
            route('admin.bookings.manage', ['tab' => 'pending']), $booking->id));

        if (config('attention.admin_email_enabled', false)) {
            DB::afterCommit(function () use ($admins, $title, $message): void {
                $this->safelyMail($admins, $title, $message, route('admin.bookings.manage', ['tab' => 'pending']));
            });
        }
    }

    private function admins(string $kind, string $title, string $message, string $url, int $bookingId): void
    {
        $admins = User::where('role', 'admin')->get();
        if ($admins->isEmpty()) {
            return;
        }
        Notification::send($admins, new BookingAttention($kind, $title, $message, $url, $bookingId));
        $this->safelyMail($admins, $title, $message, $url);
    }

    private function safelyMail($admins, string $title, string $message, string $url): void
    {
        if (! config('attention.admin_email_enabled', false)) {
            return;
        }
        try {
            Notification::send($admins, (new AdminAttentionEmail($title, $message, $url))->afterCommit());
        } catch (Throwable $exception) {
            Log::warning('Admin attention email dispatch failed', ['error' => $exception->getMessage()]);
        }
    }
}
