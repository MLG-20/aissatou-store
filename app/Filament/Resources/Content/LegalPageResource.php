<?php

namespace App\Filament\Resources\Content;

use App\Enums\Admin\AdminPermission;
use App\Filament\Concerns\HasSentenceCaseTitles;
use App\Filament\Concerns\RequiresAdminPermission;
use App\Filament\Resources\Content\LegalPageResource\Pages;
use App\Models\Content\LegalPage;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/**
 * Pages légales (Phase 7) : la cliente en tient le texte à jour sans toucher
 * au code — un nouvel outil (mesure d'audience…), une nouvelle condition de
 * vente. Les quatre pages sont fixes (LegalDocument) : on les modifie, on ne
 * les crée ni ne les supprime.
 */
class LegalPageResource extends Resource
{
    use HasSentenceCaseTitles;
    use RequiresAdminPermission;

    protected static function requiredPermission(): AdminPermission
    {
        return AdminPermission::Settings;
    }

    protected static ?string $model = LegalPage::class;

    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationGroup = 'Contenu du site';

    protected static ?string $navigationLabel = 'Pages légales';

    public static function getModelLabel(): string
    {
        return 'page légale';
    }

    public static function getPluralModelLabel(): string
    {
        return 'pages légales';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('title')
                    ->label('Titre')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Placeholder::make('placeholders_help')
                    ->label('Repères automatiques')
                    ->content(new HtmlString(
                        'Écrivez un repère tel quel dans le texte : il est remplacé sur le site par la valeur des '
                        .'<strong>Réglages du site &gt; Identité légale</strong>.<br>'
                        .collect(LegalPage::placeholders())
                            ->map(fn (string $label, string $placeholder) => '<code>'.e($placeholder).'</code> '.e($label))
                            ->implode(' · ')
                    ))
                    ->columnSpanFull(),
                RichEditor::make('content')
                    ->label('Texte')
                    ->helperText("Titres, gras, listes, liens — les scripts sont retirés à l'affichage.")
                    ->toolbarButtons(['h2', 'h3', 'bold', 'italic', 'underline', 'bulletList', 'orderedList', 'link', 'blockquote', 'undo', 'redo'])
                    ->columnSpanFull(),
                Toggle::make('is_published')
                    ->label('Publiée')
                    ->helperText('Tant que ce n\'est pas coché, la page reste invisible et absente du pied de page.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('Page'),
                IconColumn::make('is_published')->label('Publiée')->boolean(),
                TextColumn::make('updated_at')->label('Dernière mise à jour')->dateTime('d/m/Y à H:i'),
            ])
            ->defaultSort('id')
            ->paginated(false)
            ->actions([
                Action::make('view')
                    ->label('Voir')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (LegalPage $record) => route('shop.legal', $record->document))
                    ->openUrlInNewTab()
                    ->visible(fn (LegalPage $record) => $record->is_published),
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLegalPages::route('/'),
            'edit' => Pages\EditLegalPage::route('/{record}/modifier'),
        ];
    }
}
