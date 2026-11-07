<?php

declare(strict_types=1);

namespace App\Traits;

use App\Services\Cards\PublicCardService;
use Config\Services;

/** Keeps public cards and owner previews in the card's language, not the dashboard's. */
trait RendersCardPages
{
    protected function renderCardPage(string $view, array $data): string
    {
        $locale = PublicCardService::cardLocale(['card_locale' => $data['cardLocale'] ?? null]);
        $language = Services::language();
        $previousLanguage = $language->getLocale();
        $previousRequestLocale = $this->request->getLocale();

        $this->request->setLocale($locale);
        $language->setLocale($locale);
        try {
            return view($view, array_replace($data, ['locale' => $locale, 'langSwitch' => false]));
        } finally {
            $this->request->setLocale($previousRequestLocale);
            $language->setLocale($previousLanguage);
        }
    }
}
