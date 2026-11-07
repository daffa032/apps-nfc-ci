<?php

declare(strict_types=1);

namespace App\Services\Cards;

use Config\Services;

/**
 * Allowlist of card layouts. A template row in the database can only point to one of these files,
 * or to an uploaded HTML version ("custom-<id>"); the file name never becomes a view path on its
 * own (LOG-06).
 */
final class TemplateCatalog
{
    private const VIEWS = [
        // The two original cards keep their stored file names (accounts already use them) and render
        // through the shared structure (digital_card/classic).
        'index-white.php'    => ['digital_card/classic', 'classic-white'],
        'index-dark.php'     => ['digital_card/classic', 'classic-dark'],
        'layout-profesional' => ['digital_card/layout', 'profesional'],
        'layout-hangat'      => ['digital_card/layout', 'hangat'],
        'layout-portofolio'  => ['digital_card/layout', 'portofolio'],
        'layout-gelap'       => ['digital_card/layout', 'gelap'],
        // Pro designs (PRD NT-01..07), rendered by digital_card/pro.
        'layout-pro-aurora'   => ['digital_card/pro', 'pro-aurora'],
        'layout-pro-meridian' => ['digital_card/pro', 'pro-meridian'],
        'layout-pro-atelier'  => ['digital_card/pro', 'pro-atelier'],
        'layout-pro-vista'    => ['digital_card/pro', 'pro-vista'],
        'layout-pro-ledger'   => ['digital_card/pro', 'pro-ledger'],
        'layout-pro-kanvas'   => ['digital_card/pro', 'pro-kanvas'],
        'layout-pro-halo'     => ['digital_card/pro', 'pro-halo'],
        'layout-pro-rona'     => ['digital_card/pro', 'pro-rona'],
        'layout-pro-sorot'    => ['digital_card/pro', 'pro-sorot'],
        'layout-pro-ombak'    => ['digital_card/pro', 'pro-ombak'],
        'layout-pro-siluet'   => ['digital_card/pro', 'pro-siluet'],
        'layout-pro-bingkai'  => ['digital_card/pro', 'pro-bingkai'],
    ];

    /**
     * Fallback slot limits (PRD MA-02) when the template_slot_limits row is missing. The live values
     * are edited by a Super Admin in Master Templates (TemplateRepository::slotLimits()).
     */
    public const DEFAULT_MAX_ACTIVE_BASIC = 4;

    public const DEFAULT_MAX_ACTIVE_TOTAL = 20;

    /**
     * Upper bound an admin may set, so a typo cannot open an unlimited catalog.
     */
    public const MAX_SLOT_LIMIT = 99;

    /**
     * Uploaded HTML templates use nama_file "custom-<id_template>" and render digital_card/custom.
     */
    public const CUSTOM_PREFIX = 'custom-';

    /**
     * Pro designs with a header background slot; Rona and Sorot use the profile photo instead (NT-07).
     */
    private const HEADER_BACKGROUND = ['pro-aurora', 'pro-meridian', 'pro-atelier', 'pro-vista', 'pro-ledger', 'pro-kanvas', 'pro-halo', 'pro-ombak', 'pro-siluet', 'pro-bingkai'];

    /**
     * Shape of the logo frame per Pro design: round designs get a circle, the others a 2:1 box with
     * rounded or sharp corners. pro.css draws the same frame on the card and the Personal Info cropper
     * crops to it, so an uploaded logo always fits its container (PRD BR-01).
     */
    private const LOGO_FRAMES = [
        'aurora'   => 'circle', 'vista' => 'circle', 'halo' => 'circle', 'rona' => 'circle', 'ombak' => 'circle', 'siluet' => 'circle',
        'meridian' => 'rounded', 'kanvas' => 'rounded', 'sorot' => 'rounded', 'bingkai' => 'rounded',
        'atelier'  => 'square', 'ledger' => 'square',
    ];

