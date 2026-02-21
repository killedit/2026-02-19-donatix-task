<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomType extends Model
{
    protected $fillable = [
        'external_id',
        'name',
        'description'
    ];
    public $incrementing = true;
    protected $keyType = 'int';

    public function rooms()
    {
        return $this->hasMany(Room::class);
    }
}
