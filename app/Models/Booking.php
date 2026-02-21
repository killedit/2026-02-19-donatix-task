<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $fillable = [
        'external_id',
        'arrival_date',
        'departure_date',
        'room_id',
        'room_type_id',
        'status',
        'notes',
    ];

    public $incrementing = true;
    protected $keyType = 'int';

    public function guests()
    {
        return $this->belongsToMany(Guest::class)->withTimestamps();
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }
}

