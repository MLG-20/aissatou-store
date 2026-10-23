<?php

namespace App\Filament\Resources\Content;

use App\Enums\Admin\AdminPermission;
use App\Filament\Concerns\RequiresAdminPermission;
use App\Filament\Resources\Content\ReviewResource\Pages;
use App\Models\Content\Review;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class ReviewResource extends Resource
{
    use RequiresAdminPermission;

    protected static function requiredPermission(): AdminPermission
    {
        return AdminPermission::Content;
    }

    protected static ?string $model = Review::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationGroup = 'Contenu du site';

    protected static ?string $navigationLabel = 'Avis clients';

    protected static ?string $modelLabel = 'avis client';

    protected static ?string $pluralModelLabel = 'avis clients';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('author_name')
                    ->label('Nom de la cliente')
                    ->required()
                    ->maxLength(255),

                TextInput::make('location')
                    ->label('Ville / pays (optionnel)')
                    ->helperText('Ex. : Dakar. Affiché sous le nom.')
                    ->maxLength(255),

                Select::make('rating')
                    ->label('Note (étoiles)')
                    ->options([
                        5 => '★★★★★ (5)',
                        4 => '★★★★ (4)',
                        3 => '★★★ (3)',
                        2 => '★★ (2)',
                        1 => '★ (1)',
                    ])
                    ->helperText('Optionnel — laissez vide pour ne pas afficher d\'étoiles.'),

                Textarea::make('comment')
                    ->label('Avis')
                    ->required()
                    ->rows(4)
                    ->maxLength(600)
                    ->columnSpanFull(),

                Toggle::make('is_published')
                    ->label('Publié (visible sur le site)')
                    ->helperText('Décochez pour retirer l\'avis du site sans le supprimer (modération).')
                    ->default(true),

                TextInput::make('position')
                    ->label("Ordre d'affichage")
                    ->helperText('Un plus petit nombre apparaît en premier.')
                    ->numeric()
                    ->default(0)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('position')
            ->columns([
                TextColumn::make('author_name')->label('Cliente')->searchable(),
                TextColumn::make('rating')->label('Note')->formatStateUsing(fn (?int $state): string => $state ? str_repeat('★', $state) : '—'),
                TextColumn::make('comment')->label('Avis')->limit(60)->wrap(),
                ToggleColumn::make('is_published')->label('Publié'),
                TextColumn::make('position')->label('Ordre')->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_published')->label('Publié'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageReviews::route('/'),
        ];
    }
}
