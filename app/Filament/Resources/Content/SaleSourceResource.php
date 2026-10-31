<?php

namespace App\Filament\Resources\Content;

use App\Enums\Admin\AdminPermission;
use App\Filament\Concerns\HasSentenceCaseTitles;
use App\Filament\Concerns\RequiresAdminPermission;
use App\Filament\Resources\Content\SaleSourceResource\Pages;
use App\Models\Content\SaleSource;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

/**
 * Liens de suivi (Visibilité V4) : un lien par endroit où la boutique est
 * publiée, pour voir ensuite d'où viennent les ventes. Pas de suppression :
 * les commandes passées gardent leur source, et on désactive un lien qui ne
 * sert plus.
 */
class SaleSourceResource extends Resource
{
    use HasSentenceCaseTitles;
    use RequiresAdminPermission;

    protected static function requiredPermission(): AdminPermission
    {
        return AdminPermission::Content;
    }

    protected static ?string $model = SaleSource::class;

    protected static ?string $navigationIcon = 'heroicon-o-link';

    protected static ?string $navigationGroup = 'Contenu du site';

    protected static ?string $navigationLabel = 'Liens de suivi';

    public static function getModelLabel(): string
    {
        return 'lien de suivi';
    }

    public static function getPluralModelLabel(): string
    {
        return 'liens de suivi';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Nom')
                    ->placeholder('TikTok, Influenceuse Awa…')
                    ->helperText('C\'est le nom affiché dans « Ventes par source ».')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Set $set, ?string $state, string $operation): void {
                        if ($operation === 'create') {
                            $set('code', Str::slug((string) $state));
                        }
                    }),
                TextInput::make('code')
                    ->label('Code du lien')
                    ->helperText('Rempli tout seul. Il ne peut plus changer ensuite, car il figure dans les liens déjà publiés.')
                    ->required()
                    ->maxLength(50)
                    ->regex('/^[a-z0-9-]+$/')
                    ->validationMessages(['regex' => 'Uniquement des lettres minuscules sans accent, des chiffres et des tirets.'])
                    ->unique(ignoreRecord: true)
                    ->disabledOn('edit'),
                Toggle::make('is_active')
                    ->label('Actif')
                    ->helperText('Désactivé, le lien mène toujours à la boutique, mais les visites ne sont plus attribuées à cette source.')
                    ->default(true),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description('Copiez le lien et collez-le dans la bio ou le statut correspondant. Pour viser une page précise, ajoutez ?src=code à son adresse.')
            ->defaultSort('id')
            ->paginated(false)
            ->columns([
                TextColumn::make('name')->label('Source')->searchable(),
                TextColumn::make('link')
                    ->label('Lien à copier')
                    ->state(fn (SaleSource $record): string => $record->link())
                    ->icon('heroicon-o-clipboard')
                    ->copyable()
                    ->copyMessage('Lien copié'),
                IconColumn::make('is_active')->label('Actif')->boolean(),
            ])
            ->actions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageSaleSources::route('/'),
        ];
    }
}
