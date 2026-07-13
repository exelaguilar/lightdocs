<?php
$initial = mb_strtoupper(mb_substr($config['name'], 0, 1));
$reviewedValue = is_int($editorMeta['reviewed'] ?? null) ? date('Y-m-d', $editorMeta['reviewed']) : (string) ($editorMeta['reviewed'] ?? '');
$renderEditorTree = function (array $nodes) use (&$renderEditorTree, $e, $selected): string {
    $html = '<ul class="studio-tree">';
    foreach ($nodes as $node) {
        if ($node['type'] === 'folder') {
            $landing = !empty($node['landing']) ? '<a class="studio-folder-page" href="/admin/editor?file=' . rawurlencode((string) $node['relativePath']) . '">' . $e($node['title']) . '</a>' : $e($node['title']);
            $html .= '<li data-tree-folder><details open><summary><span class="tree-chevron"></span><span class="folder-icon" aria-hidden="true"></span>' . $landing . '</summary>' . $renderEditorTree($node['children']) . '</details></li>';
            continue;
        }
        $active = ($node['relativePath'] ?? '') === $selected;
        $badges = !empty($node['private']) ? '<span class="tree-badge">Private</span>' : (!empty($node['draft']) ? '<span class="tree-badge">Draft</span>' : '');
        $search = mb_strtolower(($node['title'] ?? '') . ' ' . ($node['relativePath'] ?? ''));
        $html .= '<li data-tree-page data-search-text="' . $e($search) . '" draggable="true"><a data-page-file="' . $e($node['relativePath']) . '" href="/admin/editor?file=' . rawurlencode((string) $node['relativePath']) . '"' . ($active ? ' aria-current="page"' : '') . '><span class="drag-handle" title="Drag to reorder">⋮⋮</span><span class="tree-page-title">' . $e($node['title']) . '</span>' . $badges . '</a></li>';
    }
    return $html . '</ul>';
};
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Editor · <?= $e($config['name']) ?></title>
  <link rel="stylesheet" href="/assets/app.css?v=<?= @filemtime(dirname(__DIR__, 3) . '/public/assets/app.css') ?: 1 ?>">
  <style>:root{--brand:<?= $e($config['accent']) ?>}</style>
  <script type="module" src="/assets/admin.js?v=<?= @filemtime(dirname(__DIR__, 3) . '/public/assets/admin.js') ?: 1 ?>"></script>
