<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Filament\Support\AdminSupport;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('company_id')->default(fn (): ?string => AdminSupport::companyId()),
                Section::make('User Details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')->required()->maxLength(255),
                                TextInput::make('email')->required()->email()->maxLength(255),
                                TextInput::make('phone')->tel()->maxLength(50),
                                TextInput::make('password')
                                    ->password()
                                    ->required(fn (string $operation): bool => $operation === 'create')
                                    ->dehydrated(fn (?string $state): bool => filled($state))
                                    ->maxLength(255),
                                Select::make('branch_id')
                                    ->options(fn (): array => AdminSupport::branchOptions())
                                    ->searchable()
                                    ->preload()
                                    ->live(),
                                Select::make('warehouse_id')
                                    ->options(fn (Get $get): array => AdminSupport::warehouseOptions($get('branch_id')))
                                    ->searchable()
                                    ->preload(),
                                Toggle::make('is_active')->default(true)->inline(false),
                            ]),
                    ]),
                Section::make('Access Roles')
                    ->schema([
                        CheckboxList::make('roles')
                            ->relationship('roles', 'name')
                            ->columns(2)
                            ->gridDirection('row'),
                    ]),
            ]);
    }
}
