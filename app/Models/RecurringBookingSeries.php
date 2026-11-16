<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecurringBookingSeries extends Model
{
    protected $table = 'recurring_booking_series';

    protected $fillable = ['facility_id', 'user_id', 'created_by', 'frequency', 'occurrence_count'];

    public function bookings()
    {
        return $this->hasMany(Booking::class, 'recurring_series_id')->orderBy('start_date')->orderBy('id');
    }

    public function facility() { return $this->belongsTo(Facility::class); }
    public function owner() { return $this->belongsTo(User::class, 'user_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
