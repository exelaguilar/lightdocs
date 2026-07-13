<?php

declare(strict_types=1);

namespace Lightdocs\System\Engine;

use Lightdocs\System\Library\Database;
use PDO;

abstract class Model
{
    protected readonly PDO $db;

    public function __construct(
        protected readonly Database $database,
        protected readonly EventDispatcher $events,
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
}
