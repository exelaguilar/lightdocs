<?php
// Recursive page-tree partial. Receives $tree (nested nodes) and $selected
// (relative path of the open file) from the editor controller.
$render_tree = function (array $nodes, string $selected, bool $nested = false) use (&$render_tree, $e): string {
	$encode = static fn (string $path): string => str_replace('%2F', '/', rawurlencode($path));
	$html = '<ul class="grid gap-0.5 m-0 list-none ' . ($nested ? 'ps-3.5' : 'ps-0') . '">';
	foreach ($nodes as $node) {
		if (($node['type'] ?? '') === 'folder') {
			$landing = !empty($node['landing']) ? '<a class="font-semibold text-primary" href="/admin/editor?file=' . $encode((string) $node['relativePath']) . '">' . $e($node['title']) . '</a>' : $e($node['title']);
			$html .= '<li data-tree-folder><details open><summary class="flex cursor-pointer list-none items-center gap-1.5 rounded-md px-2 py-1.5 text-xs text-sidebar-foreground hover:bg-sidebar-accent">' . $landing . '</summary>' . $render_tree($node['children'] ?? [], $selected, true) . '</details></li>';
			continue;
		}
		$active = ($node['relativePath'] ?? '') === $selected;
		$badge = !empty($node['private']) ? '<span class="inline-flex min-h-6 w-fit items-center justify-center rounded-full px-2 py-0.5 text-xs font-semibold bg-muted text-muted-foreground">Private</span>' : (!empty($node['draft']) ? '<span class="inline-flex min-h-6 w-fit items-center justify-center rounded-full px-2 py-0.5 text-xs font-semibold border border-border bg-transparent text-foreground">Draft</span>' : '');
		$html .= '<li data-tree-page data-search-text="' . $e(mb_strtolower(($node['title'] ?? '') . ' ' . ($node['relativePath'] ?? ''))) . '"><a class="flex cursor-pointer items-center justify-between gap-1.5 rounded-lg border-s-2 px-2.5 py-2 text-xs text-sidebar-foreground transition-colors hover:bg-sidebar-accent ' . ($active ? 'border-primary bg-accent font-semibold text-accent-foreground' : 'border-transparent') . '" data-page-file="' . $e($node['relativePath']) . '" href="/admin/editor?file=' . $encode((string) $node['relativePath']) . '">' . $e($node['title']) . $badge . '</a></li>';
	}
	return $html . '</ul>';
};
echo $render_tree($tree, $selected);
