<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * HTML de l'éditeur riche (Journal, pages légales) assaini avant affichage :
 * on ne garde qu'une liste fermée de balises de mise en forme — aucun script,
 * même si le contenu venait d'une source compromise. C'est ce qui permet
 * d'afficher avec `{!! !!}` sans ouvrir de faille XSS persistante.
 *
 * Pas `strip_tags` : il garde les attributs des balises autorisées
 * (`<p onmouseover=…>`, `<a href="javascript:…">`). Le sanitizer ne
 * garde que `href` sur les liens, en http(s) ou mailto.
 */
class RichText
{
    public static function sanitize(?string $html): string
    {
        $config = (new HtmlSanitizerConfig)
            ->allowElement('a', ['href'])
            ->allowLinkSchemes(['https', 'http', 'mailto'])
            ->allowRelativeLinks()
            ->forceAttribute('a', 'rel', 'noopener noreferrer nofollow');

        foreach (['p', 'br', 'strong', 'em', 'u', 'ul', 'ol', 'li', 'h2', 'h3', 'blockquote'] as $element) {
            $config = $config->allowElement($element);
        }

        return (new HtmlSanitizer($config))->sanitize($html ?? '');
    }
}
