<?php

namespace App\Filament\Resources\Events\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class EventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(200)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Set $set, Get $get, ?string $state) => blank($get('slug')) ? $set('slug', Str::slug((string) $state)) : null),
                TextInput::make('slug')
                    ->required()
                    ->alphaDash()
                    ->unique(ignoreRecord: true),
                Textarea::make('description')
                    ->columnSpanFull(),
                DateTimePicker::make('starts_at'),
                DateTimePicker::make('ends_at')->afterOrEqual('starts_at'),
                Toggle::make('is_active')
                    ->default(true),
            ]);
    }
}