    /**
     * Logo frame of a Pro design ("aurora" …); other or unknown designs get the rounded 2:1 box.
     *
     * @return array{shape: 'circle'|'rounded'|'square', ratio: int}
     */
    public static function logoFrame(?string $design): array
    {
        $shape = self::LOGO_FRAMES[$design ?? ''] ?? 'rounded';

        return ['shape' => $shape, 'ratio' => $shape === 'circle' ? 1 : 2];
    }

    /**
     * Brand slots a layout offers (PRD BR-01): every Pro design has a logo; all but Rona and Sorot
     * also have a header background. Other layouts have none.
     *
     * @return list<string> 'brand_logo' and/or 'header_background'
     */
    public static function brandSlots(?string $file): array
    {
        $layout = self::layoutFor($file);
        if ($layout === null || ! str_starts_with($layout, 'pro-')) {
            return [];
        }

        return in_array($layout, self::HEADER_BACKGROUND, true) ? ['brand_logo', 'header_background'] : ['brand_logo'];
    }

    /**
     * Source HTML of a built-in Pro design (app/Views/digital_card/pro/<design>.html, copied from
     * docs/ui-ux-2026/templates/pro), or null for other layouts. It goes through the same sanitizer
     * as uploaded templates on every render.
     */
    public static function proSourcePath(?string $file): ?string
    {
        $layout = self::layoutFor($file);
        if ($layout === null || ! str_starts_with($layout, 'pro-') || ! isset(self::VIEWS[$file ?? ''])) {
            return null;
        }

        return APPPATH . 'Views/digital_card/pro/' . substr($layout, 4) . '.html';
    }

    public static function viewFor(?string $file): ?string
    {
        if (self::isCustom($file)) {
            return 'digital_card/custom';
        }

        return self::VIEWS[$file ?? ''][0] ?? null;
    }

    /**
     * Description of a template in the current language: built-in layouts have translated texts in
     * app/Language/{id,en}/Templates.php; an uploaded template shows what the admin typed.
     */
    public static function description(array $template): string
    {
        $file = (string) ($template['nama_file'] ?? '');
        $key  = match ($file) {
            'index-white.php' => 'Templates.descWhite',
            'index-dark.php'  => 'Templates.descDark',
            default           => self::isCustom($file) || ! isset(self::VIEWS[$file]) ? null : 'Templates.desc_' . str_replace('-', '_', (string) self::layoutFor($file)),
        };
        if ($key !== null) {
            $text = lang($key);
            if ($text !== $key) {
                return $text;
            }
        }

        return trim((string) ($template['description'] ?? ''));
    }

    /**
     * Catalog thumbnail of a template in the current language: "<name>-en.png" next to the stored
     * image when it exists for English, else the stored image (or null for a CSS preview).
     */
    public static function previewImage(array $template): ?string
    {
        $name = CardPresentation::imageName($template['images_template'] ?? null);
        if ($name === null) {
            return null;
        }
        $locale = Services::language()->getLocale();
        if ($locale !== 'id') {
            $localized = preg_replace('/(\.[a-z0-9]+)$/i', '-' . $locale . '$1', $name);
            if (is_string($localized) && is_file(ROOTPATH . 'public/uploads/templates/' . $localized)) {
                return $localized;
            }
        }

        return $name;
    }

    public static function isCustom(?string $file): bool
    {
        return preg_match('/\A' . self::CUSTOM_PREFIX . '[1-9][0-9]{0,9}\z/', (string) $file) === 1;
    }

    /**
     * Layout name for the card view (body class nc-<layout>): classic-white/-dark, a layout.php
     * variant, pro-<design>, or custom.
     */
    public static function layoutFor(?string $file): ?string
    {
        return self::isCustom($file) ? 'custom' : (self::VIEWS[$file ?? ''][1] ?? null);
    }

    public static function availableRows(array $rows): array
    {
        return array_values(array_filter($rows, static fn (array $row): bool => self::viewFor($row['nama_file'] ?? null) !== null));
    }
}
