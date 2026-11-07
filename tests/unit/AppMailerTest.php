<?php

declare(strict_types=1);

use App\Services\Mail\AppMailer;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Email;
use Config\Services;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Email templates (app/Views/emails/): every view renders on the shared layout, escapes data, and
 * gets a readable plain-text part with link targets and without the hidden preheader.
 *
 * @internal
 */
final class AppMailerTest extends CIUnitTestCase
{
    private function mailer(): AppMailer
    {
        $config            = new Email();
        $config->fromEmail = 'noreply@example.test';
        $config->SMTPHost  = 'smtp.example.test';

        return new AppMailer($config);
    }

    #[DataProvider('provideEveryTemplateRendersOnTheSharedLayout')]
    public function testEveryTemplateRendersOnTheSharedLayout(string $view, array $data): void
    {
        $html = $this->mailer()->render($view, 'Subjek', $data);
        $this->assertStringContainsString('QR-CODE.ID', $html);
        $this->assertStringContainsString('<table role="presentation"', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('DEBUG-VIEW', $html, 'No toolbar comments (file paths) in emails.');

        foreach ($data as $value) {
            if (is_string($value) && str_starts_with($value, 'https://')) {
                $this->assertStringContainsString('href="' . esc($value, 'attr') . '"', $html, $view);
            }
        }

        $text = AppMailer::plainText($html);
        $this->assertStringNotContainsString('<', $text);
        $this->assertStringContainsString('QR-CODE.ID', $text);
    }

    public static function provideEveryTemplateRendersOnTheSharedLayout(): iterable
    {
        $base = ['name' => 'daffa', 'orderCode' => 'NFC-1', 'plan' => 'Pro · 12 bulan', 'amount' => 'Rp199.000'];

        return [
            'receipt'  => ['payment_receipt', $base + ['paidAt' => '27 Okt 2026, 10.00 WIB', 'method' => 'QRIS', 'periodLabel' => '27 Oktober 2027', 'receiptUrl' => 'https://example.test/r/NFC-1']],
            'refund'   => ['payment_refund', $base + ['refundedAt' => '27 Okt 2026, 11.00 WIB', 'billingUrl' => 'https://example.test/billing']],
            'reminder' => ['renewal_reminder', ['name' => 'daffa', 'lastDay' => '3 November 2026', 'days' => 7, 'billingUrl' => 'https://example.test/billing']],
            'reset'    => ['password_reset', ['resetUrl' => 'https://example.test/reset/abc', 'minutes' => 30]],
        ];
    }

    public function testDataIsEscapedAndPreheaderIsNotInPlainText(): void
    {
        $html = $this->mailer()->render('payment_receipt', 'S', ['name' => '<img src=x onerror=alert(1)>'] + self::provideEveryTemplateRendersOnTheSharedLayout()['receipt'][1]);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringContainsString('&lt;img src=x', $html);

        $text = AppMailer::plainText($html);
        $this->assertStringNotContainsString(lang('Email.receiptPreheader', ['NFC-1', 'Rp199.000']), $text);
        $this->assertStringContainsString('(https://example.test/r/NFC-1)', $text);
    }

    public function testEmailsFollowTheAccountLanguageAndRestoreTheRequestOne(): void
    {
        $language = Services::language();
        $language->setLocale('id');
        $data = self::provideEveryTemplateRendersOnTheSharedLayout()['reset'][1];

        $en = AppMailer::inLocale('en', fn (): string => lang('Email.resetSubject') . ' ' . $this->mailer()->render('password_reset', 'S', $data));
        $this->assertStringContainsString('Reset your password', $en);
        $this->assertStringContainsString('lang="en"', $en);
        $this->assertSame('id', $language->getLocale(), 'The request language is restored afterwards.');

        $id = AppMailer::inLocale(null, fn (): string => $this->mailer()->render('password_reset', 'S', $data));
        $this->assertStringContainsString('Atur ulang kata sandi', $id, 'No saved choice: Indonesian.');
        $this->assertStringContainsString('Atur ulang', AppMailer::inLocale('xx', static fn (): string => lang('Email.resetSubject')), 'Unknown codes fall back.');
    }

    public function testNotSentWithoutSmtpOrValidAddress(): void
    {
        // Config\Email reads .env, so blank the two fields explicitly.
        $config            = new Email();
        $config->fromEmail = '';
        $config->SMTPHost  = '';
        $empty             = new AppMailer($config);
        $this->assertFalse($empty->isConfigured());
        $this->assertFalse($empty->send('a@example.test', 'S', 'password_reset', self::provideEveryTemplateRendersOnTheSharedLayout()['reset'][1]));
        $this->assertFalse($this->mailer()->send('bukan-email', 'S', 'password_reset', self::provideEveryTemplateRendersOnTheSharedLayout()['reset'][1]));
    }
}
