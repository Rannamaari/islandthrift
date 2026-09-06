<?php

namespace App\Filament\Resources\AuditLogs\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AuditLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Audit Event')->schema([
                Grid::make(3)->schema([
                    TextEntry::make('created_at')->label('Date & Time')->dateTime('d M Y, h:i:s A'),
                    TextEntry::make('actor_name')->label('Changed By')->placeholder('System / Website'),
                    TextEntry::make('actor_email')->label('User Account')->placeholder('Not signed in'),
                    TextEntry::make('event')->badge(),
                    TextEntry::make('entity_type')->label('Area')->formatStateUsing(fn (string $state): string => class_basename($state)),
                    TextEntry::make('entity_label')->label('Record'),
                    TextEntry::make('entity_id')->label('Record ID')->copyable()->columnSpanFull(),
                    TextEntry::make('ip_address')->label('IP Address')->placeholder('Background process'),
                    TextEntry::make('request_method')->label('Request'),
                    TextEntry::make('request_url')->label('Page / Endpoint')->placeholder('Background process')->columnSpanFull(),
                    TextEntry::make('user_agent')->label('Device / Browser')->placeholder('Background process')->columnSpanFull(),
                ]),
            ]),
            Section::make('Before Change')->schema([
                TextEntry::make('old_values')->label('Previous Values')->state(fn ($record): string => self::json($record->old_values))->markdown()->columnSpanFull(),
            ])->visible(fn ($record): bool => ! empty($record->old_values)),
            Section::make('After Change')->schema([
                TextEntry::make('new_values')->label('New Values')->state(fn ($record): string => self::json($record->new_values))->markdown()->columnSpanFull(),
            ])->visible(fn ($record): bool => ! empty($record->new_values)),
        ]);
    }

    private static function json(?array $values): string
    {
        return "```json\n".json_encode($values ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n```";
    }
}
