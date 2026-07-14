<?php
$encode_path = static fn (string $path): string => str_replace('%2F', '/', rawurlencode($path));
$initial = mb_strtoupper(mb_substr($config['name'], 0, 1));
$reviewed_value = is_int($editor_meta['reviewed'] ?? null) ? date('Y-m-d', $editor_meta['reviewed']) : (string) ($editor_meta['reviewed'] ?? '');
$render_editor_tree = function (array $nodes) use (&$render_editor_tree, $e, $selected, $encode_path): string {
    $html = '<ul class="studio-tree">';
    foreach ($nodes as $node) {
        if ($node['type'] === 'folder') {
            $landing = !empty($node['landing']) ? '<a class="studio-folder-page" href="/admin/editor?file=' . $encode_path((string) $node['relativePath']) . '">' . $e($node['title']) . '</a>' : $e($node['title']);
            $html .= '<li data-tree-folder><details open><summary><span class="tree-chevron"></span><span class="folder-icon" aria-hidden="true"></span>' . $landing . '</summary>' . $render_editor_tree($node['children']) . '</details></li>';
            continue;
        }
        $active = ($node['relativePath'] ?? '') === $selected;
        $badges = !empty($node['private']) ? '<span class="tree-badge">Private</span>' : (!empty($node['draft']) ? '<span class="tree-badge">Draft</span>' : '');
        $search = mb_strtolower(($node['title'] ?? '') . ' ' . ($node['relativePath'] ?? ''));
        $html .= '<li data-tree-page data-search-text="' . $e($search) . '" draggable="true"><a data-page-file="' . $e($node['relativePath']) . '" href="/admin/editor?file=' . $encode_path((string) $node['relativePath']) . '"' . ($active ? ' aria-current="page"' : '') . '><span class="drag-handle" title="Drag to reorder">⋮⋮</span><span class="tree-page-title">' . $e($node['title']) . '</span>' . $badges . '</a></li>';
    }
    return $html . '</ul>';
};
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Editor · <?= $e($config['name']) ?></title>
  <link rel="stylesheet" href="/frontend/view/stylesheet/app.css?v=<?= @filemtime(dirname(__DIR__, 4) . '/frontend/view/stylesheet/app.css') ?: 1 ?>">
  <style>:root{--brand:<?= $e($config['accent']) ?>}</style>
