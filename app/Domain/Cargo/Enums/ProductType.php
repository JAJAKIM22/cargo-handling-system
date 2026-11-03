<?php

namespace App\Domain\Cargo\Enums;

use Filament\Support\Contracts\HasLabel;

enum ProductType: string implements HasLabel
{
    case CPO = 'CPO';
    case CPOL = 'CPOL';
    case PFAD = 'PFAD';

    public function label(): string
    {
        return match ($this) {
            self::CPO => 'Crude Palm Oil',
            self::CPOL => 'Crude Palm Olein',
            self::PFAD => 'Palm Fatty Acid Distillate',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}