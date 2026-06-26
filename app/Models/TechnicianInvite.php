<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TechnicianInvite extends Model
{
    protected $fillable = [
        'name',
        'email',
        'token',
        'expires_at',
        'used_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];
}