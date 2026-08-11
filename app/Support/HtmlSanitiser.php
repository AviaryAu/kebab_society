<?php

declare(strict_types=1);

namespace App\Support;

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * An allow-list sanitiser for editor HTML.
 *
 * The rich text editor is admin-only, but admin-authored HTML is still stored
 * and re-rendered as markup, so it is treated as untrusted input. Anything not
 * on the list below is unwrapped (children kept) or dropped.
 */
final class HtmlSanitiser
{
    /** @var array<string, list<string>> tag => allowed attributes */
    private const ALLOWED = [
        'p' => [],
        'br' => [],
        'strong' => [],
        'b' => [],
        'em' => [],
        'i' => [],
        'u' => [],
        's' => [],
        'h2' => [],
        'h3' => [],
        'h4' => [],
        'ul' => [],
        'ol' => ['start'],
        'li' => [],
        'blockquote' => [],
        'hr' => [],
        'code' => [],
        'pre' => [],
        'figure' => [],
        'figcaption' => [],
        'a' => ['href', 'target', 'rel'],
        'img' => ['src', 'alt', 'title'],
    ];

    private const ALLOWED_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    public static function clean(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $html = trim($html);

        if ($html === '' || strip_tags($html) === '' && ! str_contains($html, '<img')) {
            return null;
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        $document->loadHTML(
            '<?xml encoding="UTF-8"><body><div id="ks-root">'.$html.'</div></body>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('ks-root');

        if (! $root instanceof DOMElement) {
            return null;
        }

        self::cleanChildren($root);

        $output = '';

        foreach (iterator_to_array($root->childNodes) as $child) {
            $output .= $document->saveHTML($child);
        }

        $output = trim($output);

        return $output === '' ? null : $output;
    }

    private static function cleanChildren(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                self::cleanElement($child);

                continue;
            }

            // Keep text; drop comments, processing instructions and the rest.
            if ($child->nodeType !== XML_TEXT_NODE) {
                $child->parentNode?->removeChild($child);
            }
        }
    }

    private static function cleanElement(DOMElement $element): void
    {
        $tag = strtolower($element->nodeName);

        if (! array_key_exists($tag, self::ALLOWED)) {
            self::cleanChildren($element);
            self::unwrap($element);

            return;
        }

        foreach (iterator_to_array($element->attributes ?? []) as $attribute) {
            /** @var DOMAttr $attribute */
            $name = strtolower($attribute->nodeName);

            if (! in_array($name, self::ALLOWED[$tag], true)) {
                $element->removeAttribute($attribute->nodeName);

                continue;
            }

            if (in_array($name, ['href', 'src'], true) && ! self::isSafeUrl($attribute->nodeValue)) {
                $element->removeAttribute($attribute->nodeName);
            }
        }

        if ($tag === 'a' && $element->getAttribute('target') === '_blank') {
            $element->setAttribute('rel', 'noopener noreferrer');
        }

        self::cleanChildren($element);
    }

    private static function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;

        if ($parent === null) {
            return;
        }

        foreach (iterator_to_array($element->childNodes) as $child) {
            $parent->insertBefore($child, $element);
        }

        $parent->removeChild($element);
    }

    private static function isSafeUrl(?string $url): bool
    {
        $url = trim((string) $url);

        if ($url === '') {
            return false;
        }

        // Relative and root-relative links stay inside the site.
        if (str_starts_with($url, '/') || str_starts_with($url, '#')) {
            return true;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, self::ALLOWED_SCHEMES, true);
    }
}
