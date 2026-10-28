<?php

namespace App\Http\Controllers\WhatsApp;

use App\Enums\Order\WhatsAppMessageStatus;
use App\Http\Controllers\Controller;
use App\Models\Order\WhatsAppMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Webhook de l'API WhatsApp Cloud (étape D, docs/files/08-whatsapp-cloud-api.md).
 *
 * GET : Meta vérifie l'abonnement avec le jeton choisi dans l'app.
 * POST : notifications signées (X-Hub-Signature-256, secret de l'app) ;
 * sans signature valide, rien n'est lu. Réponse 200 rapide, sinon Meta
 * renvoie la même notification en boucle.
 */
class WebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        // PHP lit `hub.mode` comme `hub_mode` (points remplacés).
        $expected = (string) config('whatsapp.verify_token');

        if ($expected === ''
            || $request->query('hub_mode') !== 'subscribe'
            || ! hash_equals($expected, (string) $request->query('hub_verify_token'))) {
            return response('Refusé', 403);
        }

        return response((string) $request->query('hub_challenge'));
    }

    public function receive(Request $request): Response
    {
        if (! $this->hasValidSignature($request)) {
            Log::warning('Webhook WhatsApp : signature invalide', ['ip' => $request->ip()]);

            return response('Refusé', 403);
        }

        foreach ((array) $request->input('entry', []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                $value = (array) ($change['value'] ?? []);

                foreach ((array) ($value['statuses'] ?? []) as $status) {
                    $this->applyStatus((array) $status);
                }

                foreach ((array) ($value['messages'] ?? []) as $message) {
                    // v1 : la boutique répond depuis son application WhatsApp.
                    // Ni le texte ni le numéro complet ne sont conservés ici.
                    Log::info('Message WhatsApp reçu', [
                        'de' => '…'.substr((string) ($message['from'] ?? ''), -2),
                        'type' => $message['type'] ?? null,
                    ]);
                }
            }
        }

        return response('OK');
    }

    /** Signature calculée sur le corps BRUT, comparée à temps constant. */
    private function hasValidSignature(Request $request): bool
    {
        $secret = (string) config('whatsapp.app_secret');

        if ($secret === '') {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, (string) $request->header('X-Hub-Signature-256'));
    }

    /**
     * Un statut ne recule jamais (Meta peut livrer « distribué » après
     * « lu ») ; les statuts inconnus et les messages d'ailleurs sont ignorés.
     *
     * @param  array<string, mixed>  $status
     */
    private function applyStatus(array $status): void
    {
        $new = WhatsAppMessageStatus::tryFrom((string) ($status['status'] ?? ''));
        $message = WhatsAppMessage::where('wamid', (string) ($status['id'] ?? ''))->first();

        if (! $new || ! $message || $new->rank() <= $message->status->rank()) {
            return;
        }

        $error = $status['errors'][0] ?? null;

        $message->update([
            'status' => $new,
            'error' => $new === WhatsAppMessageStatus::Failed && is_array($error)
                ? mb_substr('Refus de Meta '.($error['code'] ?? '?').' : '.($error['title'] ?? $error['message'] ?? 'inconnu'), 0, 255)
                : $message->error,
        ]);
    }
}
