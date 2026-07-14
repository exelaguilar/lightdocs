<?php

declare(strict_types=1);

namespace System\Engine;

interface AuthProvider
{
	public function authorizationUrl(string $state, string $redirect_uri): string;

	/** @return array{subject:string,username:string,display_name:string,provision:bool,role:string} */
	public function authenticate(Request $request, string $redirect_uri): array;
}
