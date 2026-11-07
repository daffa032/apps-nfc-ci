<?php

declare(strict_types=1);

use App\Services\Mail\AppMailer;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Email;

/**
 * Email templates (app/Views/emails/): every view renders on the shared layout, escapes data, and
 * gets a readable plain-text part with link targets and without the hidden preheader.
 */
final class AppMailerTest extends CIUnitTestCase
{
    private function mailer(): AppMailer
    {
        $config = new Email();
        $config->fromEmail = 'noreply@example.test';
        $config->SMTPHost = 'smtp.example.test';

        return new AppMailer($config);
    }

    public static function views(): array
    {
        $base = ['name' => 'daffa', 'orderCode' => 'NFC-1', 'plan' => 'Pro · 12 bulan', 'amount' => 'Rp199.000'];

        return [
            'receipt' => ['payment_receipt', $base + ['paidAt' => '27 Okt 2026, 10.00 WIB', 'method' => 'QRIS', 'periodLabel' => '27 Oktober 2027', 'receiptUrl' => 'https://example.test/r/NFC-1']],
            'refund' => ['payment_refund', $base + ['refundedAt' => '27 Okt 2026, 11.00 WIB', 'billingUrl' => 'https://example.test/billing']],
            'reminder' => ['renewal_reminder', ['name' => 'daffa', 'lastDay' => '3 November 2026', 'days' => 7, 'billingUrl' => 'https://example.test/billing']],
            'reset' => ['password_reset', ['resetUrl' => 'https://example.test/reset/abc', 'minutes' => 30]],
        ];
    }

    /**
     * @dataProvider views
     */
    public function testEveryTemplateRendersOnTheSharedLayout(string $view, array $data): void
    {
        $html = $this->mailer()->render($view, 'Subjek', $data);
        self::assertStringContainsString('QR-CODE.ID', $html);
        self::assertStringContainsString('<table role="presentation"', $html);
        self::assertStringNotContainsString('<script', $html);
        self::assertStringNotContainsString('DEBUG-VIEW', $html, 'No toolbar comments (file paths) in emails.');
        foreach ($data as $value) {
            if (is_string($value) && str_starts_with($value, 'https://')) {
                self::assertStringContainsString('href="' . esc($value, 'attr') . '"', $html, $view);
            }
        }

        $text = AppMailer::plainText($html);
        self::assertStringNotContainsString('<', $text);
        self::assertStringContainsString('QR-CODE.ID', $text);
    }

    public function testDataIsEscapedAndPreheaderIsNotInPlainText(): void
    {
        $html = $this->mailer()->render('payment_receipt', 'S', ['name' => '<img src=x onerror=alert(1)>'] + self::views()['receipt'][1]);
        self::assertStringNotContainsString('<img src=x', $html);
        self::assertStringContainsString('&lt;img src=x', $html);

        $text = AppMailer::plainText($html);
        self::assertStringNotContainsString(lang('Email.receiptPreheader', ['NFC-1', 'Rp199.000']), $text);
        self::assertStringContainsString('(https://example.test/r/NFC-1)', $text);
    }

    public function testEmailsFollowTheAccountLanguageAndRestoreTheRequestOne(): void
    {
        $language = \Config\Services::language();
        $language->setLocale('id');
        $data = self::views()['reset'][1];

        $en = AppMailer::inLocale('en', fn (): string => lang('Email.resetSubject') . ' ' . $this->mailer()->render('password_reset', 'S', $data));
        self::assertStringContainsString('Reset your password', $en);
        self::assertStringContainsString('lang="en"', $en);
        self::assertSame('id', $language->getLocale(), 'The request language is restored afterwards.');

        $id = AppMailer::inLocale(null, fn (): string => $this->mailer()->render('password_reset', 'S', $data));
        self::assertStringContainsString('Atur ulang kata sandi', $id, 'No saved choice: Indonesian.');
        self::assertStringContainsString('Atur ulang', AppMailer::inLocale('xx', fn (): string => lang('Email.resetSubject')), 'Unknown codes fall back.');
    }

    public function testNotSentWithoutSmtpOrValidAddress(): void
    {
        // Config\Email reads .env, so blank the two fields explicitly.
        $config = new Email();
        $config->fromEmail = '';
        $config->SMTPHost = '';
        $empty = new AppMailer($config);
        self::assertFalse($empty->isConfigured());
        self::assertFalse($empty->send('a@example.test', 'S', 'password_reset', self::views()['reset'][1]));
        self::assertFalse($this->mailer()->send('bukan-email', 'S', 'password_reset', self::views()['reset'][1]));
    }
}
