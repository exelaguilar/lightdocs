<?php
namespace System\Helper;

class ClientIp
{
    public static function resolve(object $config, array $server): string
    {
        $mode = (string)$config->get('config_trusted_proxy_header', '');

        if ($mode === 'cloudflare' && !empty($server['HTTP_CF_CONNECTING_IP'])) {
            $ip = trim((string)$server['HTTP_CF_CONNECTING_IP']);

            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        if ($mode === 'forwarded' && !empty($server['HTTP_X_FORWARDED_FOR'])) {
            $ip = trim(explode(',', (string)$server['HTTP_X_FORWARDED_FOR'])[0]);

            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        return (string)($server['REMOTE_ADDR'] ?? 'unknown');
    }
}
