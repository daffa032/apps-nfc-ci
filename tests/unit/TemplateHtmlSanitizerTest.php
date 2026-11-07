<?php

declare(strict_types=1);

use App\Exceptions\FieldException;
use App\Services\Cards\TemplateCatalog;
use App\Services\Cards\TemplateHtmlSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class TemplateHtmlSanitizerTest extends TestCase
{
    private const VALID = '<section class="tpl-stack"><div class="tpl-hero tpl-center"><div>{{photo}}</div><h2 class="tpl-title">{{name}}</h2><p>{{company}}</p></div>'
        . '<div class="tpl-panel"><h3>Hubungi saya</h3><div>{{contact}}</div></div><div>{{gallery}}</div><div>{{social}}</div><div class="tpl-divider">{{actions}}</div></section>';

    public function testValidTemplateIsRebuiltAndNormalised(): void
    {
        $html = (new TemplateHtmlSanitizer())->normalize("\n  " . str_replace('<h3>', "<!-- note --><h3>\n   ", self::VALID) . "\n");

        $this->assertStringStartsWith('<section class="tpl-stack">', $html);
        $this->assertStringNotContainsString('<!--', $html);
        $this->assertStringContainsString('<h3> Hubungi saya</h3>', $html);
        $this->assertSame($html, (new TemplateHtmlSanitizer())->normalize($html), 'Normalising stored HTML again must not change it.');
    }

    public function testSampleTemplateShippedWithTheAppIsValid(): void
    {
        $html = (new TemplateHtmlSanitizer())->normalize((string) file_get_contents(__DIR__ . '/../../app/Views/digital_card/sample-template.html'));
        $this->assertNotSame('', $html);
    }

    #[DataProvider('provideUnsafeOrIncompleteTemplatesAreRejected')]
    public function testUnsafeOrIncompleteTemplatesAreRejected(string $source): void
    {
        try {
            (new TemplateHtmlSanitizer())->normalize($source);
            $this->fail('Template must be rejected.');
        } catch (FieldException $exception) {
            $this->assertSame('template_file', $exception->field);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideUnsafeOrIncompleteTemplatesAreRejected(): iterable
    {
        yield 'script' => [str_replace('</section>', '<script>alert(1)</script></section>', self::VALID)];

        yield 'script with spaces and case' => [str_replace('</section>', '< ScRiPt >alert(1)</script></section>', self::VALID)];

        yield 'style element' => [str_replace('</section>', '<style>body{}</style></section>', self::VALID)];

        yield 'style attribute' => [str_replace('<section class="tpl-stack">', '<section style="color:red">', self::VALID)];

        yield 'event handler' => [str_replace('<section class="tpl-stack">', '<section onclick="x()">', self::VALID)];

        yield 'event handler without quotes' => [str_replace('<section class="tpl-stack">', '<section onmouseover=x()>', self::VALID)];

        yield 'link' => [str_replace('<h3>Hubungi saya</h3>', '<a href="https://evil.example">x</a>', self::VALID)];

        yield 'image' => [str_replace('<h3>Hubungi saya</h3>', '<img src="x">', self::VALID)];

        yield 'iframe' => [str_replace('</section>', '<iframe src="//x"></iframe></section>', self::VALID)];

        yield 'svg' => [str_replace('</section>', '<svg onload="x"></svg></section>', self::VALID)];

        yield 'form' => [str_replace('</section>', '<form><input></form></section>', self::VALID)];

        yield 'unknown tag' => [str_replace('<h3>', '<marquee>', str_replace('</h3>', '</marquee>', self::VALID))];

        yield 'id attribute' => [str_replace('<section class="tpl-stack">', '<section id="x">', self::VALID)];

        yield 'data attribute' => [str_replace('<section class="tpl-stack">', '<section data-x="1">', self::VALID)];

        yield 'unknown class' => [str_replace('tpl-panel', 'nc-btn', self::VALID)];

        yield 'javascript url in text' => [str_replace('Hubungi saya', 'javascript:alert(1)', self::VALID)];

        yield 'missing placeholder' => [str_replace('{{actions}}', '', self::VALID)];

        yield 'duplicate placeholder' => [str_replace('{{company}}', '{{name}}', self::VALID)];

        yield 'unknown placeholder' => [str_replace('Hubungi saya', '{{password}}', self::VALID)];

        yield 'block placeholder inside p' => [str_replace('<div>{{gallery}}</div>', '<p>{{gallery}}</p>', self::VALID)];

        yield 'stray braces' => [str_replace('Hubungi saya', 'Harga {{ diskon', self::VALID)];

        yield 'too many elements' => [str_replace('</section>', str_repeat('<span>x</span>', 101) . '</section>', self::VALID)];

        yield 'too deep' => [str_replace('<div>{{gallery}}</div>', str_repeat('<div>', 17) . '{{gallery}}' . str_repeat('</div>', 17), self::VALID)];

        yield 'too large' => [self::VALID . str_repeat(' ', TemplateHtmlSanitizer::MAX_BYTES)];

        yield 'invalid utf-8' => [self::VALID . "\xC3\x28"];
    }

    public function testTextIsEscapedNotInterpreted(): void
    {
        $html = (new TemplateHtmlSanitizer())->normalize(str_replace('Hubungi saya', 'Tom &amp; Jerry &lt;b&gt;', self::VALID));

        $this->assertStringContainsString('Tom &amp; Jerry &lt;b&gt;', $html);
        $this->assertStringNotContainsString('<b>', $html);
    }

    public function testSegmentsSplitSafeHtmlAndSlotsInOrder(): void
    {
        $segments = (new TemplateHtmlSanitizer())->segments(self::VALID);
        $slots    = array_values(array_filter(array_map(static fn (array $s): ?string => $s['slot'] ?? null, $segments)));

        $this->assertSame(['photo', 'name', 'company', 'contact', 'gallery', 'social', 'actions'], $slots);
    }

    public function testCatalogRecognisesOnlyWellFormedCustomFiles(): void
    {
        $this->assertSame('digital_card/custom', TemplateCatalog::viewFor('custom-17'));
        $this->assertSame('custom', TemplateCatalog::layoutFor('custom-17'));

        foreach (['custom-', 'custom-0', 'custom-1x', 'custom-../1', 'Custom-1', 'custom-17 '] as $file) {
            $this->assertNull(TemplateCatalog::viewFor($file), $file);
        }
    }

    public function testTheProDesignsFromDocsAreAcceptedAndMatchTheAppCopies(): void
    {
        // The docs sources stay out of the repository, so this drift check is local-only.
        if (! is_dir(ROOTPATH . 'docs/ui-ux-2026/templates/pro')) {
            $this->markTestSkipped('docs/ui-ux-2026/templates/pro is not available in this checkout.');
        }

        $sanitizer = new TemplateHtmlSanitizer();

        foreach (TemplateHtmlSanitizer::PRO_DESIGNS as $design) {
            $docs = (string) file_get_contents(ROOTPATH . 'docs/ui-ux-2026/templates/pro/' . $design . '.html');
            $app  = (string) file_get_contents(APPPATH . 'Views/digital_card/pro/' . $design . '.html');
            $this->assertSame($docs, $app, "app copy of {$design} must equal the docs source");

            $html = $sanitizer->normalize($docs);
            $this->assertSame($design, TemplateHtmlSanitizer::proDesign($html));
            $slots = array_column(array_filter($sanitizer->segments($html), static fn (array $s): bool => isset($s['slot'])), 'slot');

            foreach (TemplateHtmlSanitizer::PLACEHOLDERS as $required) {
                $this->assertContains($required, $slots, $design);
            }
            $this->assertContains('job_title', $slots, $design);
            $this->assertContains('brand_logo', $slots, $design);
            // Rona and Sorot use the profile photo as hero, so they have no header background (NT-07).
            $this->assertSame(! in_array($design, ['rona', 'sorot'], true), in_array('header_background', $slots, true), $design);
        }
    }

    public function testProClassesAreAnExactListNotAPrefix(): void
    {
        $base = '{{photo}}{{name}}{{company}}{{contact}}{{social}}{{gallery}}{{actions}}';

        foreach (['tpl-pro-evil', 'tpl-pro-aurora-x', 'tpl-pro-', 'tpl-proaurora'] as $class) {
            try {
                (new TemplateHtmlSanitizer())->normalize('<div class="' . $class . '">' . $base . '</div>');
                $this->fail($class . ' must be rejected');
            } catch (FieldException $exception) {
                $this->assertStringContainsString('tidak diizinkan', $exception->getMessage());
            }
        }
        $this->assertNull(TemplateHtmlSanitizer::proDesign((new TemplateHtmlSanitizer())->normalize('<div class="tpl-stack">' . $base . '</div>')));
    }

    public function testOptionalPlaceholdersAtMostOnceAndBrandSlotsNeedABlock(): void
    {
        $base      = '<div>{{photo}}{{contact}}{{social}}{{gallery}}{{actions}}</div><h2>{{name}}</h2><p>{{company}}</p>';
        $sanitizer = new TemplateHtmlSanitizer();
        $this->assertStringContainsString('{{job_title}}', $sanitizer->normalize($base . '<p>{{job_title}}</p>'));

        foreach (['<p>{{job_title}}</p><p>{{job_title}}</p>', '<p>{{brand_logo}}</p>', '<div>{{header_background}}</div><div>{{header_background}}</div>'] as $extra) {
            try {
                $sanitizer->normalize($base . $extra);
                $this->fail($extra . ' must be rejected');
            } catch (FieldException) {
            }
        }
    }
}
