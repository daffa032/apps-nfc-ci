<?php

declare(strict_types=1);

namespace App\Services\Mail;

use Config\Email;
use Config\Services;

/**
 * Sends the app's emails (receipt, refund, reminder, password reset) with one look: an HTML view
 * from app/Views/emails/ on the shared layout, plus a plain-text part made from the same view for
 * clients that do not show HTML. Uses the SMTP settings in .env (email.*). Returns false instead of
 * throwing, so a mail failure never undoes the action that triggered it.
 */
class AppMailer
{
    public function __construct(private ?Email $config = null)
    {
        $this->config ??= config(Email::class);
    }

    public function isConfigured(): bool
    {
        return $this->config->fromEmail !== '' && $this->config->SMTPHost !== '';
    }

    /**
     * @param array<string, mixed> $data view data for app/Views/emails/{$view}.php
     */
    public function send(string $to, string $subject, string $view, array $data = []): bool
    {
        if (! $this->isConfigured() || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }
        helper('date');
        $html = $this->render($view, $subject, $data);

        // One retry: a brief DNS or network hiccup (e.g. "getaddrinfo failed") must not cancel a
        // password reset or lose a receipt. Each attempt uses a fresh mailer and connection.
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $mailer = Services::email(null, false);
            $mailer->setMailType('html');
            $mailer->setFrom($this->config->fromEmail, $this->config->fromName);
            $mailer->setTo($to);
            $mailer->setSubject($subject);
            $mailer->setMessage($html);
            $mailer->setAltMessage(self::plainText($html));
            if ($mailer->send(false)) {
                return true;
            }
            if ($attempt === 1) {
                usleep(1500000);
            }
        }
        log_message('warning', 'Email "{view}" could not be sent after a retry.', ['view' => $view]);

        return false;
    }

    /**
     * Runs $build (which calls lang(), render() and send()) in the account's language, then restores
     * the previous one. Cron has no browser request, so the saved account choice decides.
     *
     * @template T
     *
     * @param callable(): T $build
     *
     * @return T
     */
    public static function inLocale(?string $locale, callable $build): mixed
    {
        $language = Services::language();
        $previous = $language->getLocale();
        $locale   = in_array($locale, config('Locale')->supported, true) ? $locale : config('Locale')->fallback;
        $language->setLocale($locale);

        try {
            return $build();
        } finally {
            $language->setLocale($previous);
        }
    }

    /**
     * Rendered HTML of an email, for sending and for previews.
     *
     * @param array<string, mixed> $data
     */
    public function render(string $view, string $subject, array $data = []): string
    {
        // Own renderer per email: the shared one keeps data from earlier views.
        $renderer = Services::renderer(null, null, false);
        $renderer->setData($data + ['subject' => $subject, 'locale' => Services::language()->getLocale(), 'year' => (int) date('Y')]);
        $html = $renderer->render('emails/' . $view, ['debug' => false]);

        // In development the toolbar wraps included partials in "DEBUG-VIEW" comments with file
        // paths; they must never reach an inbox.
        return preg_replace('#<!-- DEBUG-VIEW (START|ENDED) [^>]*-->\R?#', '', $html) ?? $html;
    }

    /**
     * Plain-text alternative: visible text and link targets, no hidden preheader.
     */
    public static function plainText(string $html): string
    {
        $html = preg_replace('#<head>.*?</head>|<div style="display:none[^>]*>.*?</div>#s', '', $html) ?? $html;
        // Each table row on one line ("Label: value"), each paragraph on its own line.
        $html = preg_replace('#</td>\s*<td[^>]*>#', ': ', $html) ?? $html;
        $html = preg_replace_callback('#<a [^>]*href="([^"]+)"[^>]*>(.*?)</a>#s', static function (array $m): string {
            $href  = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $label = html_entity_decode(strip_tags($m[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8');

            // A link whose text is already the URL (button fallback) is printed once.
            return $label === $href ? $href : $label . ' (' . $href . ')';
        }, $html) ?? $html;
        $html = preg_replace('#</(p|tr|h1|table)>#i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\n\s*\n\s*\n+/', "\n\n", $text) ?? $text;

        return trim(implode("\n", array_map('trim', explode("\n", $text))));
    }
}
