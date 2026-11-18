<?php

namespace App\Support;

class CaBundle
{
    /**
     * Whether PHP is configured with a system CA bundle (php.ini).
     */
    public static function isSystemConfigured(): bool
    {
        $curl = ini_get('curl.cainfo');
        $openssl = ini_get('openssl.cafile');

        return (is_string($curl) && $curl !== '')
            || (is_string($openssl) && $openssl !== '');
    }

    /**
     * Path to a readable CA bundle file, or null if none is available.
     */
    public static function path(): ?string
    {
        $configured = config('ssl.ca_bundle');
        if (is_string($configured) && $configured !== '' && is_readable($configured)) {
            return $configured;
        }

        $default = base_path('bootstrap/certs/cacert.pem');

        return is_readable($default) ? $default : null;
    }

    /**
     * Guzzle / Laravel HTTP "verify" option when the system CA is not configured.
     */
    public static function httpVerifyOption(): bool|string
    {
        if (static::isSystemConfigured()) {
            return true;
        }

        return static::path() ?? true;
    }
}
