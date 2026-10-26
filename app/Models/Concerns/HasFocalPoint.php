<?php

namespace App\Models\Concerns;

/**
 * Point focal d'une photo (colonnes `focal_x`, `focal_y`, en % de l'image) :
 * là où se trouve le sujet. Le site recadre la même photo différemment selon
 * l'écran (bandeau large sur ordinateur, portrait sur téléphone, tuiles de
 * formes variées) ; `object-position` garde ce point dans le cadre.
 */
trait HasFocalPoint
{
    public function initializeHasFocalPoint(): void
    {
        $this->mergeFillable(['focal_x', 'focal_y']);
        $this->mergeCasts(['focal_x' => 'integer', 'focal_y' => 'integer']);
    }

    /** Valeur CSS pour `object-position` ou `background-position`. */
    public function focalPosition(): string
    {
        return ($this->focal_x ?? 50).'% '.($this->focal_y ?? 50).'%';
    }
}
