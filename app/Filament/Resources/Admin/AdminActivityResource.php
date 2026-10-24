<?php

namespace App\Filament\Resources\Admin;

use App\Enums\Admin\AdminPermission;
use App\Filament\Concerns\HasSentenceCaseTitles;
use App\Filament\Concerns\RequiresAdminPermission;
use App\Filament\Pages\SiteSettings;
use App\Filament\Resources\Admin\AdminActivityResource\Pages;
use App\Filament\Resources\Customer\CustomerResource;
use App\Filament\Resources\Order\OrderResource;
use App\Filament\Resources\Order\PromoCodeResource;
use App\Models\Admin;
use App\Models\AdminActivity;
use App\Models\Content\SiteSetting;
use App\Models\Order\Order;
use App\Models\Order\PromoCode;
use App\Models\User;
use App\Support\AdminActivityDetails;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Journal d'activité admin (Phase 6A) : qui a fait quoi, quand, depuis où.
 * Lecture seule, réservé à l'admin complet.
 */
class AdminActivityResource extends Resource
{
    use HasSentenceCaseTitles;
    use RequiresAdminPermission;

    protected static function requiredPermission(): AdminPermission
    {
        return AdminPermission::Admins;
    }

    protected static ?string $model = AdminActivity::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'Journal d\'activité';

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return 'activité';
    }

    public static function getPluralModelLabel(): string
    {
        return 'journal d\'activité';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Date')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('admin_name')->label('Qui')->placeholder('inconnu')->searchable(),
                TextColumn::make('action')
                    ->label('Action')
                    ->badge()
                    ->formatStateUsing(fn (AdminActivity $record): string => $record->actionLabel())
                    ->color(fn (string $state): string => self::actionColor($state)),
                // Détails et adresse IP : sur la fiche de la ligne.
                TextColumn::make('subject_label')
                    ->label('Sur')
                    ->placeholder('—')
                    ->searchable()
                    ->url(fn (AdminActivity $record): ?string => self::subjectUrl($record))
                    ->color(fn (AdminActivity $record): ?string => self::subjectUrl($record) ? 'primary' : null),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('action')->label('Action')->options(AdminActivity::ACTIONS),
                SelectFilter::make('admin_id')->label('Qui')->relationship('admin', 'name'),
                // Une ligne par fiche client ouverte : utile pour la protection
                // des données, mais elle noierait les vraies actions.
                Filter::make('hide_views')
                    ->label('Masquer les consultations de fiches clients')
                    ->toggle()
                    ->default()
                    ->query(fn (Builder $query): Builder => $query->where('action', '!=', 'customer.viewed')),
            ])
            ->actions([
                ViewAction::make()->label('Voir la fiche'),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('En clair')
                    ->schema([
                        TextEntry::make('sentence')
                            ->label('')
                            ->getStateUsing(fn (AdminActivity $record): string => AdminActivityDetails::sentence($record))
                            ->size(TextEntry\TextEntrySize::Large)
                            ->columnSpanFull(),
                        TextEntry::make('created_at')->label('Le')->dateTime('d/m/Y à H:i:s'),
                        TextEntry::make('admin_name')->label('Qui')->placeholder('inconnu'),
                        TextEntry::make('action')
                            ->label('Action')
                            ->badge()
                            ->formatStateUsing(fn (AdminActivity $record): string => $record->actionLabel())
                            ->color(fn (string $state): string => self::actionColor($state)),
                        TextEntry::make('subject_label')
                            ->label('Sur')
                            ->placeholder('—')
                            ->url(fn (AdminActivity $record): ?string => self::subjectUrl($record))
                            ->color(fn (AdminActivity $record): ?string => self::subjectUrl($record) ? 'primary' : null),
                    ])
                    ->columns(4),

                Section::make('Détails')
                    ->visible(fn (AdminActivity $record): bool => filled($record->details))
                    ->schema([
                        // Jamais de secret ici (un mot de passe changé = « oui »).
                        KeyValueEntry::make('details')
                            ->label('')
                            ->keyLabel('Information')
                            ->valueLabel('Valeur')
                            ->getStateUsing(fn (AdminActivity $record): array => AdminActivityDetails::of($record)),
                    ]),

                Section::make('Informations techniques')
                    ->description('Utiles en cas de doute sur l\'origine d\'une action.')
                    ->collapsed()
                    ->schema([
                        TextEntry::make('id')->label('N° de l\'entrée'),
                        TextEntry::make('ip')->label('Adresse IP d\'origine')->placeholder('—'),
                    ])
                    ->columns(2),
            ]);
    }

    private static function actionColor(string $action): string
    {
        return match ($action) {
            'admin.login_failed' => 'danger',
            'admin.created', 'admin.updated' => 'warning',
            default => 'gray',
        };
    }

    /**
     * Lien vers ce sur quoi porte l'action, si la page existe et que l'admin
     * connecté y a accès (sinon, simple libellé).
     */
    private static function subjectUrl(AdminActivity $record): ?string
    {
        if (! $record->subject_id) {
            return null;
        }

        $target = match ($record->subject_type) {
            Order::class => [OrderResource::class, 'view'],
            User::class => [CustomerResource::class, 'view'],
            PromoCode::class => [PromoCodeResource::class, 'edit'],
            Admin::class => [AdminResource::class, 'edit'],
            SiteSetting::class => SiteSettings::class,
            default => null,
        };

        if ($target === SiteSettings::class) {
            return SiteSettings::canAccess() ? SiteSettings::getUrl() : null;
        }

        if (! $target || ! $target[0]::canAccess()) {
            return null;
        }

        return $target[0]::getUrl($target[1], ['record' => $record->subject_id]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdminActivities::route('/'),
            'view' => Pages\ViewAdminActivity::route('/{record}'),
        ];
    }
}
