<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    //
    protected $fillable = [
        // 'id',
        'external_id',
        'number',
        'floor',
        'room_type_id',
    ];

    public $incrementing = true;
    protected $keyType = 'int';

    public function roomType()
    {
        return $this->belongsTo(RoomType::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}
