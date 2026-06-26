<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
     protected $fillable = [
        'owner_name',
        'address',
        'vehicle_name',
        'plate_number',
        'service_date',
    ];

    public function parts()
    {
        return $this->hasMany(
            VehiclePart::class
        );
    }
}
