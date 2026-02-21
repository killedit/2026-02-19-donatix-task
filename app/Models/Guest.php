<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Guest extends Model
{
    //
    protected $fillable = [
        // 'id',
        'external_id',
        'first_name',
        'last_name',
        'email',
    ];

    public $incrementing = true;
    protected $keyType = 'int';

    public function bookings()
    {
        return $this->belongsToMany(Booking::class);
    }
}
