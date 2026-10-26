<?php

namespace App\Filament\Forms;

use Closure;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

/**
 * Réglage du point focal en back-office, pour une utilisatrice non
 * technicienne : deux curseurs (« de gauche à droite », « de haut en bas »)
 * et un aperçu qui montre la photo entière avec le point, puis CHAQUE
 * recadrage que le site en fait réellement (bandeau ordinateur, téléphone,
 * tuile…). On règle en regardant le résultat, pas des pourcentages.
 */
class FocalPointFields
{
    /**
     * @param  array<string, string>|Closure(Model): array<string, string>  $frames  libellé => ratio CSS
     *                                                                               (« 16 / 9 ») des recadrages du site
     * @return array<Component>
     */
    public static function make(array|Closure $frames, string $pathField = 'image_path'): array
    {
        return [
            Placeholder::make('focal_preview')
                ->label('Cadrage sur le site')
                ->hidden(fn (?Model $record): bool => blank($record?->{$pathField}))
                ->content(fn (Get $get, ?Model $record): HtmlString => self::preview(
                    Storage::disk('public')->url($record->{$pathField}),
                    (int) ($get('focal_x') ?? 50),
                    (int) ($get('focal_y') ?? 50),
                    $frames instanceof Closure ? $frames($record) : $frames,
                ))
                ->columnSpanFull(),

            Grid::make(2)
                ->schema([
                    self::slider('focal_x', 'Le sujet : de gauche (0) à droite (100)'),
                    self::slider('focal_y', 'Le sujet : du haut (0) vers le bas (100)'),
                ])
                ->columnSpanFull(),
        ];
    }

    private static function slider(string $name, string $label): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->type('range')
            ->minValue(0)
            ->maxValue(100)
            ->step(1)
            ->default(50)
            ->required()
            ->live()
            ->helperText($name === 'focal_x'
                ? 'Placez le point sur le visage ou la pièce : il reste visible quel que soit l\'écran.'
                : null);
    }

    /** @param  array<string, string>  $frames */
    private static function preview(string $url, int $x, int $y, array $frames): HtmlString
    {
        $url = e($url);
        $position = "{$x}% {$y}%";

        $original = '<figure style="margin:0;flex:0 0 auto;">'
            .'<div style="position:relative;display:inline-block;">'
            .'<img src="'.$url.'" alt="" style="display:block;max-height:170px;max-width:220px;border-radius:.375rem;">'
            .'<span style="position:absolute;left:'.$x.'%;top:'.$y.'%;width:22px;height:22px;transform:translate(-50%,-50%);'
            .'border-radius:9999px;border:2px solid #FBF7F1;background:rgba(159,45,64,.85);box-shadow:0 0 0 3px rgba(0,0,0,.25);"></span>'
            .'</div>'
            .'<figcaption style="margin-top:.35rem;font-size:.75rem;color:#6b7280;">Photo entière</figcaption></figure>';

        $crops = collect($frames)->map(fn (string $ratio, string $label): string => '<figure style="margin:0;flex:0 0 auto;">'
            .'<div style="height:170px;max-width:100%;aspect-ratio:'.e($ratio).';overflow:hidden;border-radius:.375rem;background:#17151B;">'
            .'<img src="'.$url.'" alt="" style="width:100%;height:100%;object-fit:cover;object-position:'.$position.';">'
            .'</div>'
            .'<figcaption style="margin-top:.35rem;font-size:.75rem;color:#6b7280;">'.e($label).'</figcaption></figure>'
        )->implode('');

        return new HtmlString('<div style="display:flex;flex-wrap:wrap;gap:1rem;align-items:flex-start;">'.$original.$crops.'</div>');
    }
}
