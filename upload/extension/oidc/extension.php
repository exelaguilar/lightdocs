<?php

declare(strict_types=1);

namespace Extension\Oidc;

use RuntimeException;
use System\Engine\AuthProvider;
use System\Engine\ExtensionContext;
use System\Engine\ExtensionInterface;
use System\Engine\ExtensionManager;
use System\Engine\Request;

final class Extension implements ExtensionInterface, AuthProvider
{
	public function __construct(private readonly ExtensionContext $context)
	{
	}

	public function name(): string
	{
		return 'oidc';
	}

	public function register(ExtensionManager $extensions): void
	{
		$extensions->service('auth.provider', $this);
	}

	public function authorizationUrl(string $state, string $redirect_uri): string
	{
		$settings = $this->settings();
		$this->assertConfigured($settings);
		return $settings['authorization_url'] . '?' . http_build_query([
			'client_id' => $settings['client_id'],
			'redirect_uri' => $redirect_uri,
			'response_type' => 'code',
			'scope' => $settings['scopes'],
			'state' => $state,
		], '', '&', PHP_QUERY_RFC3986);
	}

	public function authenticate(Request $request, string $redirect_uri): array
	{
		$settings = $this->settings();
		$this->assertConfigured($settings);
		$code = trim((string) $request->query('code'));
		if ($code === '') throw new RuntimeException('The identity provider did not return an authorization code.');
		$token = $this->requestJson($settings['token_url'], [
			'grant_type' => 'authorization_code',
			'code' => $code,
			'client_id' => $settings['client_id'],
			'client_secret' => $settings['client_secret'],
			'redirect_uri' => $redirect_uri,
		]);
		$access_token = trim((string) ($token['access_token'] ?? ''));
		if ($access_token === '') throw new RuntimeException('The identity provider did not return an access token.');
		$claims = $this->requestJson($settings['userinfo_url'], [], ['Authorization: Bearer ' . $access_token]);
		$subject = trim((string) ($claims['sub'] ?? ''));
		if ($subject === '') throw new RuntimeException('The identity provider response did not include a subject.');
		$username = trim((string) ($claims['email'] ?? $claims['preferred_username'] ?? $subject));
		$display_name = trim((string) ($claims['name'] ?? $claims['preferred_username'] ?? $username));

		return ['subject' => $subject, 'username' => $username, 'display_name' => $display_name, 'provision' => $settings['auto_provision'], 'role' => $settings['default_role']];
	}

	/** @return array{auto_provision:bool,authorization_url:string,client_id:string,client_secret:string,default_role:string,scopes:string,token_url:string,userinfo_url:string} */
	public function settings(): array
	{
		return [
			'auto_provision' => (bool) ($this->context->settings['auto_provision'] ?? false),
			'authorization_url' => trim((string) ($this->context->settings['authorization_url'] ?? '')),
			'client_id' => trim((string) ($this->context->settings['client_id'] ?? '')),
			'client_secret' => (string) ($this->context->settings['client_secret'] ?? ''),
			'default_role' => trim((string) ($this->context->settings['default_role'] ?? 'viewer')),
			'scopes' => trim((string) ($this->context->settings['scopes'] ?? 'openid profile email')),
			'token_url' => trim((string) ($this->context->settings['token_url'] ?? '')),
			'userinfo_url' => trim((string) ($this->context->settings['userinfo_url'] ?? '')),
		];
	}

	/** @param array<string,mixed> $settings */
	private function assertConfigured(array $settings): void
	{
		foreach (['authorization_url', 'token_url', 'userinfo_url'] as $key) {
			if (!str_starts_with(strtolower((string) $settings[$key]), 'https://')) throw new RuntimeException('OIDC endpoints must use HTTPS. Configure the OIDC extension first.');
		}
		if ($settings['client_id'] === '' || $settings['client_secret'] === '') throw new RuntimeException('Configure the OIDC client ID and secret first.');
	}

	/** @param array<string,string> $form @param list<string> $headers @return array<string,mixed> */
	private function requestJson(string $url, array $form, array $headers = []): array
	{
		$options = ['http' => ['method' => $form === [] ? 'GET' : 'POST', 'header' => implode("\r\n", array_merge($form === [] ? [] : ['Content-Type: application/x-www-form-urlencoded'], $headers)), 'timeout' => 10, 'ignore_errors' => true]];
		if ($form !== []) $options['http']['content'] = http_build_query($form, '', '&', PHP_QUERY_RFC3986);
		$body = @file_get_contents($url, false, stream_context_create($options));
		if ($body === false) throw new RuntimeException('The identity provider could not be reached.');
		$value = json_decode($body, true);
		if (!is_array($value)) throw new RuntimeException('The identity provider returned invalid JSON.');
		return $value;
	}
}
