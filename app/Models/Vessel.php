<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vessel extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'imo_number', 'voyage_reference'];

    public function movements(): HasMany
    {
        return $this->hasMany(CargoMovement::class);
    }

    public function tanks(): HasMany
    {
        return $this->hasMany(Tank::class, 'current_vessel_id');
    }
}