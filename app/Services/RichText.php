<?php

namespace App\Services;

use Illuminate\Support\HtmlString;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class RichText
{
    private HtmlSanitizer $sanitizer;

    public function __construct()
    {
        $config = (new HtmlSanitizerConfig)->allowLinkSchemes(['http', 'https', 'mailto'])->allowRelativeLinks()->withMaxInputLength(250000);
        foreach (['p', 'br', 'strong', 'em', 'u', 's', 'h2', 'h3', 'ul', 'ol', 'li', 'blockquote'] as $tag) {
            $config = $config->allowElement($tag);
        }
        $config = $config->allowElement('a', ['href', 'title'])->forceAttribute('a', 'rel', 'noopener noreferrer');
        foreach (['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'template', 'form', 'input', 'button'] as $tag) {
            $config = $config->dropElement($tag);
        }
        $this->sanitizer = new HtmlSanitizer($config);
    }

    public function sanitize(string $value): string
    {
        return $this->sanitizer->sanitize($value);
    }

    public function render(string $value, ?string $format = 'plain'): HtmlString
    {
        return new HtmlString($format === 'html' ? $this->sanitize($value) : nl2br(e($value)));
    }

    public function text(string $value, ?string $format = 'plain'): string
    {
        if ($format !== 'html') {
            return $value;
        }
        $html = $this->sanitize($value);
        $html = preg_replace('~<(br\s*/?|/p|/h[23]|/li|/blockquote)>~i', "\n", $html);
        $html = preg_replace('~<li>~i', '- ', $html);

        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
