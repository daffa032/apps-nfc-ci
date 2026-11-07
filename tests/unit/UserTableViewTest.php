<?php

declare(strict_types=1);

use Config\Services;
use PHPUnit\Framework\TestCase;

final class UserTableViewTest extends TestCase
{
    public function testCardLinksMatchEachUsernameForBothAdminRolesAndLanguages(): void
    {
        helper(['asset', 'feedback', 'date']);
        $language = Services::language();
        $before = $language->getLocale();
        try {
            foreach (['id', 'en'] as $locale) {
                $language->setLocale($locale);
                foreach (['Admin', 'Super Admin'] as $role) {
                    $renderer = Services::renderer(null, null, false);
                    $renderer->setData([
                        'username' => 'staff', 'encryptedId' => 'MQ', 'accessLevel' => $role, 'currentUserId' => 1,
                        'locale' => $locale, 'currentPath' => 'dashboard/user_table', 'cardActive' => true,
                        'feedback' => null, 'nowUtc' => '2026-11-02 00:00:00',
                        'users' => array_map(static fn (string $name, int $id): array => [
                            'username' => $name, 'id_user' => $id, 'email' => 'sample@example.test',
                            'registration_date' => '2026-01-01', 'ends_at' => null,
                        ], ['first.user', 'second-user', '<img src=x onerror=alert(1)>'], [7, 8, 9]),
                    ]);
                    $html = $renderer->render('dashboard/pages/user_table/user_table', ['debug' => false]);
                    $doc = new DOMDocument();
                    $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
                    $xpath = new DOMXPath($doc);
                    self::assertSame(1, $xpath->query('//th[text()="' . lang('Accounts.cardLink') . '"]')->length);
                    foreach (['first.user', 'second-user', '<img src=x onerror=alert(1)>'] as $username) {
                        $url = base_url(rawurlencode($username));
                        $links = $xpath->query('//table[@id="datatablesSimple"]//a[@target="_blank" and @href="' . $url . '"]');
                        self::assertSame(1, $links->length);
                        self::assertSame('noopener noreferrer', $links->item(0)->getAttribute('rel'));
                        self::assertSame($url, $links->item(0)->textContent);
                    }
                    self::assertSame(0, $xpath->query('//table//img')->length);
                }
            }
        } finally {
            $language->setLocale($before);
        }
    }
}
