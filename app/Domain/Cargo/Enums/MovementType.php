<?php

namespace App\Domain\Cargo\Enums;

use Filament\Support\Contracts\HasLabel;

enum MovementType: string implements HasLabel
{
    case Discharge = 'discharge'; // inbound: vessel -> tank (0.5% transit loss applies)
    case Delivery = 'delivery';   // outbound: tank -> truck (no loss)

    public function label(): string
    {
        return match ($this) {
            self::Discharge => 'Vessel discharge',
            self::Delivery => 'Truck delivery',
        };
    }
    public function getLabel(): string
    {
        return $this->label();
    }
}