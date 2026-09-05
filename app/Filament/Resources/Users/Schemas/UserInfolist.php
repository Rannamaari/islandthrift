<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('User')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('name'),
                                TextEntry::make('email'),
                                TextEntry::make('phone'),
                                TextEntry::make('company.name'),
                                TextEntry::make('branch.name'),
                                TextEntry::make('warehouse.name'),
                                TextEntry::make('roles.name')
                                    ->badge()
                                    ->listWithLineBreaks(),
                                IconEntry::make('is_active')->boolean(),
                                TextEntry::make('last_login_at')->dateTime(),
                            ]),
                    ]),
            ]);
    }
}
