<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FacilityClosure extends Model
{
    use SoftDeletes;
    protected $fillable = ['facility_id', 'created_by', 'starts_at', 'ends_at', 'reason', 'notes'];
    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    public function facility() { return $this->belongsTo(Facility::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function scopeOverlapping($query, int $facilityId, $start, $end)
    {
        return $query->where('facility_id', $facilityId)->where('starts_at', '<', $end)->where('ends_at', '>', $start);
    }
}
