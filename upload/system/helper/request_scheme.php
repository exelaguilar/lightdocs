<?php
namespace System\Helper;

class RequestScheme
{
    public static function isSecure(object $config, array $server): bool
    {
        if (!empty($server['HTTPS']) && $server['HTTPS'] !== 'off') {
            return true;
        }

        $mode = (string)$config->get('config_trusted_proxy_header', '');

        if ($mode === 'cloudflare' && !empty($server['HTTP_X_FORWARDED_PROTO'])) {
            return strtolower(trim((string)$server['HTTP_X_FORWARDED_PROTO'])) === 'https';
        }

        if ($mode === 'forwarded' && !empty($server['HTTP_X_FORWARDED_PROTO'])) {
            $proto = trim(explode(',', (string)$server['HTTP_X_FORWARDED_PROTO'])[0]);

            return strtolower($proto) === 'https';
        }

        return false;
    }
}
