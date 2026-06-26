<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehiclePart extends Model
{
     protected $fillable = [
        'vehicle_id',
        'part_name',
        'price',
        'created_at'
    ];

    public function vehicle()
    {
        return $this->belongsTo(
            Vehicle::class
        );
    }
}
