<?php

declare(strict_types=1);

namespace App\Filters;

use App\Models\UserModel;
use App\Services\Locale\CountryLookup;
use App\Services\Locale\LocaleResolver;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Sets the request language before the controller runs (PRD LANG-02/03). `?lang=id|en` is an
 * explicit choice: it is remembered in a cookie and the visitor is sent back to the same URL
 * without the parameter, so shared and canonical card URLs never carry a language.
 * Choosing a language is not a domain mutation, so GET is fine here.
 */
class LocaleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! $request instanceof IncomingRequest) {
            return null;
        }
        $config   = config('Locale');
        $resolver = new LocaleResolver($config);

        $choice = $request->getGet('lang');
        if (is_string($choice) && $resolver->supports($choice)) {
            $query = $request->getGet();
            unset($query['lang']);
            // Path relative to baseURL: redirect()->to() prepends baseURL, so the full URI path
            // would double a sub-folder install (/nfc/nfc/dashboard).
            $path   = '/' . ltrim($request->getPath(), '/');
            $target = $path . ($query === [] ? '' : '?' . http_build_query($query));

            // A signed-in account keeps the choice too, so emails sent later (cron) use it.
            $userId = (int) session()->get('id_user');
            if ($userId > 0) {
                (new UserModel())->update($userId, ['preferred_locale' => $choice]);
            }

            return redirect()->to($target)
                ->setCookie($config->cookieName, $choice, 365 * 86400, '', '/', '', null, false, 'Lax');
        }

        $cookie  = $request->getCookie($config->cookieName);
        $choice  = is_string($cookie) && $resolver->supports($cookie) ? $cookie : null;
        $country = null;
        if ($choice === null) {
            // Only first visits need a guess; the IP is used for this lookup and never stored.
            $country = $config->countryHeader !== ''
                ? $request->getHeaderLine($config->countryHeader)
                : (new CountryLookup($config->geoipDatabase))->countryFor($request->getIPAddress());
        }
        $request->setLocale($resolver->resolve($choice, $country, $request->getHeaderLine('Accept-Language')));

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Pages differ by language for the same URL.
        $response->appendHeader('Vary', 'Accept-Language, Cookie');

        return $response;
    }
}
