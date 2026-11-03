<?php

namespace App\Domain\Cargo\Enums;

use Filament\Support\Contracts\HasLabel;

enum MovementStatus: string implements HasLabel
{
    case Pending = 'pending';
    case Applied = 'applied';
    case Rejected = 'rejected';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}