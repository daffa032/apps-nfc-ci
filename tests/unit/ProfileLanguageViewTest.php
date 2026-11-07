<?php

declare(strict_types=1);

use Config\Services;
use PHPUnit\Framework\TestCase;

final class ProfileLanguageViewTest extends TestCase
{
    public function testCardLanguageHasItsOwnCsrfFormAndDoesNotFollowNavbarLanguage(): void
    {
        helper(['asset', 'feedback']);
        $language = Services::language();
        $before = $language->getLocale();
        try {
            foreach ([['en', null, 'id'], ['id', 'en', 'en'], ['en', 'id', 'id']] as [$dashboardLocale, $cardLocale, $selected]) {
                $language->setLocale($dashboardLocale);
                $renderer = Services::renderer(null, null, false);
                $renderer->setData([
                    'username' => 'sample', 'accessLevel' => 'User', 'encryptedId' => 'Nw',
                    'locale' => $dashboardLocale, 'currentPath' => 'dashboard/personal_info_page',
                    'cardActive' => true, 'feedback' => null, 'fieldErrors' => [],
                    'userPersonal' => ['nama' => 'Nama Contoh', 'card_locale' => $cardLocale],
                    'brand' => ['slots' => [], 'templateName' => 'Klasik'],
                    'socialMediaLinks' => [], 'images' => [],
                ]);
                $html = $renderer->render('dashboard/pages/personal_info/personal_info_page', ['debug' => false]);
                $doc = new DOMDocument();
                $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
                $xpath = new DOMXPath($doc);
                $forms = $xpath->query('//select[@name="card_locale"]/ancestor::form');
                self::assertSame(1, $forms->length);
                $form = $forms->item(0);
                self::assertSame('post', $form->getAttribute('method'));
                self::assertSame(base_url('dashboard/personal_info_page/language'), $form->getAttribute('action'));
                self::assertSame(1, $xpath->query('.//input[@name="' . csrf_token() . '"]', $form)->length);
                self::assertSame($selected, $xpath->query('.//option[@selected]', $form)->item(0)->getAttribute('value'));
                self::assertSame(0, $xpath->query('//form[@id="userForm"]//select[@name="card_locale"]')->length);
                self::assertSame($dashboardLocale, $doc->getElementsByTagName('html')->item(0)->getAttribute('lang'));
            }
        } finally {
            $language->setLocale($before);
        }
    }
}
