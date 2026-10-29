<?php

namespace App\Filament\Resources\Content;

use App\Enums\Admin\AdminPermission;
use App\Enums\Content\CookieCategory;
use App\Filament\Concerns\HasSentenceCaseTitles;
use App\Filament\Concerns\RequiresAdminPermission;
use App\Filament\Resources\Content\CookieDeclarationResource\Pages;
use App\Models\Content\CookieDeclaration;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Registre des cookies (Phase 7) : chaque outil qui dépose un cookie ou un
 * traceur (mesure d'audience, suivi d'erreurs…) s'y déclare. La page
 * « Politique cookies » l'affiche tel quel, et un cookie actif hors
 * « nécessaires » fait apparaître le bandeau de consentement.
 */
class CookieDeclarationResource extends Resource
{
    use HasSentenceCaseTitles;
    use RequiresAdminPermission;

    protected static function requiredPermission(): AdminPermission
    {
        return AdminPermission::Settings;
    }

    protected static ?string $model = CookieDeclaration::class;

    protected static ?string $navigationIcon = 'heroicon-o-finger-print';

    protected static ?string $navigationGroup = 'Contenu du site';

    protected static ?string $navigationLabel = 'Cookies et traceurs';

    public static function getModelLabel(): string
    {
        return 'cookie';
    }

    public static function getPluralModelLabel(): string
    {
        return 'cookies';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Nom du cookie')
                    ->helperText('Tel que le donne l\'outil, ex. « _ga ».')
                    ->required()
                    ->maxLength(255),
                TextInput::make('provider')
                    ->label('Fournisseur')
                    ->placeholder('Google Analytics, Sentry…')
                    ->required()
                    ->maxLength(255),
                Select::make('category')
                    ->label('Catégorie')
                    ->options(collect(CookieCategory::cases())->mapWithKeys(fn (CookieCategory $category) => [$category->value => $category->label()]))
                    ->helperText('Hors « Nécessaires », le visiteur doit donner son accord : le bandeau de consentement apparaît dès qu\'un tel cookie est actif.')
                    ->required(),
                TextInput::make('duration')
                    ->label('Durée de conservation')
                    ->placeholder('13 mois, Session…')
                    ->required()
                    ->maxLength(255),
                Textarea::make('purpose')
                    ->label('À quoi il sert')
                    ->helperText('En une phrase simple : c\'est ce que lit le visiteur.')
                    ->rows(2)
                    ->required()
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('Actif')
                    ->helperText('Décochez quand l\'outil est retiré du site : il disparaît de la page cookies.')
                    ->default(true),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('position')
            ->reorderable('position')
            ->paginated(false)
            ->columns([
                TextColumn::make('name')->label('Cookie')->searchable(),
                TextColumn::make('provider')->label('Fournisseur'),
                TextColumn::make('category')->label('Catégorie')
                    ->formatStateUsing(fn (CookieCategory $state) => $state->label())
                    ->badge()
                    ->color(fn (CookieCategory $state) => $state->requiresConsent() ? 'warning' : 'gray'),
                TextColumn::make('duration')->label('Durée'),
                IconColumn::make('is_active')->label('Actif')->boolean(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageCookieDeclarations::route('/'),
        ];
    }
}