</head>
<body class="editor-body">
<?php $active_nav = 'editor'; require __DIR__ . '/../common/header.php'; ?>
<div class="editor-shell" data-editor-shell>
  <aside class="editor-files" id="studio-content-panel" aria-label="Content browser">
    <div class="editor-panel-title"><div><span class="panel-eyebrow">Workspace</span><strong>Content</strong></div><span class="editor-panel-actions"><a class="new-page-button" href="/admin/editor" aria-label="Create page">+</a><button type="button" class="content-panel-close" data-close-content aria-label="Close content browser">×</button></span></div>
    <label class="content-filter"><span class="search-icon" aria-hidden="true"></span><input type="search" placeholder="Filter pages…" data-content-filter autocomplete="off"><kbd>/</kbd></label>
    <nav class="studio-tree-nav" aria-label="Documentation pages"><?= $render_editor_tree($tree) ?></nav>

    <details class="studio-resource" <?= $selected_is_snippet ? 'open' : '' ?>>
      <summary><span><span class="snippet-icon" aria-hidden="true">{ }</span>Snippets</span><small><?= count($snippets) ?></small><span class="nav-chevron" aria-hidden="true"></span></summary>
      <div class="resource-body snippet-library"><nav><?php foreach ($snippets as $snippet): ?><a href="/admin/editor?file=<?= rawurlencode($snippet['path']) ?>" <?= $selected === $snippet['path'] ? 'aria-current="page"' : '' ?>><span><?= $e($snippet['title']) ?></span><small><?= count($snippet['usages']) ?> use<?= count($snippet['usages']) === 1 ? '' : 's' ?></small></a><?php endforeach; ?><?php if (!$snippets): ?><p>No snippets yet.</p><?php endif; ?></nav><a class="resource-create" href="/admin/editor?file=_snippets%2Fnew-snippet.md">New snippet</a></div>
    </details>

    <details class="studio-resource">
      <summary><span>Assets</span><small><?= count($assets) ?></small><span class="nav-chevron" aria-hidden="true"></span></summary>
      <div class="resource-body asset-library"><div class="asset-list"><?php foreach ($assets as $asset): ?><button type="button" data-insert-asset="<?= $e($asset['url']) ?>"><span><?= $e($asset['name']) ?></span><small><?= number_format($asset['size'] / 1024, 1) ?> KB · <?= count($asset['usages']) ?> use<?= count($asset['usages']) === 1 ? '' : 's' ?></small></button><?php endforeach; ?><?php if (!$assets): ?><p>No uploaded assets.</p><?php endif; ?></div><form method="post" enctype="multipart/form-data" class="upload-form" data-upload-form><input type="hidden" name="csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="action" value="upload"><input type="hidden" name="file" value="<?= $e($selected) ?>"><label class="upload-drop"><strong>Upload asset</strong><small>Images, PDF, or text</small><input type="file" name="asset" required></label><button type="submit">Upload</button></form></div>
    </details>

    <div class="studio-index-status"><span class="status-dot"></span><span><strong>Index synced</strong><small><?= (int) ($index_stats['documents'] ?? 0) ?> docs · <?= (int) ($index_stats['keywords'] ?? 0) ?> keywords · <?= (int) ($index_stats['links'] ?? 0) ?> links</small></span></div>
  </aside>
  <button type="button" class="editor-drawer-backdrop" data-close-content aria-label="Close content browser"></button>

  <main class="editor-main">
    <?php if ($message): ?><p class="form-success" role="status"><?= $e($message) ?></p><?php endif; ?>
    <?php if ($error): ?><p class="form-error" role="alert"><?= $e($error) ?></p><?php endif; ?>
    <form method="post" class="editor-form" data-editor-form data-content-kind="<?= $selected_is_snippet ? 'snippet' : 'page' ?>">
      <input type="hidden" name="csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="hash" value="<?= $e($source['hash']) ?>"><input type="hidden" name="action" value="save">
      <?php if ($selected === ''): ?><nav class="template-picker" aria-label="Page template"><span>Start from</span><?php foreach ($templates as $key => $label): ?><a href="/admin/editor?template=<?= rawurlencode($key) ?>" class="<?= $selected_template === $key ? 'active' : '' ?>"><?= $e($label) ?></a><?php endforeach; ?></nav><?php endif; ?>

      <div class="editor-toolbar">
        <button class="content-panel-toggle" type="button" data-toggle-content aria-controls="studio-content-panel" aria-expanded="true"><span aria-hidden="true">☰</span><span>Content</span></button>
        <label class="path-field"><span>Path</span><input name="file" value="<?= $e($selected) ?>" placeholder="guides/new-page.md" required></label>
        <div class="editor-toolbar-actions">
          <span class="editor-count" data-editor-count></span>
          <?php if (!$selected_is_snippet): ?><button type="button" data-toggle-metadata>Details</button><?php endif; ?>
          <button type="button" data-toggle-preview aria-pressed="false">Preview only</button>
          <?php if ($git_file_history): ?><button type="button" class="toolbar-history" data-toggle-git-history>History <span class="button-count"><?= count($git_file_history) ?></span></button><?php endif; ?>
          <details class="editor-more"><summary>More</summary><div><?php if ($preview_url): ?><a href="<?= $e($preview_url) ?>" target="_blank">Share preview</a><?php endif; ?><?php if ($revisions): ?><button type="button" data-toggle-revisions>Revisions <span><?= count($revisions) ?></span></button><?php endif; ?><?php if (!$selected_is_snippet): ?><button type="button" data-duplicate-page>Duplicate page</button><?php endif; ?></div></details>
          <span class="studio-status" aria-live="polite"><span class="status-dot"></span><span data-save-state>Saved</span></span>
          <button class="button" type="submit">Save</button>
        </div>
      </div>

      <?php if ($selected_is_snippet): ?>
        <section class="snippet-usage-panel"><div><strong>Used by <?= count($snippet_usages) ?> page<?= count($snippet_usages) === 1 ? '' : 's' ?></strong><span>Detected from include directives.</span></div><nav><?php foreach ($snippet_usages as $usage): ?><a href="/admin/editor?file=<?= rawurlencode($usage->relative_path) ?>"><span><?= $e($usage->title) ?></span><small><?= $e($usage->relative_path) ?></small></a><?php endforeach; ?><?php if (!$snippet_usages): ?><span class="unused-snippet">This snippet is not currently used.</span><?php endif; ?></nav></section>
      <?php else: ?>
        <section class="metadata-panel" data-metadata-panel>
          <div><label>Title<input type="text" value="<?= $e($editor_meta['title'] ?? '') ?>" data-meta-field="title"></label><label>Description<input type="text" value="<?= $e($editor_meta['description'] ?? '') ?>" data-meta-field="description"></label></div>
          <div><label>Keywords<input type="text" list="known-keywords" value="<?= $e(is_array($editor_meta['keywords'] ?? null) ? implode(', ', $editor_meta['keywords']) : ($editor_meta['keywords'] ?? '')) ?>" data-meta-field="keywords" placeholder="storage, backup, lxc"><datalist id="known-keywords"><?php foreach ($keyword_stats as $keyword): ?><option value="<?= $e($keyword['name']) ?>"><?= (int) $keyword['usage_count'] ?> uses</option><?php endforeach; ?></datalist></label><label>Redirect aliases<input type="text" value="<?= $e(is_array($editor_meta['aliases'] ?? null) ? implode(', ', $editor_meta['aliases']) : ($editor_meta['aliases'] ?? '')) ?>" data-meta-field="aliases" placeholder="/old-path"></label></div>
          <div class="metadata-compact"><label>Order<input type="number" value="<?= $e($editor_meta['order'] ?? 100) ?>" data-meta-field="order"></label><label>Visibility<select data-meta-field="visibility"><option value="public" <?= ($editor_meta['visibility'] ?? 'public') === 'public' ? 'selected' : '' ?>>Public</option><option value="private" <?= ($editor_meta['visibility'] ?? '') === 'private' ? 'selected' : '' ?>>Private</option></select></label><label>Type<select data-meta-field="type"><option value="article" <?= ($editor_meta['type'] ?? 'article') === 'article' ? 'selected' : '' ?>>Article</option><option value="runbook" <?= ($editor_meta['type'] ?? '') === 'runbook' ? 'selected' : '' ?>>Runbook</option></select></label><label>Reviewed<input type="date" value="<?= $e($reviewed_value) ?>" data-meta-field="reviewed"></label><label>Review after<input type="number" min="1" value="<?= $e($editor_meta['review_after'] ?? 180) ?>" data-meta-field="review_after"></label></div>
          <div class="metadata-flags">
            <label class="flag-toggle"><input type="checkbox" data-meta-field="draft" <?= !empty($editor_meta['draft']) ? 'checked' : '' ?>><span class="flag-switch" aria-hidden="true"></span><span class="flag-text"><strong>Draft</strong><small>Hidden from readers until published</small></span></label>
            <label class="flag-toggle"><input type="checkbox" data-meta-field="nav" <?= ($editor_meta['nav'] ?? true) !== false ? 'checked' : '' ?>><span class="flag-switch" aria-hidden="true"></span><span class="flag-text"><strong>In navigation</strong><small>Listed in the sidebar page tree</small></span></label>
            <label class="flag-toggle flag-danger"><input type="checkbox" data-meta-field="contains_secrets" <?= !empty($editor_meta['contains_secrets']) ? 'checked' : '' ?>><span class="flag-switch" aria-hidden="true"></span><span class="flag-text"><strong>Contains secrets</strong><small>Redacted in sanitized exports; keep visibility private</small></span></label>
            <label class="flag-toggle"><input type="checkbox" data-meta-field="ai_exclude" <?= !empty($editor_meta['ai_exclude']) ? 'checked' : '' ?>><span class="flag-switch" aria-hidden="true"></span><span class="flag-text"><strong>Exclude from AI</strong><small>Left out of llms.txt exports</small></span></label>
          </div>
        </section>
        <details class="page-insights"><summary><span><strong>Page intelligence</strong><small><?= count($page_issues) ?> issues · <?= count($page_backlinks) ?> incoming · <?= count($page_outbound) ?> outgoing</small></span><span class="nav-chevron" aria-hidden="true"></span></summary><nav><?php foreach (array_slice($page_issues, 0, 4) as $issue): ?><a href="#contents" class="insight-<?= $e($issue['severity']) ?>"><span><?= $e(ucfirst($issue['severity'])) ?></span><?= $e($issue['message']) ?></a><?php endforeach; ?><?php foreach (array_slice($page_backlinks, 0, 3) as $page): ?><a href="/admin/editor?file=<?= rawurlencode($page->relative_path) ?>"><span>Linked by</span><?= $e($page->title) ?></a><?php endforeach; ?><?php foreach (array_slice($page_outbound, 0, 3) as $page): ?><a href="/admin/editor?file=<?= rawurlencode($page->relative_path) ?>"><span>Links to</span><?= $e($page->title) ?></a><?php endforeach; ?><?php if (!$page_issues && !$page_backlinks && !$page_outbound): ?><span class="insight-empty">No issues or page relationships.</span><?php endif; ?></nav></details>
      <?php endif; ?>

      <details class="insert-panel"><summary><span>Insert content</span><small>Directives, page links, and snippets</small><span class="nav-chevron" aria-hidden="true"></span></summary><section class="authoring-toolbar" aria-label="Insert documentation content"><label>Directive<select data-insert-directive><option value="callout">Callout</option><option value="banner">Banner</option><option value="tabs">Tabs</option><option value="filetree">File tree</option><option value="figure">Figure</option><option value="inline-toc">Inline TOC</option><option value="code">Code frame</option><option value="comparison">Comparison</option><option value="details">Details</option></select></label><button type="button" data-insert-directive-button>Insert</button><label>Page link<select data-insert-page><?php foreach ($files as $file): ?><option value="<?= $e($file) ?>"><?= $e($file) ?></option><?php endforeach; ?></select></label><button type="button" data-insert-page-button>Insert</button><label>Snippet<select data-insert-snippet><?php foreach ($snippets as $snippet): ?><option value="<?= $e($snippet['path']) ?>"><?= $e($snippet['title']) ?></option><?php endforeach; ?></select></label><button type="button" data-insert-snippet-button>Include</button></section></details>

      <?php if ($revisions): ?><section class="revision-panel" data-revision-panel><div class="revision-panel-head"><div><strong>Revision history</strong><span>Compare or restore an earlier version.</span></div><button type="button" data-close-revisions>×</button></div><div class="revision-list"><?php foreach ($revisions as $revision): ?><div><span><strong><?= $e(date('M j, Y H:i', $revision['modified'])) ?></strong><small><?= number_format($revision['size'] / 1024, 1) ?> KB</small></span><span class="revision-actions"><button type="button" data-compare-revision="<?= $e($revision['id']) ?>">Compare</button><button type="submit" name="action" value="restore:<?= $e($revision['id']) ?>" data-restore-revision>Restore</button></span></div><?php endforeach; ?></div></section><?php endif; ?>

      <?php if ($git_file_history): ?><section class="revision-panel git-history-panel" data-git-history-panel><div class="revision-panel-head"><div><strong>Local Git history</strong><span>Committed versions of this Markdown note. Viewing a snapshot never changes the file.</span></div><button type="button" data-close-git-history>&times;</button></div><div class="revision-list git-note-history"><?php foreach ($git_file_history as $commit): ?><div><span><strong><?= $e($commit['subject']) ?></strong><small><code><?= $e($commit['short']) ?></code> &middot; <?= $e($commit['author']) ?> &middot; <?= $e(date('M j, Y H:i', strtotime($commit['date']))) ?></small></span><span class="revision-actions"><button type="button" data-compare-git="<?= $e($commit['hash']) ?>" data-git-label="<?= $e($commit['short'] . ' · ' . $commit['subject']) ?>">Compare</button></span></div><?php endforeach; ?></div></section><?php endif; ?>

      <div class="editor-workspace"><div class="source-pane"><div class="pane-label"><span>Markdown</span><span>Ctrl/Cmd + S</span></div><nav class="editor-outline" data-editor-outline aria-label="Page outline"></nav><label class="sr-only" for="contents">Markdown</label><textarea id="contents" name="contents" spellcheck="true" data-markdown-editor><?= $e($source['contents']) ?></textarea></div><div class="preview-pane"><div class="pane-label"><span>Preview</span><span class="preview-sizes"><button type="button" data-preview-size="desktop" class="active">Desktop</button><button type="button" data-preview-size="tablet">Tablet</button><button type="button" data-preview-size="mobile">Mobile</button></span></div><iframe class="editor-preview" title="Preview" data-preview-frame></iframe></div></div>
    </form>
  </main>
</div>
<dialog class="revision-compare" data-revision-compare><div class="revision-compare-head"><strong>Revision comparison</strong><button type="button" data-close-compare>×</button></div><div><section><span>Selected revision</span><pre data-revision-source></pre></section><section><span>Current editor</span><pre data-current-source></pre></section></div></dialog>
<dialog class="revision-compare" data-git-compare><div class="revision-compare-head"><strong>Local Git note comparison</strong><button type="button" data-close-git-compare>&times;</button></div><div><section><span data-git-source-label>Committed version</span><pre data-git-source></pre></section><section><span>Current editor</span><pre data-git-current-source></pre></section></div></dialog>
</body>
</html>
