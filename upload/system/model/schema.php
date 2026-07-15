<?php

declare(strict_types=1);

namespace System\Model;

use System\Engine\Model;
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
	nav INTEGER NOT NULL DEFAULT 1, type TEXT NOT NULL DEFAULT 'article', status TEXT NOT NULL DEFAULT 'published', publish_at INTEGER,
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
CREATE TABLE IF NOT EXISTS extensions (
	name TEXT PRIMARY KEY, version TEXT NOT NULL DEFAULT '', enabled INTEGER NOT NULL DEFAULT 1,
	discovered_at INTEGER NOT NULL, updated_at INTEGER NOT NULL
);
CREATE TABLE IF NOT EXISTS extension_events (
	code TEXT PRIMARY KEY, extension TEXT NOT NULL, event TEXT NOT NULL, description TEXT NOT NULL DEFAULT '', enabled INTEGER NOT NULL DEFAULT 1,
	sort_order INTEGER NOT NULL DEFAULT 0, updated_at INTEGER NOT NULL
);
CREATE TABLE IF NOT EXISTS extension_settings (
	extension TEXT NOT NULL, setting_key TEXT NOT NULL, value_json TEXT NOT NULL DEFAULT 'null', updated_at INTEGER NOT NULL,
	PRIMARY KEY(extension, setting_key)
);
CREATE TABLE IF NOT EXISTS users (
	id INTEGER PRIMARY KEY, username TEXT NOT NULL UNIQUE, display_name TEXT NOT NULL, password_hash TEXT NOT NULL, enabled INTEGER NOT NULL DEFAULT 1, last_login INTEGER, created_at INTEGER NOT NULL, updated_at INTEGER NOT NULL
);
CREATE TABLE IF NOT EXISTS roles (
	id INTEGER PRIMARY KEY, name TEXT NOT NULL UNIQUE, label TEXT NOT NULL, description TEXT NOT NULL DEFAULT ''
);
CREATE TABLE IF NOT EXISTS user_roles (
	user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE, role_id INTEGER NOT NULL REFERENCES roles(id) ON DELETE CASCADE, PRIMARY KEY(user_id, role_id)
);
CREATE TABLE IF NOT EXISTS user_identities (
	provider TEXT NOT NULL, subject TEXT NOT NULL, user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
	created_at INTEGER NOT NULL, updated_at INTEGER NOT NULL, PRIMARY KEY(provider, subject), UNIQUE(provider, user_id)
);
CREATE TABLE IF NOT EXISTS role_permissions (
	role_id INTEGER NOT NULL REFERENCES roles(id) ON DELETE CASCADE, permission TEXT NOT NULL, PRIMARY KEY(role_id, permission)
);
CREATE TABLE IF NOT EXISTS audit_logs (
	id INTEGER PRIMARY KEY, event TEXT NOT NULL, source TEXT NOT NULL DEFAULT 'core', payload_json TEXT NOT NULL DEFAULT '{}', created_at INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS audit_logs_created_idx ON audit_logs(created_at DESC);
CREATE TABLE IF NOT EXISTS page_feedback (
	page_path TEXT NOT NULL, visitor_hash TEXT NOT NULL, vote TEXT NOT NULL CHECK(vote IN ('good', 'bad')),
	created_at INTEGER NOT NULL, updated_at INTEGER NOT NULL, PRIMARY KEY(page_path, visitor_hash)
);
CREATE INDEX IF NOT EXISTS page_feedback_path_idx ON page_feedback(page_path);
CREATE TABLE IF NOT EXISTS login_attempts (
	username TEXT NOT NULL, ip_address TEXT NOT NULL, attempts INTEGER NOT NULL DEFAULT 0,
	first_attempt_at INTEGER NOT NULL, last_attempt_at INTEGER NOT NULL,
	PRIMARY KEY(username, ip_address)
);
CREATE TABLE IF NOT EXISTS admin_sessions (
	session_id TEXT PRIMARY KEY, user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
	ip_address TEXT NOT NULL DEFAULT '', user_agent TEXT NOT NULL DEFAULT '',
	created_at INTEGER NOT NULL, last_seen_at INTEGER NOT NULL, revoked INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS admin_sessions_user_idx ON admin_sessions(user_id, revoked, last_seen_at DESC);
SQL);
	try {
		$this->db->exec("ALTER TABLE extension_events ADD COLUMN description TEXT NOT NULL DEFAULT ''");
		} catch (PDOException) {
			// The column already exists on current installations.
		}
	try {
		$this->db->exec("CREATE VIRTUAL TABLE IF NOT EXISTS documents_fts USING fts5(title, description, keywords, plain_text, content='documents', content_rowid='id', tokenize='unicode61 remove_diacritics 2')");
	} catch (PDOException) {
		// Search falls back to indexed LIKE queries when FTS5 is unavailable.
	}
	try {
		$this->db->exec("ALTER TABLE documents ADD COLUMN status TEXT NOT NULL DEFAULT 'published'");
	} catch (PDOException) {
		// The column already exists on current installations.
	}
	try {
		$this->db->exec('ALTER TABLE documents ADD COLUMN publish_at INTEGER');
	} catch (PDOException) {
		// The column already exists on current installations.
	}
	$this->db->exec("UPDATE documents SET status = CASE WHEN draft = 1 THEN 'draft' ELSE 'published' END WHERE status = 'published' AND publish_at IS NULL");
	$statement = $this->db->prepare('INSERT OR IGNORE INTO schema_migrations(version, applied_at) VALUES (:version, :applied_at)');
	foreach ([2, 3] as $version) $statement->execute(['version' => $version, 'applied_at' => gmdate(DATE_ATOM)]);
	}
}
