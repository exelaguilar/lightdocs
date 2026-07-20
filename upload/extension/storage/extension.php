<?php

declare(strict_types=1);

namespace Extension\Storage;

use RuntimeException;
use System\Engine\AssetStorage;
use System\Engine\ExtensionApplication;
use System\Engine\ExtensionContext;
use System\Engine\ExtensionInterface;

final class Extension implements ExtensionInterface, AssetStorage
{
	private ExtensionApplication $context;

	public function register(ExtensionContext $context): void
	{
		$this->context = $this->application($context);
		$context->service('storage.assets', $this);
	}

	private function application(ExtensionContext $context): ExtensionApplication
	{
		$application = $context->capability('lightdocs.application');
		if (!$application instanceof ExtensionApplication) throw new RuntimeException('Invalid Lightdocs extension capability.');
		return $application;
	}

	public function publish(string $path, string $name, string $mime): ?string
	{
		$settings = $this->context->settings;
		if (strtolower((string) ($settings['driver'] ?? 's3')) !== 's3') return null;
		$endpoint = rtrim((string) ($settings['endpoint'] ?? ''), '/');
		$bucket = trim((string) ($settings['bucket'] ?? ''));
		$access_key = trim((string) ($settings['access_key'] ?? ''));
		$secret_key = (string) ($settings['secret_key'] ?? '');
		if ($endpoint === '' || $bucket === '' || $access_key === '' || $secret_key === '' || !is_file($path)) return null;
		$contents = file_get_contents($path);
		if ($contents === false) throw new RuntimeException('Could not read the local asset for external publication.');
		$object = trim((string) ($settings['prefix'] ?? 'lightdocs'), '/') . '/' . $name;
		$parsed = parse_url($endpoint);
		$scheme = (string) ($parsed['scheme'] ?? 'https');
		$host = (string) ($parsed['host'] ?? '');
		$port = isset($parsed['port']) ? ':' . (int) $parsed['port'] : '';
		$base_path = trim((string) ($parsed['path'] ?? ''), '/');
		if ($host === '') return null;
		$uri = '/' . implode('/', array_filter([$base_path, $bucket, $this->encodePath($object)]));
		$payload_hash = hash('sha256', $contents);
		$amz_date = gmdate('Ymd\THis\Z');
		$date = gmdate('Ymd');
		$region = (string) ($settings['region'] ?? 'us-east-1');
		$service = 's3';
		$credential_scope = $date . '/' . $region . '/' . $service . '/aws4_request';
		$headers = 'host:' . $host . $port . "\n" . 'x-amz-content-sha256:' . $payload_hash . "\n" . 'x-amz-date:' . $amz_date . "\n";
		$signed_headers = 'host;x-amz-content-sha256;x-amz-date';
		$canonical_request = "PUT\n" . $uri . "\n\n" . $headers . "\n" . $signed_headers . "\n" . $payload_hash;
		$string_to_sign = "AWS4-HMAC-SHA256\n" . $amz_date . "\n" . $credential_scope . "\n" . hash('sha256', $canonical_request);
		$date_key = hash_hmac('sha256', $date, 'AWS4' . $secret_key, true);
		$region_key = hash_hmac('sha256', $region, $date_key, true);
		$service_key = hash_hmac('sha256', $service, $region_key, true);
		$signing_key = hash_hmac('sha256', 'aws4_request', $service_key, true);
		$signature = hash_hmac('sha256', $string_to_sign, $signing_key);
		$authorization = 'AWS4-HMAC-SHA256 Credential=' . $access_key . '/' . $credential_scope . ', SignedHeaders=' . $signed_headers . ', Signature=' . $signature;
		$url = $scheme . '://' . $host . $port . $uri;
		$options = [
			'http' => [
				'method' => 'PUT',
				'header' => "Host: " . $host . $port . "\r\nContent-Type: " . $mime . "\r\nx-amz-content-sha256: " . $payload_hash . "\r\nx-amz-date: " . $amz_date . "\r\nAuthorization: " . $authorization . "\r\n",
				'content' => $contents,
				'timeout' => max(1, min(120, (int) ($settings['timeout'] ?? 15))),
				'ignore_errors' => true,
			],
		];
		$response = file_get_contents($url, false, stream_context_create($options));
		$status = $http_response_header[0] ?? '';
		if ($response === false || !preg_match('/\s2\d\d\s/', $status)) throw new RuntimeException('The external asset provider rejected the upload.');
		$public_base_url = rtrim((string) ($settings['public_base_url'] ?? ''), '/');
		return ($public_base_url !== '' ? $public_base_url : $scheme . '://' . $host . $port . '/' . $bucket) . '/' . $this->encodePath($object);
	}

	private function encodePath(string $path): string
	{
		return implode('/', array_map('rawurlencode', array_filter(explode('/', $path), static fn (string $part): bool => $part !== '')));
	}
}
