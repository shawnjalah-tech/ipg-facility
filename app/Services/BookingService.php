<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingRescheduleRequest;
use App\Models\Facility;
use App\Models\FacilityClosure;
use App\Models\User;
use App\Notifications\BookingRescheduleReviewed;
use App\Notifications\BookingStatusChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingService
{
    /** Acquire this lock first, inside the transaction, before reading reservations. */
    public function lockFacility(int $id): Facility
    {
        // Acquire a write lock before checking availability, including on SQLite.
        DB::table('facilities')->where('id', $id)->update(['id' => DB::raw('id')]);

        return Facility::whereKey($id)->lockForUpdate()->firstOrFail();
    }

    public function create(User $user, array $data): Booking
    {
        return DB::transaction(function () use ($user, $data) {
            $facility = $this->lockFacility((int) $data['facility_id']);
            if (! $facility->is_active) {
                throw ValidationException::withMessages(['facility_id' => 'This facility is unavailable for new bookings.']);
            }
            $candidate = new Booking($data);
            $this->validateProposedDates($candidate);
            $this->ensureAvailable($candidate);
            $candidate->user_id = $user->id;
            $candidate->status = 'pending';
            $candidate->save();
            $this->record($candidate, $user, null, 'Request submitted.');
            app(BookingAttentionService::class)->bookingRequested($candidate);

            return $candidate;
        }, 5);
    }

    /**
     * Confirm a new booking on behalf of a registered staff member.
     * Ownership belongs to staff; the audit actor is the administrator.
     */
    public function createForStaff(User $admin, User $staff, array $data): Booking
    {
        abort_unless($admin->isAdmin(), 403);
        if ($staff->isAdmin()) {
            throw ValidationException::withMessages(['user_id' => 'Bookings can only be assigned to staff accounts.']);
        }

        return DB::transaction(function () use ($admin, $staff, $data) {
            $facility = $this->lockFacility((int) $data['facility_id']);
            if (! $facility->is_active) {
                throw ValidationException::withMessages(['facility_id' => 'This facility is unavailable for new bookings.']);
            }

            $candidate = new Booking($data);
            $this->validateProposedDates($candidate);
            $this->ensureAvailable($candidate);
            $candidate->user_id = $staff->id;
            $candidate->status = 'approved';
            $candidate->save();

            $this->record($candidate, $admin, null, 'Created and approved by administrator on behalf of '.$staff->name.'.');
            $staff->notify((new BookingStatusChanged($candidate))->afterCommit());
            app(BookingAttentionService::class)->staffStatusChanged($candidate);

            return $candidate;
        }, 5);
    }

    public function transition(Booking $original, User $actor, string $status, ?string $remarks = null): Booking
    {
        return DB::transaction(function () use ($original, $actor, $status, $remarks) {
            $facility = $this->lockFacility($original->facility_id);
            $booking = Booking::whereKey($original->id)->lockForUpdate()->firstOrFail();
            abort_unless($actor->isAdmin() || ($status === 'cancelled' && $booking->user_id === $actor->id), 403);
            $allowed = $status === 'cancelled' ? ['pending', 'approved'] : ['pending'];
            if (! in_array($booking->status, $allowed, true)) {
                throw ValidationException::withMessages(['status' => 'This booking has already been processed. Refresh the page.']);
            }
            // A normal cancellation releases a future reservation only. Post-start
            // changes must use the audited Admin > Booking History status editor.
            if ($status === 'cancelled' && $booking->starts_at->lte(now())) {
                throw ValidationException::withMessages(['status' => 'This booking has already started. For a historical correction, an administrator must use Edit Status and provide a reason.']);
            }
            if ($status === 'approved') {
                if (! $facility->is_active || $booking->starts_at->lte(now())) {
                    throw ValidationException::withMessages(['status' => 'Cannot approve a booking that has started or uses an inactive facility.']);
                }
                $this->ensureAvailable($booking, ['approved']);
            }
            $old = $booking->status;
            $booking->update(['status' => $status, 'admin_remarks' => $actor->isAdmin() ? $remarks : $booking->admin_remarks]);
            $this->record($booking, $actor, $old, $remarks);
            $this->closePendingReschedules($booking);
            $booking->user->notify((new BookingStatusChanged($booking))->afterCommit());
            app(BookingAttentionService::class)->staffStatusChanged($booking);

            return $booking;
        }, 5);
    }

    /**
     * Admin-only correction from Booking History. The normal action buttons retain
     * their original pending-only lifecycle restrictions.
     */
    public function reviseStatus(Booking $original, User $actor, string $status, string $reason, string $expectedStatus): Booking
    {
        abort_unless($actor->isAdmin(), 403);

        if (! in_array($status, ['pending', 'approved', 'rejected', 'cancelled', 'expired'], true)) {
            throw ValidationException::withMessages(['status' => 'Select a valid booking status.']);
        }

        return DB::transaction(function () use ($original, $actor, $status, $reason, $expectedStatus) {
            $facility = $this->lockFacility($original->facility_id);
            $booking = Booking::whereKey($original->id)->lockForUpdate()->firstOrFail();

            if ($booking->status !== $expectedStatus) {
                throw ValidationException::withMessages(['status' => 'The booking was updated by someone else. Reload before editing.']);
            }
            if ($booking->status === $status) {
                throw ValidationException::withMessages(['status' => 'Choose a different status to record a correction.']);
            }
            if (in_array($status, ['pending', 'approved'], true)) {
                if ($booking->starts_at->lte(now())) {
                    throw ValidationException::withMessages(['status' => 'Cannot restore a booking to pending or approved after its start time.']);
                }
                if (! $facility->is_active) {
                    throw ValidationException::withMessages(['status' => 'This facility is inactive.']);
                }
                // Reinstating a released slot may conflict with a newer request.
                $this->ensureAvailable($booking);
            }
            if ($status === 'expired' && $booking->starts_at->gt(now())) {
                throw ValidationException::withMessages(['status' => 'A future booking cannot be marked expired.']);
            }

            $old = $booking->status;
            $booking->update(['status' => $status, 'admin_remarks' => $reason]);
            $this->record($booking, $actor, $old, 'Administrative status correction: '.$reason);
            $this->closePendingReschedules($booking);
            $booking->user?->notify((new BookingStatusChanged($booking))->afterCommit());
            app(BookingAttentionService::class)->staffStatusChanged($booking);

            return $booking;
        }, 5);
    }

    /**
     * Mark unanswered requests expired at their start date + time. Idempotent:
     * only requests still pending under lock are changed or logged.
     */
    public function expireOverduePending(): int
    {
        $expired = 0;
        Booking::where('status', 'pending')
            ->whereDate('start_date', '<=', today()->toDateString())
            ->chunkById(100, function ($candidates) use (&$expired) {
                foreach ($candidates as $original) {
                    $changed = DB::transaction(function () use ($original) {
                        $this->lockFacility($original->facility_id);
                        $booking = Booking::whereKey($original->id)->lockForUpdate()->firstOrFail();
                        if ($booking->status !== 'pending' || $booking->starts_at->gt(now())) {
                            return false;
                        }
                        $notes = 'Automatically expired: booking start time passed without an admin decision.';
                        $booking->update(['status' => 'expired', 'admin_remarks' => $notes]);
                        $this->closePendingReschedules($booking);
                        $booking->events()->create([
                            'user_id' => null,
                            'actor_name' => 'System',
                            'old_status' => 'pending',
                            'new_status' => 'expired',
                            'notes' => $notes,
                        ]);
                        $booking->user?->notify((new BookingStatusChanged($booking))->afterCommit());
                        app(BookingAttentionService::class)->staffStatusChanged($booking);

                        return true;
                    }, 5);
                    if ($changed) {
                        $expired++;
                    }
                }
            });

        return $expired;
    }

    /**
     * Submit a proposed change. The original booking dates/status remain untouched
     * (and its approved slot remains reserved) until admin approval.
     */
    public function requestReschedule(Booking $original, User $actor, array $data): BookingRescheduleRequest
    {
        return DB::transaction(function () use ($original, $actor, $data) {
            $facility = $this->lockFacility($original->facility_id);
            $booking = Booking::whereKey($original->id)->lockForUpdate()->firstOrFail();
            abort_unless($booking->user_id === $actor->id && ! $actor->isAdmin(), 403);

            if (! in_array($booking->status, ['pending', 'approved'], true) || $booking->starts_at->lte(now())) {
                throw ValidationException::withMessages(['booking' => 'Only upcoming pending or approved bookings can be rescheduled.']);
            }
            if (! $facility->is_active) {
                throw ValidationException::withMessages(['booking' => 'This facility is currently unavailable.']);
            }
            if ($booking->rescheduleRequests()->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['booking' => 'A rescheduling request is already awaiting admin review.']);
            }

            $candidate = new Booking(array_merge($data, ['facility_id' => $booking->facility_id]));
            $this->validateProposedDates($candidate);

            if ($booking->start_date->isSameDay($candidate->start_date)
                && $booking->end_date->isSameDay($candidate->end_date)
                && substr((string) $booking->start_time, 0, 5) === substr((string) $candidate->start_time, 0, 5)
                && substr((string) $booking->end_time, 0, 5) === substr((string) $candidate->end_time, 0, 5)) {
                throw ValidationException::withMessages(['start_date' => 'Choose a different date or time from your existing booking.']);
            }

            $this->ensureAvailable($candidate, ['pending', 'approved'], $booking->id);

            $proposal = $booking->rescheduleRequests()->create([
                'user_id' => $actor->id,
                'start_date' => $candidate->start_date->toDateString(),
                'end_date' => $candidate->end_date->toDateString(),
                'start_time' => $candidate->start_time,
                'end_time' => $candidate->end_time,
                'status' => 'pending',
            ]);
            $booking->events()->create([
                'user_id' => $actor->id,
                'actor_name' => $actor->name,
                'old_status' => $booking->status,
                'new_status' => $booking->status,
                'notes' => 'Reschedule requested (#'.$proposal->id.'): '.$this->describePeriod($candidate).'. Original booking remains unchanged until approved.',
            ]);

            app(BookingAttentionService::class)->rescheduleRequested($proposal);

            return $proposal;
        }, 5);
    }

    /** Admin decision: recheck the slot under the facility lock before updating. */
    public function decideReschedule(BookingRescheduleRequest $original, User $admin, bool $approve, ?string $reason = null): BookingRescheduleRequest
    {
        abort_unless($admin->isAdmin(), 403);
        // An ordinary SELECT inside the transaction could establish a MySQL
        // snapshot before the facility lock, missing a concurrent reservation.
        $facilityId = $original->booking()->value('facility_id');
        abort_unless($facilityId, 404);

        return DB::transaction(function () use ($original, $admin, $approve, $reason, $facilityId) {
            $facility = $this->lockFacility((int) $facilityId);
            $booking = Booking::whereKey($original->booking_id)->lockForUpdate()->firstOrFail();
            $proposal = BookingRescheduleRequest::whereKey($original->id)->lockForUpdate()->firstOrFail();
            if ($proposal->booking_id !== $booking->id || $proposal->status !== 'pending') {
                throw ValidationException::withMessages(['decision' => 'This rescheduling request has already been processed. Refresh the page.']);
            }

            $oldStatus = $booking->status;
            if ($approve) {
                if (! in_array($booking->status, ['pending', 'approved'], true) || $booking->starts_at->lte(now())) {
                    throw ValidationException::withMessages(['decision' => 'The original booking is no longer eligible for rescheduling. Reject this request instead.']);
                }
                if (! $facility->is_active) {
                    throw ValidationException::withMessages(['decision' => 'The facility is inactive.']);
                }
                $candidate = new Booking([
                    'facility_id' => $booking->facility_id,
                    'start_date' => $proposal->start_date->toDateString(),
                    'end_date' => $proposal->end_date->toDateString(),
                    'start_time' => $proposal->start_time,
                    'end_time' => $proposal->end_time,
                ]);
                $this->validateProposedDates($candidate);
                $this->ensureAvailable($candidate, ['pending', 'approved'], $booking->id);
                $before = $this->describePeriod($booking);
                $after = $this->describePeriod($candidate);
                // Admin approval of the amended request confirms the booking.
                $booking->update([
                    'start_date' => $proposal->start_date->toDateString(),
                    'end_date' => $proposal->end_date->toDateString(),
                    'start_time' => $proposal->start_time,
                    'end_time' => $proposal->end_time,
                    'status' => 'approved',
                ]);
                $notes = 'Reschedule approved (#'.$proposal->id.'): '.$before.' -> '.$after.'.'.($reason ? ' Admin note: '.$reason : '');
                $decision = 'approved';
            } else {
                $notes = 'Reschedule rejected (#'.$proposal->id.'): '.$this->describeRequestPeriod($proposal).'. Reason: '.$reason;
                $decision = 'rejected';
            }

            $proposal->update([
                'status' => $decision,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'review_notes' => $reason,
            ]);
            $booking->events()->create([
                'user_id' => $admin->id,
                'actor_name' => $admin->name,
                'old_status' => $oldStatus,
                'new_status' => $booking->status,
                'notes' => $notes,
            ]);
            $booking->user?->notify((new BookingRescheduleReviewed($proposal))->afterCommit());
            app(BookingAttentionService::class)->rescheduleReviewed($proposal);

            return $proposal;
        }, 5);
    }

    public function withdrawReschedule(BookingRescheduleRequest $original, User $actor): void
    {
        DB::transaction(function () use ($original, $actor) {
            $facilityId = $original->booking()->value('facility_id');
            abort_unless($facilityId, 404);
            $this->lockFacility((int) $facilityId);
            $proposal = BookingRescheduleRequest::whereKey($original->id)->lockForUpdate()->firstOrFail();
            abort_unless($proposal->user_id === $actor->id && ! $actor->isAdmin(), 403);
            if ($proposal->status !== 'pending') {
                throw ValidationException::withMessages(['booking' => 'This rescheduling request has already been reviewed.']);
            }
            $proposal->update(['status' => 'withdrawn', 'reviewed_at' => now(), 'review_notes' => 'Withdrawn by staff.']);
            $booking = Booking::whereKey($proposal->booking_id)->lockForUpdate()->firstOrFail();
            $booking->events()->create([
                'user_id' => $actor->id,
                'actor_name' => $actor->name,
                'old_status' => $booking->status,
                'new_status' => $booking->status,
                'notes' => 'Reschedule request #'.$proposal->id.' withdrawn. Original schedule is unchanged.',
            ]);
        }, 5);
    }

    private function validateProposedDates(Booking $candidate): void
    {
        // Guard every service entry point, even if a controller forgets its pair rules.
        if (boolval($candidate->start_time) !== boolval($candidate->end_time)) {
            throw ValidationException::withMessages(['end_time' => 'Choose both start and end times, or leave both empty for an all-day booking.']);
        }
        if ($candidate->ends_at->lte($candidate->starts_at)) {
            throw ValidationException::withMessages(['end_time' => 'The booking must end after it starts. For overnight bookings, choose the following day as End Date.']);
        }
        if ($candidate->starts_at->lte(now())) {
            throw ValidationException::withMessages(['start_date' => 'Choose a booking start in the future.']);
        }
        if ($candidate->start_date->diffInDays($candidate->end_date) + 1 > 30) {
            throw ValidationException::withMessages(['end_date' => 'A booking can cover at most 30 calendar days.']);
        }
    }

    private function describePeriod(Booking $booking): string
    {
        if (! $booking->start_time && ! $booking->end_time) {
            return $booking->start_date->toDateString().' to '.$booking->end_date->toDateString().' (all day)';
        }

        return $booking->start_date->toDateString().' '.substr((string) $booking->start_time, 0, 5)
            .' to '.$booking->end_date->toDateString().' '.substr((string) $booking->end_time, 0, 5);
    }

    private function describeRequestPeriod(BookingRescheduleRequest $proposal): string
    {
        return $proposal->date_range.' ('.$proposal->time_range.')';
    }

    private function closePendingReschedules(Booking $booking): void
    {
        if (! in_array($booking->status, ['pending', 'approved'], true)) {
            $booking->rescheduleRequests()->where('status', 'pending')->update([
                'status' => 'withdrawn',
                'review_notes' => 'Original booking is now '.$booking->status.'.',
                'reviewed_at' => now(),
            ]);
        }
    }

    private function ensureAvailable(Booking $candidate, array $statuses = ['pending', 'approved'], ?int $excludeBookingId = null): void
    {
        if (FacilityClosure::overlapping($candidate->facility_id, $candidate->starts_at, $candidate->ends_at)->exists()) {
            throw ValidationException::withMessages(['conflict' => 'This facility is closed for maintenance or an event during the selected time. Choose another time or facility.']);
        }
        $others = Booking::overlapping($candidate->facility_id, $candidate->start_date->toDateString(), $candidate->end_date->toDateString(), $statuses)
            ->when($excludeBookingId || $candidate->exists, fn ($q) => $q->where('id', '!=', $excludeBookingId ?? $candidate->id))->get();
        foreach ($others as $other) {
            // Half-open intervals: 09:00–10:00 and 10:00–11:00 do not conflict.
            if ($candidate->starts_at->lt($other->ends_at) && $other->starts_at->lt($candidate->ends_at)) {
                throw ValidationException::withMessages(['conflict' => 'This facility already has a pending or approved booking during the selected period.']);
            }
        }
    }

    private function record(Booking $booking, User $actor, ?string $old, ?string $notes): void
    {
        $booking->events()->create(['user_id' => $actor->id, 'actor_name' => $actor->name, 'old_status' => $old, 'new_status' => $booking->status, 'notes' => $notes]);
    }
}
