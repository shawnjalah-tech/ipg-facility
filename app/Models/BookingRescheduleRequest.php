<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingRescheduleRequest extends Model
{
    protected $table = 'booking_reschedule_requests';

    protected $fillable = [
        'booking_id', 'user_id', 'start_date', 'end_date', 'start_time', 'end_time',
        'status', 'reviewed_by', 'review_notes', 'reviewed_at',
    ];

    protected $casts = [
        'start_date' => 'date', 'end_date' => 'date', 'reviewed_at' => 'datetime',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function getDateRangeAttribute(): string
    {
        return $this->start_date->isSameDay($this->end_date)
            ? $this->start_date->format('d M Y')
            : $this->start_date->format('d M Y').' – '.$this->end_date->format('d M Y');
    }

    public function getTimeRangeAttribute(): string
    {
        return $this->start_time && $this->end_time
            ? substr($this->start_time, 0, 5).' – '.substr($this->end_time, 0, 5)
            : 'All day';
    }
}
