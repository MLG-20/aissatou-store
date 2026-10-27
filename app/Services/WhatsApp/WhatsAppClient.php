<?php

namespace App\Services\WhatsApp;

use App\Enums\Order\WhatsAppTemplate;
use App\Models\Order\Order;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Envoi de messages modèles par la WhatsApp Business Cloud API (Meta).
 * Configuration : config/whatsapp.php ; spécification :
 * docs/files/08-whatsapp-cloud-api.md.
 */
class WhatsAppClient
{
    /** Actif seulement si l'interrupteur est mis ET les accès renseignés. */
    public function isConfigured(): bool
    {
        return config('whatsapp.enabled')
            && filled(config('whatsapp.phone_number_id'))
            && filled(config('whatsapp.access_token'));
    }

    /**
     * Envoie le modèle à un numéro E.164 et renvoie l'identifiant du message
     * attribué par Meta (wamid).
     *
     * @throws WhatsAppException
     */
    public function sendTemplate(string $to, WhatsAppTemplate $template, Order $order): string
    {
        $components = [[
            'type' => 'body',
            'parameters' => array_map(
                fn (string $text) => ['type' => 'text', 'text' => $text],
                $template->bodyParameters($order),
            ),
        ]];

        if (($button = $template->buttonParameter($order)) !== null) {
            $components[] = [
                'type' => 'button',
                'sub_type' => 'url',
                'index' => '0',
                'parameters' => [['type' => 'text', 'text' => $button]],
            ];
        }

        try {
            $response = $this->client()->post('/'.config('whatsapp.phone_number_id').'/messages', [
                'messaging_product' => 'whatsapp',
                'to' => ltrim($to, '+'),
                'type' => 'template',
                'template' => [
                    'name' => $template->value,
                    'language' => ['code' => config('whatsapp.template_language')],
                    'components' => $components,
                ],
            ]);
        } catch (ConnectionException) {
            throw WhatsAppException::unreachable();
        }

        $wamid = $response->json('messages.0.id');

        if (! $response->successful() || ! is_string($wamid)) {
            throw WhatsAppException::rejected($response->status(), $response->json('error'));
        }

        return $wamid;
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('whatsapp.base_url'), '/').'/'.config('whatsapp.api_version'))
            ->acceptJson()
            ->asJson()
            ->withToken((string) config('whatsapp.access_token'))
            ->timeout(config('whatsapp.timeout'))
            ->connectTimeout(5);
    }
}
