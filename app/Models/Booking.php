<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'facility_id', 'user_id',
        'start_date', 'end_date',
        'start_time', 'end_time',
        'purpose', 'status', 'admin_remarks', 'recurring_series_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function scopeSearch($query, ?string $search)
    {
        if (trim((string) $search) === '') {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('purpose', 'like', '%'.$search.'%')
                ->orWhereHas('facility', fn ($f) => $f->where('name', 'like', '%'.$search.'%'))
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', '%'.$search.'%'));
        });
    }

    public function getStartsAtAttribute(): Carbon
    {
        return $this->start_date->copy()->setTimeFromTimeString($this->start_time ?: '00:00:00');
    }

    public function getEndsAtAttribute(): Carbon
    {
        return $this->end_time
            ? $this->end_date->copy()->setTimeFromTimeString($this->end_time)
            : $this->end_date->copy()->addDay()->startOfDay();
    }

    public function recurringSeries()
    {
        return $this->belongsTo(RecurringBookingSeries::class, 'recurring_series_id');
    }

    public function rescheduleRequests()
    {
        return $this->hasMany(BookingRescheduleRequest::class);
    }

    public function events()
    {
        return $this->hasMany(BookingEvent::class);
    }

    public function facility()
    {
        return $this->belongsTo(Facility::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope: bookings that overlap a given date range for a given facility.
     *
     * Two ranges [A_start, A_end] and [B_start, B_end] overlap iff:
     *   A_start <= B_end  AND  B_start <= A_end
     *
     * This catches every case — partial overlap on either end AND full
     * containment (existing entirely inside new, or vice versa).
     */
    public function scopeOverlapping($query, int $facilityId, string $startDate, string $endDate, array $statuses = ['pending', 'approved'])
    {
        return $query->where('facility_id', $facilityId)
            ->whereIn('status', $statuses)
            ->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate);
    }

    /**
     * Human-friendly date range, e.g. "10 Sep 2026 – 14 Sep 2026".
     * Collapses to a single date when start == end.
     */
    public function getDateRangeAttribute(): string
    {
        if ($this->start_date->equalTo($this->end_date)) {
            return $this->start_date->format('d M Y');
        }

        return $this->start_date->format('d M Y').' – '.$this->end_date->format('d M Y');
    }

    /**
     * Bootstrap badge class for the current status.
     */
    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'approved' => 'bg-success',
            'pending' => 'bg-warning text-dark',
            'rejected' => 'bg-danger',
            'cancelled' => 'bg-secondary',
            'expired' => 'bg-dark',
            default => 'bg-secondary',
        };
    }

    // Convenience accessor used by the timeline view (Phase 7)
    public function getBookerNameAttribute(): string
    {
        return $this->user?->name ?? 'Unknown';
    }
}