</head>
<body class="editor-body">
<?php $activeNav = 'editor'; require __DIR__ . '/_header.php'; ?>
<div class="editor-shell" data-editor-shell>
  <aside class="editor-files" id="studio-content-panel" aria-label="Content browser">
    <div class="editor-panel-title"><div><span class="panel-eyebrow">Workspace</span><strong>Content</strong></div><span class="editor-panel-actions"><a class="new-page-button" href="/admin/editor" aria-label="Create page">+</a><button type="button" class="content-panel-close" data-close-content aria-label="Close content browser">×</button></span></div>
    <label class="content-filter"><span class="search-icon" aria-hidden="true"></span><input type="search" placeholder="Filter pages…" data-content-filter autocomplete="off"><kbd>/</kbd></label>
    <nav class="studio-tree-nav" aria-label="Documentation pages"><?= $renderEditorTree($tree) ?></nav>

    <details class="studio-resource" <?= $selectedIsSnippet ? 'open' : '' ?>>
      <summary><span><span class="snippet-icon" aria-hidden="true">{ }</span>Snippets</span><small><?= count($snippets) ?></small><span class="nav-chevron" aria-hidden="true"></span></summary>
      <div class="resource-body snippet-library"><nav><?php foreach ($snippets as $snippet): ?><a href="/admin/editor?file=<?= rawurlencode($snippet['path']) ?>" <?= $selected === $snippet['path'] ? 'aria-current="page"' : '' ?>><span><?= $e($snippet['title']) ?></span><small><?= count($snippet['usages']) ?> use<?= count($snippet['usages']) === 1 ? '' : 's' ?></small></a><?php endforeach; ?><?php if (!$snippets): ?><p>No snippets yet.</p><?php endif; ?></nav><a class="resource-create" href="/admin/editor?file=_snippets%2Fnew-snippet.md">New snippet</a></div>
    </details>

    <details class="studio-resource">
      <summary><span>Assets</span><small><?= count($assets) ?></small><span class="nav-chevron" aria-hidden="true"></span></summary>
      <div class="resource-body asset-library"><div class="asset-list"><?php foreach ($assets as $asset): ?><button type="button" data-insert-asset="<?= $e($asset['url']) ?>"><span><?= $e($asset['name']) ?></span><small><?= number_format($asset['size'] / 1024, 1) ?> KB · <?= count($asset['usages']) ?> use<?= count($asset['usages']) === 1 ? '' : 's' ?></small></button><?php endforeach; ?><?php if (!$assets): ?><p>No uploaded assets.</p><?php endif; ?></div><form method="post" enctype="multipart/form-data" class="upload-form" data-upload-form><input type="hidden" name="csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="action" value="upload"><input type="hidden" name="file" value="<?= $e($selected) ?>"><label class="upload-drop"><strong>Upload asset</strong><small>Images, PDF, or text</small><input type="file" name="asset" required></label><button type="submit">Upload</button></form></div>
    </details>

    <div class="studio-index-status"><span class="status-dot"></span><span><strong>Index synced</strong><small><?= (int) ($indexStats['documents'] ?? 0) ?> docs · <?= (int) ($indexStats['keywords'] ?? 0) ?> keywords · <?= (int) ($indexStats['links'] ?? 0) ?> links</small></span></div>
  </aside>
  <button type="button" class="editor-drawer-backdrop" data-close-content aria-label="Close content browser"></button>

  <main class="editor-main">
    <?php if ($message): ?><p class="form-success" role="status"><?= $e($message) ?></p><?php endif; ?>
    <?php if ($error): ?><p class="form-error" role="alert"><?= $e($error) ?></p><?php endif; ?>
    <form method="post" class="editor-form" data-editor-form data-content-kind="<?= $selectedIsSnippet ? 'snippet' : 'page' ?>">
      <input type="hidden" name="csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="hash" value="<?= $e($source['hash']) ?>"><input type="hidden" name="action" value="save">
      <?php if ($selected === ''): ?><nav class="template-picker" aria-label="Page template"><span>Start from</span><?php foreach ($templates as $key => $label): ?><a href="/admin/editor?template=<?= rawurlencode($key) ?>" class="<?= $selectedTemplate === $key ? 'active' : '' ?>"><?= $e($label) ?></a><?php endforeach; ?></nav><?php endif; ?>

      <div class="editor-toolbar">
        <button class="content-panel-toggle" type="button" data-toggle-content aria-controls="studio-content-panel" aria-expanded="true"><span aria-hidden="true">☰</span><span>Content</span></button>
        <label class="path-field"><span>Path</span><input name="file" value="<?= $e($selected) ?>" placeholder="guides/new-page.md" required></label>
        <div class="editor-toolbar-actions">
          <span class="editor-count" data-editor-count></span>
          <?php if (!$selectedIsSnippet): ?><button type="button" data-toggle-metadata>Details</button><?php endif; ?>
          <button type="button" data-toggle-preview aria-pressed="false">Preview only</button>
          <?php if ($gitFileHistory): ?><button type="button" class="toolbar-history" data-toggle-git-history>History <span class="button-count"><?= count($gitFileHistory) ?></span></button><?php endif; ?>
          <details class="editor-more"><summary>More</summary><div><?php if ($previewUrl): ?><a href="<?= $e($previewUrl) ?>" target="_blank">Share preview</a><?php endif; ?><?php if ($revisions): ?><button type="button" data-toggle-revisions>Revisions <span><?= count($revisions) ?></span></button><?php endif; ?><?php if (!$selectedIsSnippet): ?><button type="button" data-duplicate-page>Duplicate page</button><?php endif; ?></div></details>
          <span class="studio-status" aria-live="polite"><span class="status-dot"></span><span data-save-state>Saved</span></span>
          <button class="button" type="submit">Save</button>
        </div>
      </div>

      <?php if ($selectedIsSnippet): ?>
        <section class="snippet-usage-panel"><div><strong>Used by <?= count($snippetUsages) ?> page<?= count($snippetUsages) === 1 ? '' : 's' ?></strong><span>Detected from include directives.</span></div><nav><?php foreach ($snippetUsages as $usage): ?><a href="/admin/editor?file=<?= rawurlencode($usage->relativePath) ?>"><span><?= $e($usage->title) ?></span><small><?= $e($usage->relativePath) ?></small></a><?php endforeach; ?><?php if (!$snippetUsages): ?><span class="unused-snippet">This snippet is not currently used.</span><?php endif; ?></nav></section>
      <?php else: ?>
        <section class="metadata-panel" data-metadata-panel>
          <div><label>Title<input type="text" value="<?= $e($editorMeta['title'] ?? '') ?>" data-meta-field="title"></label><label>Description<input type="text" value="<?= $e($editorMeta['description'] ?? '') ?>" data-meta-field="description"></label></div>
          <div><label>Keywords<input type="text" list="known-keywords" value="<?= $e(is_array($editorMeta['keywords'] ?? null) ? implode(', ', $editorMeta['keywords']) : ($editorMeta['keywords'] ?? '')) ?>" data-meta-field="keywords" placeholder="storage, backup, lxc"><datalist id="known-keywords"><?php foreach ($keywordStats as $keyword): ?><option value="<?= $e($keyword['name']) ?>"><?= (int) $keyword['usage_count'] ?> uses</option><?php endforeach; ?></datalist></label><label>Redirect aliases<input type="text" value="<?= $e(is_array($editorMeta['aliases'] ?? null) ? implode(', ', $editorMeta['aliases']) : ($editorMeta['aliases'] ?? '')) ?>" data-meta-field="aliases" placeholder="/old-path"></label></div>
          <div class="metadata-compact"><label>Order<input type="number" value="<?= $e($editorMeta['order'] ?? 100) ?>" data-meta-field="order"></label><label>Visibility<select data-meta-field="visibility"><option value="public" <?= ($editorMeta['visibility'] ?? 'public') === 'public' ? 'selected' : '' ?>>Public</option><option value="private" <?= ($editorMeta['visibility'] ?? '') === 'private' ? 'selected' : '' ?>>Private</option></select></label><label>Type<select data-meta-field="type"><option value="article" <?= ($editorMeta['type'] ?? 'article') === 'article' ? 'selected' : '' ?>>Article</option><option value="runbook" <?= ($editorMeta['type'] ?? '') === 'runbook' ? 'selected' : '' ?>>Runbook</option></select></label><label>Reviewed<input type="date" value="<?= $e($reviewedValue) ?>" data-meta-field="reviewed"></label></div>
          <div class="metadata-compact"><label>Review after<input type="number" min="1" value="<?= $e($editorMeta['review_after'] ?? 180) ?>" data-meta-field="review_after"></label><label class="check-field"><input type="checkbox" data-meta-field="draft" <?= !empty($editorMeta['draft']) ? 'checked' : '' ?>><span>Draft</span></label><label class="check-field"><input type="checkbox" data-meta-field="nav" <?= ($editorMeta['nav'] ?? true) !== false ? 'checked' : '' ?>><span>Navigation</span></label><label class="check-field"><input type="checkbox" data-meta-field="contains_secrets" <?= !empty($editorMeta['contains_secrets']) ? 'checked' : '' ?>><span>Secrets</span></label><label class="check-field"><input type="checkbox" data-meta-field="ai_exclude" <?= !empty($editorMeta['ai_exclude']) ? 'checked' : '' ?>><span>Exclude AI</span></label></div>
        </section>
        <details class="page-insights"><summary><span><strong>Page intelligence</strong><small><?= count($pageIssues) ?> issues · <?= count($pageBacklinks) ?> incoming · <?= count($pageOutbound) ?> outgoing</small></span><span class="nav-chevron" aria-hidden="true"></span></summary><nav><?php foreach (array_slice($pageIssues, 0, 4) as $issue): ?><a href="#contents" class="insight-<?= $e($issue['severity']) ?>"><span><?= $e(ucfirst($issue['severity'])) ?></span><?= $e($issue['message']) ?></a><?php endforeach; ?><?php foreach (array_slice($pageBacklinks, 0, 3) as $page): ?><a href="/admin/editor?file=<?= rawurlencode($page->relativePath) ?>"><span>Linked by</span><?= $e($page->title) ?></a><?php endforeach; ?><?php foreach (array_slice($pageOutbound, 0, 3) as $page): ?><a href="/admin/editor?file=<?= rawurlencode($page->relativePath) ?>"><span>Links to</span><?= $e($page->title) ?></a><?php endforeach; ?><?php if (!$pageIssues && !$pageBacklinks && !$pageOutbound): ?><span class="insight-empty">No issues or page relationships.</span><?php endif; ?></nav></details>
      <?php endif; ?>

      <details class="insert-panel"><summary><span>Insert content</span><small>Directives, page links, and snippets</small><span class="nav-chevron" aria-hidden="true"></span></summary><section class="authoring-toolbar" aria-label="Insert documentation content"><label>Directive<select data-insert-directive><option value="callout">Callout</option><option value="banner">Banner</option><option value="tabs">Tabs</option><option value="filetree">File tree</option><option value="figure">Figure</option><option value="inline-toc">Inline TOC</option><option value="code">Code frame</option><option value="comparison">Comparison</option><option value="details">Details</option></select></label><button type="button" data-insert-directive-button>Insert</button><label>Page link<select data-insert-page><?php foreach ($files as $file): ?><option value="<?= $e($file) ?>"><?= $e($file) ?></option><?php endforeach; ?></select></label><button type="button" data-insert-page-button>Insert</button><label>Snippet<select data-insert-snippet><?php foreach ($snippets as $snippet): ?><option value="<?= $e($snippet['path']) ?>"><?= $e($snippet['title']) ?></option><?php endforeach; ?></select></label><button type="button" data-insert-snippet-button>Include</button></section></details>

      <?php if ($revisions): ?><section class="revision-panel" data-revision-panel><div class="revision-panel-head"><div><strong>Revision history</strong><span>Compare or restore an earlier version.</span></div><button type="button" data-close-revisions>×</button></div><div class="revision-list"><?php foreach ($revisions as $revision): ?><div><span><strong><?= $e(date('M j, Y H:i', $revision['modified'])) ?></strong><small><?= number_format($revision['size'] / 1024, 1) ?> KB</small></span><span class="revision-actions"><button type="button" data-compare-revision="<?= $e($revision['id']) ?>">Compare</button><button type="submit" name="action" value="restore:<?= $e($revision['id']) ?>" data-restore-revision>Restore</button></span></div><?php endforeach; ?></div></section><?php endif; ?>

      <?php if ($gitFileHistory): ?><section class="revision-panel git-history-panel" data-git-history-panel><div class="revision-panel-head"><div><strong>Local Git history</strong><span>Committed versions of this Markdown note. Viewing a snapshot never changes the file.</span></div><button type="button" data-close-git-history>&times;</button></div><div class="revision-list git-note-history"><?php foreach ($gitFileHistory as $commit): ?><div><span><strong><?= $e($commit['subject']) ?></strong><small><code><?= $e($commit['short']) ?></code> &middot; <?= $e($commit['author']) ?> &middot; <?= $e(date('M j, Y H:i', strtotime($commit['date']))) ?></small></span><span class="revision-actions"><button type="button" data-compare-git="<?= $e($commit['hash']) ?>" data-git-label="<?= $e($commit['short'] . ' · ' . $commit['subject']) ?>">Compare</button></span></div><?php endforeach; ?></div></section><?php endif; ?>

      <div class="editor-workspace"><div class="source-pane"><div class="pane-label"><span>Markdown</span><span>Ctrl/Cmd + S</span></div><nav class="editor-outline" data-editor-outline aria-label="Page outline"></nav><label class="sr-only" for="contents">Markdown</label><textarea id="contents" name="contents" spellcheck="true" data-markdown-editor><?= $e($source['contents']) ?></textarea></div><div class="preview-pane"><div class="pane-label"><span>Preview</span><span class="preview-sizes"><button type="button" data-preview-size="desktop" class="active">Desktop</button><button type="button" data-preview-size="tablet">Tablet</button><button type="button" data-preview-size="mobile">Mobile</button></span></div><iframe class="editor-preview" title="Preview" data-preview-frame></iframe></div></div>
    </form>
  </main>
</div>
<dialog class="revision-compare" data-revision-compare><div class="revision-compare-head"><strong>Revision comparison</strong><button type="button" data-close-compare>×</button></div><div><section><span>Selected revision</span><pre data-revision-source></pre></section><section><span>Current editor</span><pre data-current-source></pre></section></div></dialog>
<dialog class="revision-compare" data-git-compare><div class="revision-compare-head"><strong>Local Git note comparison</strong><button type="button" data-close-git-compare>&times;</button></div><div><section><span data-git-source-label>Committed version</span><pre data-git-source></pre></section><section><span>Current editor</span><pre data-git-current-source></pre></section></div></dialog>
</body>
</html>
