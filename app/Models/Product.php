<?php

namespace App\Models;

use App\Domain\Cargo\Enums\ProductType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'description'];

    protected function casts(): array
    {
        return [
            'code' => ProductType::class,
        ];
    }

    public function tanks(): HasMany
    {
        return $this->hasMany(Tank::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CargoMovement::class);
    }
}