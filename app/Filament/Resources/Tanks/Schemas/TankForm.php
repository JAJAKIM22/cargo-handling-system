<?php

namespace App\Filament\Resources\Tanks\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TankForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required(),
                TextInput::make('name'),
                TextInput::make('product_id')
                    ->required()
                    ->numeric(),
                TextInput::make('capacity')
                    ->required()
                    ->numeric(),
                TextInput::make('current_volume')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('status')
                    ->required()
                    ->default('empty'),
            ]);
    }
}
