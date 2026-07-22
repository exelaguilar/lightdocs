<?php

declare(strict_types=1);

namespace System\Bootstrap;

use System\Engine\Config;
use System\Engine\Registry;
use System\Helper\RequestScheme;
use System\Library\FlashNotifications;
use System\Library\Request;
use System\Library\Session;
use System\Library\User;

/** Boots TinyMVC's session, notifications, and user principal for Lightdocs. */
final class SessionSetup
{
	public static function register(Registry $registry, Config $config, Request $request): void
	{
		$sessionName = (string) $config->get('session_name', 'SESSID_LIGHTDOCS');
		$sessionTimeout = max(0, (int) $config->get('config_session_timeout', 86400));

		$config->set('session_gc_maxlifetime_server', (int) ini_get('session.gc_maxlifetime'));
		if ($sessionTimeout > 0) {
			ini_set('session.gc_maxlifetime', (string) $sessionTimeout);
		}

		session_name($sessionName);
		session_set_cookie_params([
			'lifetime' => $sessionTimeout > 0 ? $sessionTimeout : 0,
			'path' => (string) ($config->get('session_path') ?: '/'),
			'domain' => '',
			'secure' => RequestScheme::isSecure($config, $_SERVER),
			'httponly' => true,
			'samesite' => (string) $config->get('session_samesite', 'Lax'),
		]);
		ini_set('session.use_strict_mode', '1');

		$sessionId = $request->cookie[$sessionName] ?? ($_COOKIE[$sessionName] ?? '');
		$session = new Session();
		$session->start(preg_match('/^[a-zA-Z0-9,-]{22,128}$/', (string) $sessionId) ? (string) $sessionId : '');

		$registry->set('session', $session);
		$registry->set('notifications', new FlashNotifications($session));
		$registry->set('user', new User($registry));
	}
}
