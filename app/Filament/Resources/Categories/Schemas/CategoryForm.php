<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Filament\Support\AdminSupport;
use App\Models\Category;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Category Details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(1),
                                TextInput::make('code')
                                    ->maxLength(255)
                                    ->rule(fn () => Rule::unique('categories', 'code')
                                        ->where('company_id', AdminSupport::companyId())
                                        ->ignore(request()->route('record')))
                                    ->columnSpan(1),
                                Select::make('parent_id')
                                    ->label('Parent Category')
                                    ->options(fn (): array => AdminSupport::companyOptions(Category::class))
                                    ->searchable()
                                    ->preload()
                                    ->columnSpan(1),
                                Toggle::make('is_active')
                                    ->default(true)
                                    ->inline(false)
                                    ->columnSpan(1),
                                Textarea::make('description')
                                    ->rows(4)
                                    ->columnSpanFull(),
                                FileUpload::make('image_path')
                                    ->label('Storefront Image')
                                    ->image()
                                    ->disk('public')
                                    ->directory('categories')
                                    ->visibility('public')
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}
