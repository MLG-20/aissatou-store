<?php

namespace App\Filament\Resources\Admin;

use App\Enums\Admin\AdminPermission;
use App\Filament\Concerns\HasSentenceCaseTitles;
use App\Filament\Concerns\RequiresAdminPermission;
use App\Filament\Resources\Admin\AdminActivityResource\Pages;
use App\Models\AdminActivity;
use App\Support\KeyValueSummary;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
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
                TextColumn::make('created_at')->label('Date')->dateTime('d/m/Y H:i:s')->sortable(),
                TextColumn::make('admin_name')->label('Admin')->placeholder('inconnu')->searchable(),
                TextColumn::make('action')
                    ->label('Action')
                    ->badge()
                    ->formatStateUsing(fn (AdminActivity $record): string => $record->actionLabel())
                    ->color(fn (string $state): string => match ($state) {
                        'admin.login_failed' => 'danger',
                        'admin.created', 'admin.updated' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('subject_label')->label('Sur')->placeholder('—')->searchable(),
                TextColumn::make('ip')->label('IP')->placeholder('—'),
                TextColumn::make('summary')
                    ->label('Détails')
                    ->getStateUsing(fn (AdminActivity $record): ?string => KeyValueSummary::of($record->details))
                    ->wrap()
                    ->placeholder('—'),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('action')->label('Action')->options(AdminActivity::ACTIONS),
                SelectFilter::make('admin_id')->label('Admin')->relationship('admin', 'name'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdminActivities::route('/'),
        ];
    }
}
