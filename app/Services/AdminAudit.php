<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\AdminActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Trace une action sensible du back-office avec l'admin authentifié précis
 * (Phase 6A, responsabilisation et audit). Jamais de secret dans `details`
 * (un mot de passe changé = « oui », pas sa valeur).
 */
class AdminAudit
{
    /** @param  array<string, mixed>  $details */
    public static function record(string $action, ?Model $subject = null, array $details = [], ?Admin $admin = null, ?string $label = null): AdminActivity
    {
        $admin ??= Auth::guard('admin')->user();

        return AdminActivity::create([
            'admin_id' => $admin?->id,
            'admin_name' => $admin?->name,
            'action' => $action,
            'subject_type' => $subject ? $subject->getMorphClass() : null,
            'subject_id' => $subject?->getKey(),
            'subject_label' => $label ?? self::labelFor($subject),
            'details' => $details ?: null,
            'ip' => request()?->ip(),
        ]);
    }

    private static function labelFor(?Model $subject): ?string
    {
        return match (true) {
            $subject === null => null,
            isset($subject->number) => (string) $subject->number,
            isset($subject->email) => (string) $subject->email,
            isset($subject->name) => (string) $subject->name,
            default => class_basename($subject).' #'.$subject->getKey(),
        };
    }
}
