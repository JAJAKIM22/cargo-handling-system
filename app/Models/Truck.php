<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Truck extends Model
{
    use HasFactory;

    protected $fillable = ['plate_number', 'driver_name', 'transporter'];

    public function movements(): HasMany
    {
        return $this->hasMany(CargoMovement::class);
    }
}