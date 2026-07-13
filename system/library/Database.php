<?php

declare(strict_types=1);

namespace Lightdocs\System\Library;

use PDO;

final class Database
{
    private PDO $pdo;

    public function __construct(string $path)
    {
        if (!is_dir(dirname($path))) mkdir(dirname($path), 0775, true);
        $this->pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->pdo->exec('PRAGMA foreign_keys = ON; PRAGMA journal_mode = WAL; PRAGMA synchronous = NORMAL; PRAGMA busy_timeout = 5000;');
    }

    public function connection(): PDO
    {
        return $this->pdo;
    }
}
