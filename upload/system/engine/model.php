<?php

declare(strict_types=1);

namespace System\Engine;

use System\Library\DB;
use PDO;

abstract class Model
{
	private static ?Proxy $proxy = null;
	protected readonly PDO $db;

	public static function setRegistry(Registry $registry): void
	{
		self::$proxy = new Proxy($registry);
	}

	public function __construct(
		protected readonly DB $database,
		protected readonly Event $events,
	) {
		$this->db = $database->connection();
	}

	protected function transaction(callable $operation): mixed
	{
		$this->db->beginTransaction();
		try {
			$result = $operation();
			$this->db->commit();
			return $result;
		} catch (\Throwable $exception) {
			if ($this->db->inTransaction()) $this->db->rollBack();
			throw $exception;
		}
	}

	public function __get(string $key): mixed
	{
		return self::$proxy?->{$key};
	}
}
