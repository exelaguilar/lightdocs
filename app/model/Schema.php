<?php

declare(strict_types=1);

namespace Lightdocs\App\Model;

use Lightdocs\System\Engine\Model;
use PDOException;

final class Schema extends Model
{
    public function migrate(): void
    {
        $this->db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS schema_migrations (version INTEGER PRIMARY KEY, applied_at TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS index_meta (key TEXT PRIMARY KEY, value TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS documents (
    id INTEGER PRIMARY KEY, path TEXT NOT NULL UNIQUE, url TEXT NOT NULL UNIQUE,
    title TEXT NOT NULL, description TEXT NOT NULL DEFAULT '', keywords TEXT NOT NULL DEFAULT '',
    visibility TEXT NOT NULL DEFAULT 'public', draft INTEGER NOT NULL DEFAULT 0,
    nav INTEGER NOT NULL DEFAULT 1, type TEXT NOT NULL DEFAULT 'article',
    frontmatter_json TEXT NOT NULL, content_hash TEXT NOT NULL, plain_text TEXT NOT NULL,
    modified_at INTEGER NOT NULL, indexed_at INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS documents_visibility_idx ON documents(visibility, draft);
CREATE TABLE IF NOT EXISTS headings (
    id INTEGER PRIMARY KEY, document_id INTEGER NOT NULL REFERENCES documents(id) ON DELETE CASCADE,
    anchor TEXT NOT NULL, title TEXT NOT NULL, level INTEGER NOT NULL, position INTEGER NOT NULL,
    UNIQUE(document_id, anchor)
);
CREATE TABLE IF NOT EXISTS links (
    id INTEGER PRIMARY KEY, source_document_id INTEGER NOT NULL REFERENCES documents(id) ON DELETE CASCADE,
    target_url TEXT NOT NULL, kind TEXT NOT NULL DEFAULT 'page', label TEXT NOT NULL DEFAULT ''
);
CREATE INDEX IF NOT EXISTS links_target_idx ON links(target_url);
CREATE TABLE IF NOT EXISTS keywords (
    id INTEGER PRIMARY KEY, name TEXT NOT NULL UNIQUE COLLATE NOCASE, slug TEXT NOT NULL UNIQUE
);
CREATE TABLE IF NOT EXISTS document_keywords (
    document_id INTEGER NOT NULL REFERENCES documents(id) ON DELETE CASCADE,
    keyword_id INTEGER NOT NULL REFERENCES keywords(id) ON DELETE CASCADE,
    PRIMARY KEY(document_id, keyword_id)
);
CREATE TABLE IF NOT EXISTS aliases (
    id INTEGER PRIMARY KEY, document_id INTEGER NOT NULL REFERENCES documents(id) ON DELETE CASCADE,
    alias TEXT NOT NULL UNIQUE
);
CREATE TABLE IF NOT EXISTS snippets (path TEXT PRIMARY KEY, title TEXT NOT NULL, content_hash TEXT NOT NULL, modified_at INTEGER NOT NULL);
CREATE TABLE IF NOT EXISTS snippet_usage (
    snippet_path TEXT NOT NULL REFERENCES snippets(path) ON DELETE CASCADE,
    document_id INTEGER NOT NULL REFERENCES documents(id) ON DELETE CASCADE,
    PRIMARY KEY(snippet_path, document_id)
);
CREATE TABLE IF NOT EXISTS assets (path TEXT PRIMARY KEY, url TEXT NOT NULL UNIQUE, mime TEXT NOT NULL, size INTEGER NOT NULL, modified_at INTEGER NOT NULL);
CREATE TABLE IF NOT EXISTS asset_usage (
    asset_path TEXT NOT NULL REFERENCES assets(path) ON DELETE CASCADE,
    document_id INTEGER NOT NULL REFERENCES documents(id) ON DELETE CASCADE,
    PRIMARY KEY(asset_path, document_id)
);
CREATE TABLE IF NOT EXISTS studio_sessions (
    session_id TEXT PRIMARY KEY, user_label TEXT NOT NULL DEFAULT 'admin', state_json TEXT NOT NULL DEFAULT '{}', updated_at INTEGER NOT NULL
);
CREATE TABLE IF NOT EXISTS settings (
    key TEXT PRIMARY KEY, value_json TEXT NOT NULL, source TEXT NOT NULL DEFAULT 'yaml', updated_at INTEGER NOT NULL
);
CREATE TABLE IF NOT EXISTS git_sync_runs (
    id INTEGER PRIMARY KEY, repository TEXT NOT NULL, policy TEXT NOT NULL,
    state TEXT NOT NULL, commit_hash TEXT NOT NULL DEFAULT '', message TEXT NOT NULL DEFAULT '',
    summary_json TEXT NOT NULL DEFAULT '{}', error TEXT NOT NULL DEFAULT '', created_at INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS git_sync_runs_repository_idx ON git_sync_runs(repository, created_at DESC);
SQL);
        try {
            $this->db->exec("CREATE VIRTUAL TABLE IF NOT EXISTS documents_fts USING fts5(title, description, keywords, plain_text, content='documents', content_rowid='id', tokenize='unicode61 remove_diacritics 2')");
        } catch (PDOException) {
            // Search falls back to indexed LIKE queries when FTS5 is unavailable.
        }
        $statement = $this->db->prepare('INSERT OR IGNORE INTO schema_migrations(version, applied_at) VALUES (:version, :applied_at)');
        foreach ([2, 3] as $version) $statement->execute(['version' => $version, 'applied_at' => gmdate(DATE_ATOM)]);
    }
}
