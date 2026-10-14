<?php

namespace App\Filament\Resources\Content;

use App\Filament\Resources\Content\ContactMessageResource\Pages;
use App\Models\Contact\ContactMessage;
use Filament\Forms\Form;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * Messages du formulaire Contact (Phase 2.2E) : lecture seule côté boutique
 * (on ne réécrit pas ce qu'un visiteur a écrit), avec suivi lu/non-lu,
 * réponse par email et suppression. Le compteur dans la navigation signale
 * ce qui reste à traiter.
 */
class ContactMessageResource extends Resource
{
    protected static ?string $model = ContactMessage::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    protected static ?string $navigationGroup = 'Contenu du site';

    protected static ?string $navigationLabel = 'Messages reçus';

    public static function getModelLabel(): string
    {
        return 'message';
    }

    public static function getPluralModelLabel(): string
    {
        return 'messages';
    }

    public static function getNavigationBadge(): ?string
    {
        $unread = ContactMessage::query()->where('is_read', false)->count();

        return $unread > 0 ? (string) $unread : null;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                IconColumn::make('is_read')
                    ->label('Lu')
                    ->boolean()
                    ->trueIcon('heroicon-o-check')
                    ->falseIcon('heroicon-o-exclamation-circle')
                    ->falseColor('warning'),
                TextColumn::make('name')->label('Nom')->searchable(),
                TextColumn::make('email')->label('Email')->searchable(),
                TextColumn::make('message')->label('Message')->limit(60),
                TextColumn::make('created_at')->label('Reçu le')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TernaryFilter::make('is_read')->label('Lu'),
            ])
            ->actions([
                ViewAction::make(),
                Action::make('toggleRead')
                    ->label(fn (ContactMessage $record): string => $record->is_read ? 'Marquer non lu' : 'Marquer comme lu')
                    ->icon('heroicon-o-check')
                    ->action(fn (ContactMessage $record): bool => $record->update(['is_read' => ! $record->is_read])),
                DeleteAction::make(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Message')
                    ->schema([
                        TextEntry::make('name')->label('Nom'),
                        TextEntry::make('email')->label('Email'),
                        TextEntry::make('created_at')->label('Reçu le')->dateTime('d/m/Y à H:i'),
                        TextEntry::make('message')->label('Contenu')->columnSpanFull(),
                    ])
                    ->columns(3),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContactMessages::route('/'),
            'view' => Pages\ViewContactMessage::route('/{record}'),
        ];
    }
}
