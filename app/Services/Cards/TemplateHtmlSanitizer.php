<?php

declare(strict_types=1);

namespace App\Services\Cards;

use App\Exceptions\FieldException;
use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Turns an uploaded card template into safe, normalised HTML (PRD MA-03). Pure: no database,
 * session or request.
 *
 * The file is a declarative layout, never code: only the tags and the listed `tpl-*` classes (including
 * the named `tpl-pro-*` classes of the Pro designs; no prefix wildcard), no other
 * attributes, no script/style/links/forms/media, and text only. Card data and every interactive
 * part (photo, contact buttons, social links, gallery, save/share) are inserted by the app's
 * renderer through placeholders, so a template can arrange the card but cannot change what it does.
 * Each placeholder must appear exactly once so every card feature stays available.
 *
 * The output is rebuilt from the parsed DOM (never the raw source) and is safe to print as HTML.
 * Rendering runs it through normalize() again, so a stored version is re-checked every time.
 */
final class TemplateHtmlSanitizer
{
    public const MAX_BYTES    = 100 * 1024;
    public const MAX_ELEMENTS = 100;
    public const MAX_DEPTH    = 16;
    public const TAGS         = ['section', 'article', 'header', 'div', 'h2', 'h3', 'p', 'span', 'strong', 'em', 'ul', 'li', 'figure', 'figcaption'];
    public const CLASSES      = ['tpl-stack', 'tpl-grid', 'tpl-hero', 'tpl-panel', 'tpl-center', 'tpl-divider', 'tpl-title'];

    /**
     * The Pro designs (PRD NT-01..07, app/Views/digital_card/pro). Listed one by one, no prefix match.
     */
    public const PRO_DESIGNS = ['aurora', 'meridian', 'atelier', 'vista', 'ledger', 'kanvas', 'halo', 'rona', 'sorot', 'ombak', 'siluet', 'bingkai'];

    /**
     * Structure classes the Pro designs use, styled by assets/cards/css/pro.css.
     */
    public const PRO_CLASSES = ['tpl-pro-cover', 'tpl-pro-brand', 'tpl-pro-photo', 'tpl-pro-identity', 'tpl-pro-kicker', 'tpl-pro-content', 'tpl-pro-contact', 'tpl-pro-social', 'tpl-pro-gallery', 'tpl-pro-actions'];

    /**
     * Every template must contain each of these once, so all card features stay available.
     */
    public const PLACEHOLDERS = ['photo', 'name', 'company', 'contact', 'social', 'gallery', 'actions'];

    /**
     * May appear at most once (PRD BR-01/02): job title, brand logo, header background.
     */
    public const OPTIONAL_PLACEHOLDERS = ['job_title', 'brand_logo', 'header_background'];

    /**
     * Placeholders that render block content and so need a block container.
     */
    private const BLOCK_PLACEHOLDERS = ['photo', 'contact', 'social', 'gallery', 'actions', 'brand_logo', 'header_background'];

    private const BLOCK_PARENTS = ['section', 'article', 'header', 'div', 'figure', 'li'];
    private const FORBIDDEN     = '/<\s*\/?\s*(script|style|iframe|frame|object|embed|svg|math|link|meta|base|form|input|button|textarea|select|img|video|audio|source|template|noscript|a)\b/i';

    private int $elements = 0;

    /**
     * @var array<string, int>
     */
    private array $seen = [];

