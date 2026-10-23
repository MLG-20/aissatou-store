<?php

namespace App\Filament\Resources\Admin;

use App\Enums\Admin\AdminPermission;
use App\Filament\Concerns\RequiresAdminPermission;
use App\Filament\Resources\Admin\AdminResource\Pages;
use App\Models\Admin;
use App\Support\AdminRoles;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Resource;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Comptes du back-office (Phase 6A), réservés à l'admin complet. Pas de
 * suppression : un compte se désactive (accès coupé immédiatement, cf.
 * Admin::canAccessPanel) et reste l'auteur identifié de ses actions passées.
 * Les garde-fous (soi-même, dernier admin) sont dans AdminAccountService.
 */
class AdminResource extends Resource
{
    use RequiresAdminPermission;

    protected static function requiredPermission(): AdminPermission
    {
        return AdminPermission::Admins;
    }

    protected static ?string $model = Admin::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'Comptes admin';

    public static function getModelLabel(): string
    {
        return 'compte admin';
    }

    public static function getPluralModelLabel(): string
    {
        return 'comptes admin';
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make()
                ->schema([
                    TextInput::make('name')->label('Nom')->required()->maxLength(255),
                    TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),
                    Select::make('role')
                        ->label('Rôle')
                        ->options(AdminRoles::options())
                        ->required()
                        ->helperText('Gestionnaire : catalogue, stock et commandes uniquement.'),
                    Toggle::make('is_active')
                        ->label('Compte actif')
                        ->helperText('Désactiver coupe l\'accès immédiatement, sans perdre l\'historique.')
                        ->default(true),
                    TextInput::make('password')
                        ->label(fn ($livewire): string => $livewire instanceof CreateRecord ? 'Mot de passe' : 'Nouveau mot de passe')
                        ->password()
                        ->revealable()
                        ->minLength(12)
                        ->required(fn ($livewire): bool => $livewire instanceof CreateRecord)
                        ->helperText('12 caractères minimum. À transmettre en main propre ; la personne pourra le changer depuis son profil.')
                        ->dehydrated(fn (?string $state): bool => filled($state)),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nom')->searchable(),
                TextColumn::make('email')->label('Email')->searchable(),
                TextColumn::make('roles.name')
                    ->label('Rôle')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => AdminRoles::label($state))
                    ->placeholder('Aucun rôle (aucun accès)'),
                IconColumn::make('is_active')->label('Actif')->boolean(),
                TextColumn::make('created_at')->label('Créé le')->dateTime('d/m/Y')->sortable(),
            ])
            ->defaultSort('name')
            ->actions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdmins::route('/'),
            'create' => Pages\CreateAdmin::route('/create'),
            'edit' => Pages\EditAdmin::route('/{record}/edit'),
        ];
    }
}
