<?php

namespace App\Domain\Cargo\Enums;

use Filament\Support\Contracts\HasLabel;

enum TankStatus: string implements HasLabel
{
    case Empty = 'empty';
    case Partial = 'partial';
    case Full = 'full';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}