    /**
     * @throws FieldException with field `template_file`
     */
    public function normalize(string $source): string
    {
        if (strlen($source) > self::MAX_BYTES || ! mb_check_encoding($source, 'UTF-8')) {
            throw $this->fail(lang('Master.tplFileRules'));
        }
        if (preg_match(self::FORBIDDEN, $source, $match)) {
            throw $this->fail(lang('Master.tplTagNotAllowed', [strtolower($match[1])]));
        }
        if (preg_match('/\son[a-z]+\s*=|\sstyle\s*=|javascript:|expression\s*\(/i', $source)) {
            throw $this->fail(lang('Master.tplNoScript'));
        }

        $doc      = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $loaded   = $doc->loadHTML('<?xml encoding="UTF-8"?><!DOCTYPE html><html><body>' . $source . '</body></html>', LIBXML_NONET | LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $body = $loaded ? $doc->getElementsByTagName('body')->item(0) : null;
        if (! $body instanceof DOMElement) {
            throw $this->fail(lang('Master.tplUnreadable'));
        }

        $this->elements = 0;
        $this->seen     = [];
        $html           = '';

        foreach ($body->childNodes as $child) {
            $html .= $this->serialize($child, 0, 'div');
        }

        foreach (self::OPTIONAL_PLACEHOLDERS as $key) {
            if (($this->seen[$key] ?? 0) > 1) {
                throw $this->fail(lang('Master.tplPlaceholderOnce', ['{{' . $key . '}}']));
            }
        }

        foreach (self::PLACEHOLDERS as $key) {
            if (($this->seen[$key] ?? 0) !== 1) {
                throw $this->fail(lang(($this->seen[$key] ?? 0) === 0 ? 'Master.tplPlaceholderRequired' : 'Master.tplPlaceholderOnce', ['{{' . $key . '}}']));
            }
        }

        return trim($html);
    }

    /**
     * Normalised HTML split into safe HTML chunks and placeholder slots, for the card renderer.
     *
     * @return list<array{html: string}|array{slot: string}>
     */
    public function segments(string $storedHtml): array
    {
        $parts    = preg_split('/\{\{(' . implode('|', self::allPlaceholders()) . ')\}\}/', $this->normalize($storedHtml), -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $segments = [];

        foreach ($parts as $index => $part) {
            if ($index % 2 === 1) {
                $segments[] = ['slot' => $part];
            } elseif ($part !== '') {
                $segments[] = ['html' => $part];
            }
        }

        return $segments;
    }

    /**
     * Class names a template may use.
     *
     * @return list<string>
     */
    public static function allowedClasses(): array
    {
        $designs = [];

        foreach (self::PRO_DESIGNS as $design) {
            $designs[] = 'tpl-pro-' . $design;
            $designs[] = 'tpl-pro-' . $design . '-hero';
        }

        return [...self::CLASSES, ...$designs, ...self::PRO_CLASSES];
    }

    /**
     * @return list<string>
     */
    public static function allPlaceholders(): array
    {
        return [...self::PLACEHOLDERS, ...self::OPTIONAL_PLACEHOLDERS];
    }

    /**
     * The Pro design a normalised template is built on ("aurora" …), from its tpl-pro-<design>
     * class, or null for other layouts.
     */
    public static function proDesign(string $normalizedHtml): ?string
    {
        return preg_match('/class="(?:[^"]* )?tpl-pro-(' . implode('|', self::PRO_DESIGNS) . ')(?:[ "])/', $normalizedHtml, $match) === 1 ? $match[1] : null;
    }

    private function serialize(DOMNode $node, int $depth, string $parentTag): string
    {
        if ($node->nodeType === XML_COMMENT_NODE) {
            return '';
        }
        if ($node->nodeType === XML_TEXT_NODE) {
            return $this->text((string) $node->nodeValue, $parentTag);
        }
        if (! $node instanceof DOMElement) {
            throw $this->fail(lang('Master.tplOnlyHtml'));
        }

        $tag = strtolower($node->tagName);
        if (! in_array($tag, self::TAGS, true)) {
            throw $this->fail(lang('Master.tplTagNotAllowed', [$tag]));
        }
        if (++$this->elements > self::MAX_ELEMENTS || $depth >= self::MAX_DEPTH) {
            throw $this->fail(lang('Master.tplTooLarge', [self::MAX_ELEMENTS]));
        }

        $classes = [];

        foreach ($node->attributes as $attribute) {
            if (strtolower($attribute->name) !== 'class') {
                throw $this->fail(lang('Master.tplAttributeNotAllowed', [$attribute->name]));
            }

            foreach (preg_split('/\s+/', trim($attribute->value)) ?: [] as $class) {
                if ($class === '') {
                    continue;
                }
                if (! in_array($class, self::allowedClasses(), true)) {
                    throw $this->fail(lang('Master.tplClassNotAllowed', [mb_substr($class, 0, 40), implode(', ', self::CLASSES)]));
                }
                $classes[$class] = true;
            }
        }

        $inner = '';

        foreach ($node->childNodes as $child) {
            $inner .= $this->serialize($child, $depth + 1, $tag);
        }

        return '<' . $tag . ($classes === [] ? '' : ' class="' . implode(' ', array_keys($classes)) . '"') . '>' . $inner . '</' . $tag . '>';
    }

    private function text(string $text, string $parentTag): string
    {
        $out    = '';
        $offset = 0;
        preg_match_all('/\{\{\s*([^{}]*?)\s*\}\}/u', $text, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

        foreach ($matches as [$whole, $key]) {
            $name = $key[0];
            if (! in_array($name, self::allPlaceholders(), true)) {
                throw $this->fail(lang('Master.tplPlaceholderUnknown', ['{{' . mb_substr($name, 0, 30) . '}}', implode(', ', self::allPlaceholders())]));
            }
            if (in_array($name, self::BLOCK_PLACEHOLDERS, true) && ! in_array($parentTag, self::BLOCK_PARENTS, true)) {
                throw $this->fail(lang('Master.tplPlaceholderBlock', ['{{' . $name . '}}', implode('/', self::BLOCK_PARENTS)]));
            }
            $this->seen[$name] = ($this->seen[$name] ?? 0) + 1;
            $out .= $this->escape(substr($text, $offset, $whole[1] - $offset)) . '{{' . $name . '}}';
            $offset = $whole[1] + strlen($whole[0]);
        }
        $rest = substr($text, $offset);
        if (str_contains($rest, '{{') || str_contains($rest, '}}')) {
            throw $this->fail(lang('Master.tplBracesOnlyPlaceholders'));
        }

        return $out . $this->escape($rest);
    }

    private function escape(string $text): string
    {
        // Collapse layout whitespace so the stored version does not depend on indentation.
        return htmlspecialchars((string) preg_replace('/\s+/u', ' ', $text), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }

    private function fail(string $message): FieldException
    {
        return new FieldException('template_file', $message);
    }
}
