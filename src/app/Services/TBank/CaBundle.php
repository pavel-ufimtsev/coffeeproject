<?php

namespace App\Services\TBank;

/**
 * T-Bank's servers present a certificate from the Russian Trusted Root CA
 * (Ministry of Digital Development), which the usual CA bundles leave out.
 * This is the system bundle plus that one root, for T-Bank requests only:
 * the rest of the app keeps trusting just the system roots.
 *
 * The root is resources/certs/russian-trusted-root-ca.pem, from
 * https://gu-st.ru/content/lending/russian_trusted_root_ca_pem.crt
 * (SHA-256 D2:6D:2D:02:31:B7:C3:9F:92:CC:73:85:12:BA:54:10:35:19:E4:40:5D:68:B5:BD:70:3E:97:88:CA:8E:CF:31).
 */
class CaBundle
{
    public static function path(): string
    {
        $root = resource_path('certs/russian-trusted-root-ca.pem');
        $system = self::systemBundle();

        if ($system === null) {
            return $root;
        }

        $bundle = storage_path('framework/cache/tbank-ca-bundle.pem');

        if (! is_file($bundle) || filemtime($bundle) < max(filemtime($system), filemtime($root))) {
            $tmp = $bundle.'.'.getmypid();
            file_put_contents($tmp, file_get_contents($system)."\n".file_get_contents($root));
            rename($tmp, $bundle);
        }

        return $bundle;
    }

    private static function systemBundle(): ?string
    {
        $candidates = [
            ini_get('curl.cainfo'),
            ini_get('openssl.cafile'),
            openssl_get_cert_locations()['default_cert_file'] ?? null,
            '/etc/ssl/certs/ca-certificates.crt',
            '/etc/pki/tls/certs/ca-bundle.crt',
        ];

        foreach ($candidates as $path) {
            if (is_string($path) && $path !== '' && is_readable($path)) {
                return $path;
            }
        }

        return null;
    }
}
