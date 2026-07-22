const $ = (selector, root = document) => root.querySelector(selector);
const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];

const lightdocsComponents = new Map();
window.lightdocs = {
	register(name, definition) {
		lightdocsComponents.set(name, definition);
		$$(definition.selector).forEach((element) => definition.init(element));
	},
	theme: {
		set(theme) {
			document.documentElement.classList.toggle('dark', theme === 'dark');
		},
	},
};

const toastRegion = document.getElementById('toaster');
function showAdminToast(message, type = 'success') {
	if (!toastRegion) return;
	const toast = document.createElement('div');
	toast.className = `rounded-lg border px-4 py-3 text-sm shadow-lg ${type === 'error' ? 'border-destructive/40 bg-destructive/10 text-destructive' : 'border-border bg-card text-foreground'}`;
	toast.dataset.category = type;
	toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
	toast.textContent = message;
	toastRegion.append(toast);
	window.setTimeout(() => toast.remove(), type === 'error' ? 5000 : 3000);
}

window.lightdocs.register('admin-toast', {
	selector: '[data-admin-toast]',
	init: (notice) => {
		if (notice.dataset.adminToastInitialized) return;
		notice.dataset.adminToastInitialized = 'true';
		showAdminToast(notice.dataset.adminToast || notice.textContent.trim(), notice.dataset.adminToastType || 'success');
	},
});

document.addEventListener('submit', (event) => {
	const form = event.target;
	if (!(form instanceof HTMLFormElement) || !form.dataset.confirm) return;
	if (!window.confirm(form.dataset.confirm)) event.preventDefault();
});

window.lightdocs.register('admin-table-filter', {
	selector: '[data-table-filter]',
	init: (input) => {
		if (input.dataset.tableFilterInitialized) return;
		input.dataset.tableFilterInitialized = 'true';
		const table = document.querySelector(`[data-table="${input.dataset.tableFilter}"]`);
		if (!table) return;
		const typeFilter = document.querySelector(`[data-table-type-filter="${input.dataset.tableFilter}"]`);
		const rows = $$('tbody tr', table);
		const emptyRow = document.createElement('tr');
		emptyRow.className = 'table-filter-empty border-t border-border';
		emptyRow.hidden = true;
		const emptyCell = document.createElement('td');
		emptyCell.className = 'px-6 py-14 text-center text-sm text-muted-foreground';
		emptyCell.colSpan = Math.max(1, $$('thead th', table).length);
		emptyCell.textContent = 'No rows match this filter.';
		emptyRow.append(emptyCell);
		table.querySelector('tbody')?.append(emptyRow);
		const tableSection = table.closest('section');
		let resultCount = tableSection?.querySelector('[data-table-result-count]');
		if (!resultCount && tableSection) {
			const footer = document.createElement('footer');
			footer.className = 'flex items-center justify-between border-t border-border px-5 py-3 text-xs text-muted-foreground max-[640px]:px-4';
			resultCount = document.createElement('span');
			resultCount.dataset.tableResultCount = '';
			resultCount.setAttribute('role', 'status');
			footer.append(resultCount);
			tableSection.append(footer);
		}
		const filter = () => {
			const query = input.value.trim().toLowerCase();
			const type = typeFilter?.value || '';
			let visible = 0;
			rows.forEach((row) => {
				row.hidden = (query !== '' && !row.textContent.toLowerCase().includes(query)) || (type !== '' && row.dataset.extensionType !== type);
				if (!row.hidden) visible++;
			});
			emptyRow.hidden = visible > 0 || (query === '' && type === '');
			const label = table.dataset.tableLabel || input.dataset.tableFilter;
			if (resultCount) resultCount.textContent = `Showing ${visible} of ${rows.length} ${label}`;
		};
		input.addEventListener('input', filter);
		typeFilter?.addEventListener('change', filter);
		filter();
	},
});

window.lightdocs.register('admin-extension-filter', {
	selector: '[data-extension-grid]',
	init: (extensionGrid) => {
		if (extensionGrid.dataset.extensionGridInitialized) return;
		extensionGrid.dataset.extensionGridInitialized = 'true';
		const extensionGridFilter = $('[data-extension-grid-filter]');
		const extensionGridType = $('[data-extension-grid-type]');
		const extensionGridEmpty = $('[data-extension-grid-empty]');
		if (!extensionGridFilter || !extensionGridType) return;
		const filterExtensions = () => {
			const query = extensionGridFilter.value.trim().toLowerCase();
			const type = extensionGridType.value;
			let visible = 0;
			$$('[data-extension-type]', extensionGrid).forEach((card) => {
				const matches =
					(query === '' || card.textContent.toLowerCase().includes(query)) &&
					(type === '' || card.dataset.extensionType === type);
				card.hidden = !matches;
				if (matches) visible++;
			});
			if (extensionGridEmpty) extensionGridEmpty.hidden = visible !== 0;
		};
		extensionGridFilter.addEventListener('input', filterExtensions);
		extensionGridType.addEventListener('change', filterExtensions);
		filterExtensions();
	},
});

window.lightdocs.register('admin-navigation-editor', {
	selector: '[data-navigation-sections]',
	init: (list) => {
		if (list.dataset.navigationEditorInitialized) return;
		list.dataset.navigationEditorInitialized = 'true';
		const addButton = $('[data-navigation-add-section]');
		addButton?.addEventListener('click', () => {
			const index = list.querySelectorAll('.navigation-row').length;
			$('[data-navigation-empty]')?.remove();
			const row = document.createElement('div');
			row.className = 'navigation-row grid lg:grid-cols-[minmax(11rem,1.2fr)_minmax(10rem,1fr)_minmax(12rem,1.3fr)_minmax(7rem,.6fr)_5rem] max-[1024px]:grid-cols-[repeat(auto-fit,minmax(9rem,1fr))] gap-2.5 rounded-lg border border-border bg-muted/20 p-3';
			row.innerHTML = '<label class="grid min-w-0 gap-1.5"><span class="text-xs font-medium text-foreground">Path</span><input class="min-h-8 w-full rounded-md border border-input bg-card px-2 py-1.5 text-xs text-foreground shadow-sm outline-none focus:border-ring focus:ring-2 focus:ring-ring/20" name="sections[' + index + '][path]" required></label><label class="grid min-w-0 gap-1.5"><span class="text-xs font-medium text-foreground">Title</span><input class="min-h-8 w-full rounded-md border border-input bg-card px-2 py-1.5 text-xs text-foreground shadow-sm outline-none focus:border-ring focus:ring-2 focus:ring-ring/20" name="sections[' + index + '][title]"></label><label class="grid min-w-0 gap-1.5"><span class="text-xs font-medium text-foreground">Description</span><input class="min-h-8 w-full rounded-md border border-input bg-card px-2 py-1.5 text-xs text-foreground shadow-sm outline-none focus:border-ring focus:ring-2 focus:ring-ring/20" name="sections[' + index + '][description]"></label><label class="grid min-w-0 gap-1.5"><span class="text-xs font-medium text-foreground">Icon</span><input class="min-h-8 w-full rounded-md border border-input bg-card px-2 py-1.5 text-xs text-foreground shadow-sm outline-none focus:border-ring focus:ring-2 focus:ring-ring/20" name="sections[' + index + '][icon]" value="folder"></label><label class="grid min-w-0 gap-1.5"><span class="text-xs font-medium text-foreground">Order</span><input class="min-h-8 w-full rounded-md border border-input bg-card px-2 py-1.5 text-xs text-foreground shadow-sm outline-none focus:border-ring focus:ring-2 focus:ring-ring/20" type="number" name="sections[' + index + '][order]" value="100"></label>';
			list.append(row);
			row.querySelector('input')?.focus();
		});
	},
});

window.lightdocs.register('admin-media-controls', {
	selector: '[data-media-upload-form]',
	init: (form) => {
		if (form.dataset.mediaControlsInitialized) return;
		form.dataset.mediaControlsInitialized = 'true';
		const file = $('[data-media-upload-file]', form);
		file?.addEventListener('change', () => {
			if (!file.files?.length) return;
			$('[data-media-upload-label]', form).textContent = 'Uploading…';
			$('[data-media-upload-status]', form).textContent = file.files[0].name;
			form.requestSubmit();
		});
		$$('[data-media-rename]').forEach((button) => button.addEventListener('click', () => {
			const dialog = $('[data-media-dialog]');
			$('[data-media-original]', dialog).value = button.dataset.mediaRename;
			$('[data-media-new]', dialog).value = button.dataset.mediaRename;
			dialog.showModal();
		}));
		$$('[data-media-cancel]').forEach((button) => button.addEventListener('click', () => $('[data-media-dialog]')?.close()));
		const previewDialog = $('[data-media-preview-dialog]');
		$$('[data-media-preview]').forEach((button) => button.addEventListener('click', () => {
			const content = $('[data-media-preview-content]', previewDialog);
			const preview = document.createElement(button.dataset.mediaPreviewImage === '1' ? 'img' : 'iframe');
			preview.className = 'max-h-[70vh] max-w-full object-contain';
			preview.src = button.dataset.mediaPreview;
			if (preview.tagName === 'IMG') preview.alt = button.dataset.mediaPreviewName;
			else preview.title = button.dataset.mediaPreviewName;
			content.replaceChildren(preview);
			$('[data-media-preview-title]', previewDialog).textContent = button.dataset.mediaPreviewName;
			previewDialog.showModal();
		}));
		$('[data-media-preview-close]')?.addEventListener('click', () => previewDialog?.close());
	},
});

$('[data-permissions-select-all]')?.addEventListener('click', () => {
	$$('[data-role-permission]').forEach((permission) => {
		permission.checked = true;
	});
});

const adminSidebarToggle = document.getElementById('admin-sidebar-toggle');
const adminThemeToggles = $$('[data-admin-theme-toggle]');
const adminThemeIcons = $$('[data-admin-theme-icon]');
const adminThemes = ['system', 'light', 'dark'];
const adminThemeIconSvgOpen = '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">';
const adminThemeIconMarkup = {
	system: adminThemeIconSvgOpen + '<rect x="2" y="4" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>',
	light: adminThemeIconSvgOpen + '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>',
	dark: adminThemeIconSvgOpen + '<path d="M20 14.5A8.5 8.5 0 0 1 9.5 4a7 7 0 1 0 10.5 10.5Z"/></svg>',
};
function adminTheme() {
	try {
		return localStorage.getItem('lightdocs-theme') || 'system';
	} catch {
		return 'system';
	}
}
function applyAdminTheme(theme) {
	if (theme === 'system') delete document.documentElement.dataset.theme;
	else document.documentElement.dataset.theme = theme;
	const resolvedTheme =
		theme === 'system'
			? (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
			: theme;
	window.lightdocs.theme.set(resolvedTheme);
	try {
		localStorage.setItem('lightdocs-theme', theme);
	} catch {}
	adminThemeIcons.forEach((icon) => {
		icon.innerHTML = adminThemeIconMarkup[theme] || adminThemeIconMarkup.system;
		icon.closest('button')?.setAttribute('data-theme', theme);
	});
	const themeLabel = theme[0].toUpperCase() + theme.slice(1);
	adminThemeToggles.forEach((toggle) => toggle.setAttribute('aria-label', `Color theme: ${themeLabel}. Click to change.`));
}
function syncAdminSidebarToggle() {
	const sidebarEl = document.getElementById('sidebar');
	if (!adminSidebarToggle || !sidebarEl) return;
	adminSidebarToggle.setAttribute('aria-expanded', String(sidebarEl.getAttribute('aria-hidden') !== 'true'));
}
adminSidebarToggle?.addEventListener('click', () => {
	const sidebarEl = document.getElementById('sidebar');
	const contentEl = document.getElementById('admin-content');
	if (sidebarEl) {
		const open = sidebarEl.getAttribute('aria-hidden') !== 'true';
		sidebarEl.setAttribute('aria-hidden', String(open));
		if (contentEl) contentEl.style.marginInlineStart = open ? '0' : '';
	}
	syncAdminSidebarToggle();
	try {
		localStorage.setItem('lightdocs-sidebar', sidebarEl?.getAttribute('aria-hidden') === 'true' ? 'closed' : 'open');
	} catch {}
});
adminThemeToggles.forEach((toggle) => toggle.addEventListener('click', () => {
	applyAdminTheme(
		adminThemes[(adminThemes.indexOf(adminTheme()) + 1) % adminThemes.length],
	);
	preview();
}));

const commandButton = $('[data-admin-command]');
const commandDialog = $('[data-admin-command-dialog]');
const commandInput = $('[data-admin-command-input]');
const openCommandMenu = () => {
	if (!commandDialog) return;
	commandDialog.showModal();
	commandInput?.focus();
};
commandButton?.addEventListener('click', openCommandMenu);
commandDialog?.addEventListener('click', (event) => {
	if (event.target === commandDialog) commandDialog.close();
});
const commandItems = $$('[data-admin-command-item]');
commandInput?.addEventListener('input', () => {
	const query = commandInput.value.trim().toLowerCase();
	commandItems.forEach((item) => {
		const text = `${item.dataset.filter || ''} ${item.dataset.keywords || ''} ${item.textContent}`.toLowerCase();
		item.hidden = query !== '' && !text.includes(query);
	});
});
function wirePopoverTrigger(trigger) {
	const container = trigger?.closest('.relative');
	const popover = container?.querySelector('[data-popover]');
	if (!trigger || !popover) return;
	trigger.addEventListener('click', () => {
		const open = trigger.getAttribute('aria-expanded') === 'true';
		trigger.setAttribute('aria-expanded', String(!open));
		popover.setAttribute('aria-hidden', String(open));
	});
	trigger.addEventListener('keydown', (event) => {
		if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
			event.preventDefault();
			trigger.click();
			if (trigger.getAttribute('aria-expanded') === 'true')
				popover.querySelector('[role="menuitem"]')?.focus();
		}
	});
	popover.addEventListener('keydown', (event) => {
		if (event.key === 'Escape') {
			event.preventDefault();
			trigger.click();
			trigger.focus();
		}
	});
	document.addEventListener('click', (event) => {
		if (!container.contains(event.target)) {
			trigger.setAttribute('aria-expanded', 'false');
			popover.setAttribute('aria-hidden', 'true');
		}
	});
}
wirePopoverTrigger($('#admin-account-trigger'));
wirePopoverTrigger($('#editor-more-trigger'));
document.addEventListener('keydown', (event) => {
	if (event.key === 'Escape') {
		$$('[data-popover][aria-hidden="false"]').forEach((popover) => {
			popover.setAttribute('aria-hidden', 'true');
			popover.closest('.relative')?.querySelector('[aria-expanded="true"]')?.setAttribute('aria-expanded', 'false');
		});
	}
	if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
		event.preventDefault();
		openCommandMenu();
	}
});
applyAdminTheme(adminTheme());
syncAdminSidebarToggle();

const form = $('[data-editor-form]');
const workspace = $('[data-editor-shell]');
const sourcePane = $('[data-source-pane]');
const previewPane = $('[data-preview-pane]');
const frame = $('[data-preview-frame]');
const textarea = $('[data-markdown-editor]');
const previewButton = $('[data-toggle-preview]');
const metadataButton = $('[data-toggle-metadata]');
const metadataPanel = $('[data-metadata-panel]');
const revisionsButton = $('[data-toggle-revisions]');
const revisionsPanel = $('[data-revision-panel]');
const gitHistoryButton = $('[data-toggle-git-history]');
const gitHistoryPanel = $('[data-git-history-panel]');
const saveState = $('[data-save-state]');
const count = $('[data-editor-count]');
const isSnippet = form?.dataset.contentKind === 'snippet';
let timer;
let cleanValue = textarea?.value || '';

function updateOutline() {
	const outline = $('[data-editor-outline]');
	if (!outline || !textarea) return;
	const headings = [...textarea.value.matchAll(/^(#{1,3})\s+(.+)$/gm)];
	if (!headings.length) {
		outline.innerHTML = '';
		return;
	}
	outline.innerHTML = `<select class="min-h-8 max-w-[16rem] rounded-md border border-input bg-card px-2 py-1 text-xs text-foreground" data-outline-select aria-label="Jump to heading"><option value=''>Jump to heading&hellip;</option>${headings
		.map((match) => `<option value="${match.index}">${match[2].replace(/<[^>]*>/g, '')}</option>`)
		.join('')}</select>`;
	const select = $('[data-outline-select]', outline);
	select?.addEventListener('change', () => {
		if (select.value === '') return;
		const index = Number(select.value);
		textarea.focus();
		textarea.setSelectionRange(index, index);
		textarea.scrollTop = Math.max(
			0,
			textarea.value.slice(0, index).split(/\r?\n/).length * 20 - 80,
		);
		select.value = '';
	});
}
function updateCount() {
	if (!textarea || !count) return;
	const body = textarea.value.replace(/^---[\s\S]*?---\s*/, '');
	const words = (body.trim().match(/\S+/g) || []).length;
	const lines = textarea.value.split(/\r?\n/).length;
	count.textContent = `${words} words · ${lines} lines`;
	updateOutline();
}
function markDirty() {
	if (!textarea || !saveState) return;
	const dirty = textarea.value !== cleanValue;
	saveState.textContent = dirty ? 'Unsaved' : 'Saved';
	saveState.classList.toggle('text-destructive', dirty);
}
async function preview() {
	if (!form || !frame) return;
	const data = new FormData(form);
	data.set('action', 'preview');
	data.set('theme', adminTheme());
	try {
		const response = await fetch('/admin/preview', {
			method: 'POST',
			body: data,
			credentials: 'same-origin',
			headers: { Accept: 'text/html' },
		});
		const html = await response.text();
		if (!response.ok)
			throw new Error(
				html
					.replace(/<[^>]+>/g, ' ')
					.replace(/\s+/g, ' ')
					.trim() || 'Preview failed.',
			);
		frame.srcdoc = html;
	} catch (error) {
		frame.srcdoc =
			'<p style="font-family:system-ui;padding:24px;color:#b42318">' +
			String(error.message || 'Preview failed.').replace(
				/[&<>"']/g,
				(character) =>
					({
						'&': '&amp;',
						'<': '&lt;',
						'>': '&gt;',
						'"': '&quot;',
						"'": '&#039;',
					})[character],
			) +
			'</p>';
	}
}
function schedulePreview() {
	clearTimeout(timer);
	timer = setTimeout(preview, 320);
}

let navigatingAway = false;
function flashStatus(text, isError) {
	showAdminToast(text, isError ? 'error' : 'success');
}
function updateRevisions(revisions) {
	const badge = $('[data-toggle-revisions] span');
	if (badge) badge.textContent = String(revisions.length);
	const list = $('[data-revisions-list]');
	if (!list) return;
	list.replaceChildren(
		...revisions.map((revision) => {
			const row = document.createElement('article');
			row.className = 'flex w-full flex-wrap items-center justify-between gap-3 rounded-md border border-border bg-card p-3 text-sm';
			row.setAttribute('role', 'listitem');
			const info = document.createElement('section');
			info.className = 'min-w-0 flex-1';
			const when = document.createElement('h3');
			when.textContent = revision.label;
			const size = document.createElement('p');
			size.textContent = revision.size;
			info.append(when, size);
			const actions = document.createElement('aside');
			actions.className = 'isolate flex w-fit items-stretch';
			const compare = document.createElement('button');
			compare.className = 'inline-flex items-center justify-center rounded-md border border-border px-3 py-1.5 text-sm font-semibold';
			compare.type = 'button';
			compare.dataset.compareRevision = revision.id;
			compare.textContent = 'Compare';
			const restore = document.createElement('button');
			restore.className = compare.className;
			restore.type = 'submit';
			restore.name = 'action';
			restore.value = 'restore:' + revision.id;
			restore.dataset.restoreRevision = 'true';
			restore.textContent = 'Restore';
			actions.append(compare, restore);
			row.append(info, actions);
			return row;
		}),
	);
}
async function saveDocument() {
	if (!form || !textarea) return;
	syncFrontmatter();
	if (saveState) saveState.textContent = 'Saving…';
	const data = new FormData(form);
	data.set('action', 'save');
	try {
		const response = await fetch('/admin/save', { method: 'POST', body: data });
		const result = await response.json();
		if (!response.ok) throw new Error(result.error || 'Save failed.');
		form.elements.hash.value = result.hash;
		cleanValue = textarea.value;
		markDirty();
		if (saveState) saveState.textContent = 'Saved';
		if (result.created) {
			navigatingAway = true;
			location.href = '/admin/editor?file=' + encodeURIComponent(result.file);
			return;
		}
		flashStatus(result.message || 'Saved', false);
		updateRevisions(result.revisions || []);
	} catch (error) {
		if (saveState) saveState.textContent = 'Unsaved';
		flashStatus(error.message, true);
	}
}
function syncFrontmatter() {
	if (!textarea || isSnippet) return;
	const fields = Object.fromEntries(
		$$('[data-meta-field]').map((field) => [
			field.dataset.metaField,
			field.type === 'checkbox' ? field.checked : field.value,
		]),
	);
	let body = textarea.value;
	let lines = [];
	const match = textarea.value.match(/^---\r?\n([\s\S]*?)\r?\n---\r?\n?/);
	if (match) {
		lines = match[1].split(/\r?\n/);
		body = textarea.value.slice(match[0].length);
	}
	const keys = ['title', 'description', 'keywords', 'aliases', 'order', 'visibility', 'type', 'reviewed', 'review_after', 'status', 'publish_at', 'draft', 'nav', 'contains_secrets', 'ai_exclude'];
	const retained = lines.filter((line) => !keys.some((key) => new RegExp(`^${key}:`).test(line)));
	const quote = (value) => `"${String(value).replace(/\\/g, '\\\\').replace(/"/g, '\\"')}"`;
	const list = (value) => JSON.stringify(String(value || '').split(',').map((item) => item.trim()).filter(Boolean));
	const generated = [
		`title: ${quote(fields.title || 'New Page')}`,
		`description: ${quote(fields.description || '')}`,
		list(fields.keywords) !== '[]' ? `keywords: ${list(fields.keywords)}` : '',
		list(fields.aliases) !== '[]' ? `aliases: ${list(fields.aliases)}` : '',
		`order: ${Number(fields.order) || 100}`,
		fields.visibility && fields.visibility !== 'public' ? `visibility: ${fields.visibility}` : '',
		fields.type && fields.type !== 'article' ? `type: ${fields.type}` : '',
		fields.reviewed ? `reviewed: ${quote(fields.reviewed)}` : '',
		Number(fields.review_after) && Number(fields.review_after) !== 180 ? `review_after: ${Number(fields.review_after)}` : '',
		fields.status && fields.status !== 'published' ? `status: ${fields.status}` : '',
		fields.publish_at ? `publish_at: ${quote(fields.publish_at)}` : '',
		fields.status === 'draft' || (!fields.status && fields.draft) ? 'draft: true' : '',
		fields.nav ? '' : 'nav: false',
		fields.contains_secrets ? 'contains_secrets: true' : '',
		fields.ai_exclude ? 'ai_exclude: true' : '',
	];
	textarea.value = `---\n${[...generated, ...retained].filter(Boolean).join('\n')}\n---\n\n${body.replace(/^\s+/, '')}`;
	markDirty();
	updateCount();
	schedulePreview();
}

previewButton?.addEventListener('click', () => {
	const previewOnly = workspace?.classList.toggle('preview-only') || false;
	previewButton.classList.toggle('active', previewOnly);
	previewButton.setAttribute('aria-pressed', String(previewOnly));
	previewButton.textContent = previewOnly ? 'Split view' : 'Preview only';
	if (frame && !frame.srcdoc) preview();
});
metadataButton?.addEventListener('click', () => metadataPanel?.toggleAttribute('hidden'));
revisionsButton?.addEventListener('click', () => revisionsPanel?.toggleAttribute('hidden'));
$('[data-close-revisions]')?.addEventListener('click', () => revisionsPanel?.setAttribute('hidden', ''));
gitHistoryButton?.addEventListener('click', () => gitHistoryPanel?.toggleAttribute('hidden'));
$('[data-close-git-history]')?.addEventListener('click', () => gitHistoryPanel?.setAttribute('hidden', ''));
document.addEventListener('click', (event) => {
	const button = event.target.closest('[data-restore-revision]');
	if (button && !confirm('Restore this revision? The current page will be saved in history first.')) event.preventDefault();
});
$$('[data-meta-field]').forEach((field) => field.addEventListener('change', syncFrontmatter));
$$('[data-meta-field][type="text"], [data-meta-field][type="number"]').forEach((field) =>
	field.addEventListener('input', () => {
		clearTimeout(timer);
		timer = setTimeout(syncFrontmatter, 250);
	}),
);
textarea?.addEventListener('input', () => {
	markDirty();
	updateCount();
	schedulePreview();
	updateGlossarySuggestion();
	updateSlashMenu();
	if (/(^|\s)@image\s*$/.test(textarea.value.slice(0, textarea.selectionStart))) openAssetPicker();
});
form?.addEventListener('submit', (event) => {
	const submitter = event.submitter;
	const action = submitter && submitter.name === 'action' && submitter.value ? submitter.value : 'save';
	if (action !== 'save') {
		syncFrontmatter();
		navigatingAway = true;
		return;
	}
	event.preventDefault();
	saveDocument();
});

const glossaryTerms = (() => {
	try {
		const terms = JSON.parse($('[data-glossary-terms]')?.textContent || '[]');
		return Array.isArray(terms) ? terms : [];
	} catch {
		return [];
	}
})();
const glossarySuggestion = $('[data-glossary-suggestion]');
const glossarySuggestionLabel = $('[data-glossary-suggestion-label]');
const slashMenuRoot = $('[data-slash-menu-root]');
const slashMenu = $('[data-slash-menu]');
let glossaryMatch = null;
let slashMatch = null;
let slashIndex = 0;
function hideGlossarySuggestion() {
	if (glossarySuggestion) glossarySuggestion.hidden = true;
	glossaryMatch = null;
}
function updateGlossarySuggestion() {
	if (!textarea || !glossarySuggestion || textarea.selectionStart !== textarea.selectionEnd) {
		hideGlossarySuggestion();
		return;
	}
	const before = textarea.value.slice(0, textarea.selectionStart);
	if (/\\[\\[[^\\]]*$/.test(before)) {
		hideGlossarySuggestion();
		return;
	}
	const candidates = glossaryTerms
		.flatMap((term) => [term.term, ...(term.aliases || [])].map((label) => ({ term, label })))
		.filter((candidate) => candidate.label)
		.sort((left, right) => right.label.length - left.label.length);
	const match = candidates.find((candidate) => {
		const start = before.length - candidate.label.length;
		return start >= 0 && before.slice(start).toLocaleLowerCase() === candidate.label.toLocaleLowerCase() && (start === 0 || !/[\p{L}\p{N}_-]/u.test(before[start - 1]));
	});
	if (!match) {
		hideGlossarySuggestion();
		return;
	}
	glossaryMatch = { ...match, start: before.length - match.label.length, end: before.length };
	glossarySuggestionLabel.textContent = `Link “${match.label}” to the ${match.term.term} glossary term.`;
	glossarySuggestion.hidden = false;
}
$('[data-insert-glossary-reference]')?.addEventListener('click', () => {
	if (!textarea || !glossaryMatch) return;
	textarea.setRangeText(`[${glossaryMatch.label}](/glossary#${glossaryMatch.term.slug})`, glossaryMatch.start, glossaryMatch.end, 'end');
	textarea.dispatchEvent(new Event('input', { bubbles: true }));
	textarea.focus();
});
$('[data-dismiss-glossary-suggestion]')?.addEventListener('click', hideGlossarySuggestion);

function hideSlashMenu() {
	if (slashMenuRoot) slashMenuRoot.hidden = true;
	slashMatch = null;
	slashIndex = 0;
}
function positionSlashMenu() {
	if (!textarea || !slashMenuRoot || slashMenuRoot.hidden) return;
	slashMenuRoot.style.left = '1rem';
	slashMenuRoot.style.top = '3.25rem';
}
function updateSlashMenu() {
	if (!textarea || !slashMenuRoot || !slashMenu) return;
	const before = textarea.value.slice(0, textarea.selectionStart);
	const match = before.match(/(?:^|\n)\/([a-z-]*)$/i);
	if (!match) {
		hideSlashMenu();
		return;
	}
	const commands = [
		['Heading', '## Heading'],
		['Callout', ':::callout type="info" title="Note"\nWrite the note here.\n:::'],
		['Details', ':::details title="More information"\nAdditional details.\n:::'],
		['Inline table of contents', ':::inline-toc title="In this article"\n:::'],
	].filter(([label]) => label.toLowerCase().includes(match[1].toLowerCase()));
	if (commands.length === 0) {
		hideSlashMenu();
		return;
	}
	slashMatch = { start: textarea.selectionStart - match[1].length - 1, commands };
	slashIndex = Math.min(slashIndex, commands.length - 1);
	slashMenu.replaceChildren(...commands.map(([label, markdown], index) => {
		const button = document.createElement('button');
		button.type = 'button';
		button.className = 'flex w-full items-center rounded-md px-3 py-2 text-left text-sm hover:bg-accent';
		button.dataset.active = String(index === slashIndex);
		button.textContent = label;
		button.addEventListener('click', () => {
			textarea.setRangeText(markdown, slashMatch.start, textarea.selectionStart, 'end');
			hideSlashMenu();
			textarea.dispatchEvent(new Event('input', { bubbles: true }));
			textarea.focus();
		});
		return button;
	}));
	slashMenuRoot.hidden = false;
	positionSlashMenu();
	}
textarea?.addEventListener('keydown', (event) => {
	if (!slashMatch || slashMenuRoot?.hidden) return;
	if (event.key === 'Escape') {
		event.preventDefault();
		hideSlashMenu();
	} else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
		event.preventDefault();
		slashIndex = (slashIndex + (event.key === 'ArrowDown' ? 1 : -1) + slashMatch.commands.length) % slashMatch.commands.length;
		updateSlashMenu();
	} else if (event.key === 'Enter') {
		event.preventDefault();
		const buttons = $$('button', slashMenu);
		buttons[slashIndex]?.click();
	}
});
document.addEventListener('keydown', (event) => {
	if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
		event.preventDefault();
		saveDocument();
	}
});
window.addEventListener('beforeunload', (event) => {
	if (!navigatingAway && textarea && textarea.value !== cleanValue) {
		event.preventDefault();
		event.returnValue = '';
	}
});
function insertMarkdown(markdown) {
	if (!textarea) return;
	const start = textarea.selectionStart;
	const prefix =
		start > 0 && !textarea.value.slice(0, start).endsWith('\n') ? '\n\n' : '';
	textarea.setRangeText(prefix + markdown, start, textarea.selectionEnd, 'end');
	textarea.dispatchEvent(new Event('input'));
	textarea.focus();
}

const assetPicker = $('[data-asset-picker]');
const assetPickerSearch = $('[data-asset-picker-search]');
const assetPickerItems = $$('[data-asset-picker-item]');
let assetPickerSelection = null;

function openAssetPicker() {
	if (!assetPicker || assetPicker.open) return;
	if (textarea) {
		assetPickerSelection = {
			start: textarea.selectionStart,
			end: textarea.selectionEnd,
		};
	}
	assetPicker.showModal();
	assetPickerSearch?.focus();
}

function insertImage(url, name) {
	if (!textarea || url === '') return;
	const selectionStart = assetPickerSelection?.start ?? textarea.selectionStart;
	const selectionEnd = assetPickerSelection?.end ?? textarea.selectionEnd;
	const before = textarea.value.slice(0, selectionStart);
	const trigger = before.match(/(^|\s)@image\s*$/);
	const markdown = `![${name.replace(/\.[^.]+$/, '').replace(/[-_]+/g, ' ')}](${url})`;
	if (trigger) {
		const start = selectionStart - trigger[0].length + trigger[1].length;
		textarea.setRangeText(markdown, start, selectionEnd, 'end');
	} else {
		textarea.setRangeText(markdown, selectionStart, selectionEnd, 'end');
	}
	textarea.dispatchEvent(new Event('input'));
	textarea.focus();
	assetPicker?.close();
}

$('[data-open-asset-picker]')?.addEventListener('click', openAssetPicker);
$('[data-close-asset-picker]')?.addEventListener('click', () => assetPicker?.close());
assetPicker?.addEventListener('click', (event) => {
	if (event.target === assetPicker) assetPicker.close();
});
assetPicker?.addEventListener('close', () => {
	assetPickerSelection = null;
});
assetPickerSearch?.addEventListener('input', () => {
	const query = assetPickerSearch.value.trim().toLowerCase();
	assetPickerItems.forEach((item) => {
		item.hidden = query !== '' && !String(item.dataset.assetSearch || '').includes(query);
	});
});
assetPickerItems.forEach((item) => {
	item.addEventListener('click', () => insertImage(item.dataset.assetUrl || '', item.dataset.assetName || 'Image'));
});
const directiveTemplates = {
	callout: ':::callout type="info" title="Note"\nWrite the note here.\n:::',
	banner: ':::banner type="info"\nImportant information.\n:::',
	tabs: ':::tabs group="example" persist\n:::tab label="First" value="first"\nFirst option.\n:::\n:::tab label="Second" value="second"\nSecond option.\n:::\n:::',
	filetree: ':::filetree\ncontent/\n  index.md\n  guides/\n    example.md\n:::',
	figure:
		':::figure src="/uploads/image.png" alt="Describe the image"\nOptional caption.\n:::',
	'inline-toc': ':::inline-toc title="In this article"\n:::',
	code: ':::code filename="/path/to/file" lines="2-4" numbers\n```text\nExample\n```\n:::',
	comparison:
		':::comparison\n:::before\nPrevious behavior.\n:::\n:::after\nImproved behavior.\n:::\n:::',
	details: ':::details title="More information"\nAdditional details.\n:::',
};
$('[data-insert-directive-button]')?.addEventListener('click', () => {
	const name = $('[data-insert-directive]')?.value;
	insertMarkdown(directiveTemplates[name] || '');
});
$('[data-insert-page-button]')?.addEventListener('click', () => {
	const path = $('[data-insert-page]')?.value;
	if (path) insertMarkdown(`[Link title](/${path})`);
});
$('[data-insert-snippet-button]')?.addEventListener('click', () => {
	const path = $('[data-insert-snippet]')?.value;
	if (path) insertMarkdown(`:::include path="${path}"`);
});
$$('[data-insert-asset]').forEach((button) =>
	button.addEventListener('click', () => {
		const url = button.dataset.insertAsset;
		const image = /\.(?:png|jpe?g|gif|webp)$/i.test(url);
		insertMarkdown(
			image
				? `![Describe the image](${url})`
				: `[${url.split('/').pop()}](${url})`,
		);
	}),
);
$('[data-duplicate-page]')?.addEventListener('click', () => {
	const path = form?.elements.file;
	if (!path || !textarea) return;
	path.value = path.value.replace(/\.md$/, '-copy.md');
	form.elements.hash.value = '';
	cleanValue = '';
	markDirty();
	path.focus();
	path.select();
});
$$('[data-preview-size]').forEach((button) =>
	button.addEventListener('click', () => {
		frame?.setAttribute('data-size', button.dataset.previewSize);
		$$('[data-preview-size]').forEach((candidate) =>
			(candidate.dataset.variant = candidate === button ? 'outline' : 'ghost',
			candidate.setAttribute('aria-pressed', String(candidate === button))),
		);
		preview();
	}),
);
function lineDiff(previous, current) {
	const left = String(previous).replace(/\r\n/g, '\n').split('\n');
	const right = String(current).replace(/\r\n/g, '\n').split('\n');
	const cells = (left.length + 1) * (right.length + 1);
	if (cells > 1500000) {
		const length = Math.max(left.length, right.length);
		return Array.from({ length }, (_, index) => ({
			left:
				index < left.length
					? {
							text: left[index],
							line: index + 1,
							type: left[index] === right[index] ? 'context' : 'remove',
						}
					: null,
			right:
				index < right.length
					? {
							text: right[index],
							line: index + 1,
							type: left[index] === right[index] ? 'context' : 'add',
						}
					: null,
		}));
	}
	const table = Array.from(
		{ length: left.length + 1 },
		() => new Uint32Array(right.length + 1),
	);
	for (let i = left.length - 1; i >= 0; i--)
		for (let j = right.length - 1; j >= 0; j--)
			table[i][j] =
				left[i] === right[j]
					? table[i + 1][j + 1] + 1
					: Math.max(table[i + 1][j], table[i][j + 1]);
	const operations = [];
	let i = 0,
		j = 0;
	while (i < left.length || j < right.length) {
		if (i < left.length && j < right.length && left[i] === right[j]) {
			operations.push({
				type: 'context',
				left: { text: left[i], line: i + 1, type: 'context' },
				right: { text: right[j], line: j + 1, type: 'context' },
			});
			i++;
			j++;
			continue;
		}
		if (
			i < left.length &&
			(j >= right.length || table[i + 1][j] >= table[i][j + 1])
		) {
			operations.push({
				type: 'remove',
				value: { text: left[i], line: i + 1 },
			});
			i++;
		} else {
			operations.push({ type: 'add', value: { text: right[j], line: j + 1 } });
			j++;
		}
	}
	const rows = [];
	for (let cursor = 0; cursor < operations.length; ) {
		if (operations[cursor].type === 'context') {
			rows.push(operations[cursor]);
			cursor++;
			continue;
		}
		const removed = [],
			added = [];
		while (
			cursor < operations.length &&
			operations[cursor].type !== 'context'
		) {
			const operation = operations[cursor++];
			(operation.type === 'remove' ? removed : added).push(operation.value);
		}
		for (let index = 0; index < Math.max(removed.length, added.length); index++)
			rows.push({
				left: removed[index] ? { ...removed[index], type: 'remove' } : null,
				right: added[index] ? { ...added[index], type: 'add' } : null,
			});
	}
	return rows;
}
function compactDiffRows(rows, context = 3) {
	const changed = [];
	rows.forEach((row, index) => {
		if (row.left?.type === 'remove' || row.right?.type === 'add')
			changed.push(index);
	});
	if (!changed.length) return rows;
	const keep = new Set();
	for (const index of changed)
		for (let offset = -context; offset <= context; offset++) {
			const candidate = index + offset;
			if (candidate >= 0 && candidate < rows.length) keep.add(candidate);
		}
	const compact = [];
	let cursor = 0;
	while (cursor < rows.length) {
		if (keep.has(cursor)) {
			compact.push(rows[cursor++]);
			continue;
		}
		const start = cursor;
		while (cursor < rows.length && !keep.has(cursor)) cursor++;
		const skipped = cursor - start;
		const label = `… ${skipped} unchanged line${skipped === 1 ? '' : 's'} …`;
		compact.push({
			left: { text: label, line: '', type: 'skip' },
			right: { text: label, line: '', type: 'skip' },
		});
	}
	return compact;
}
function renderLineDiff(previous, current, previousTarget, currentTarget) {
	if (!previousTarget || !currentTarget) return;
	const fullRows = lineDiff(previous, current);
	const rows = compactDiffRows(fullRows);
	const render = (target, side) => {
		const fragment = document.createDocumentFragment();
		for (const row of rows) {
			const item = row[side];
			const line = document.createElement('span');
			const tone = item?.type === 'add'
				? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
				: item?.type === 'remove'
					? 'bg-destructive/10 text-destructive'
					: item?.type === 'skip'
						? 'bg-muted text-muted-foreground italic'
						: 'text-foreground';
			line.className = `diff-line block min-h-5 whitespace-pre-wrap break-words border-b border-border/50 px-3 py-1 font-mono text-xs leading-5 ${tone}`;
			line.dataset.line = item?.line ? String(item.line) : '';
			line.textContent = item?.text || ' ';
			fragment.append(line);
		}
		target.replaceChildren(fragment);
	};
	render(previousTarget, 'left');
	render(currentTarget, 'right');
	const additions = fullRows.filter((row) => row.right?.type === 'add').length;
	const removals = fullRows.filter((row) => row.left?.type === 'remove').length;
	const dialog = previousTarget.closest('dialog');
	const title = dialog?.querySelector('header h2');
	if (title) {
		title.dataset.baseTitle ||= title.textContent;
		title.textContent = `${title.dataset.baseTitle} · +${additions} →${removals}`;
	}
}
const compareDialog = $('[data-revision-compare]');
document.addEventListener('click', async (event) => {
	const button = event.target.closest('[data-compare-revision]');
	if (!button) return;
	try {
		const file = form?.elements.file.value;
		const response = await fetch(
			`/admin/revision?file=${encodeURIComponent(file)}&id=${encodeURIComponent(button.dataset.compareRevision)}`,
		);
		const result = await response.json();
		if (!response.ok)
			throw new Error(result.error || 'Could not load revision.');
		renderLineDiff(
			result.contents,
			textarea.value,
			$('[data-revision-source]'),
			$('[data-current-source]'),
		);
		compareDialog.showModal();
	} catch (error) {
		alert(error.message);
	}
});
$('[data-close-compare]')?.addEventListener('click', () =>
	compareDialog?.close(),
);
const gitCompareDialog = $('[data-git-compare]');
$$('[data-compare-git]').forEach((button) =>
	button.addEventListener('click', async () => {
		try {
			const file = form?.elements.file.value;
			if (!file) throw new Error('This note path is unavailable.');
			const response = await fetch(
				`/admin/local-git/file?file=${encodeURIComponent(file)}&commit=${encodeURIComponent(button.dataset.compareGit)}`,
			);
			const result = await response.json();
			if (!response.ok)
				throw new Error(result.error || 'Could not load the committed note.');
			renderLineDiff(
				result.contents,
				textarea.value,
				$('[data-git-source]'),
				$('[data-git-current-source]'),
			);
			$('[data-git-source-label]').textContent =
				button.dataset.gitLabel || 'Committed version';
			gitCompareDialog.showModal();
		} catch (error) {
			alert(error.message);
		}
	}),
);
$('[data-close-git-compare]')?.addEventListener('click', () =>
	gitCompareDialog?.close(),
);
async function uploadIntoEditor(file) {
	if (!file || !form || !textarea) return;
	const data = new FormData();
	data.set('csrf_token', form.elements.csrf_token.value);
	data.set('asset', file);
	textarea.classList.add('border-primary');
	if (saveState) saveState.textContent = 'Uploading asset...';
	try {
		const response = await fetch('/admin/upload', {
			method: 'POST',
			body: data,
		});
		const result = await response.json();
		if (!response.ok) throw new Error(result.error || 'Upload failed.');
		const image = file.type.startsWith('image/');
		const alt = file.name.replace(/\.[^.]+$/, '').replace(/[-_]+/g, ' ');
		const markdown = image
			? `![${alt}](${result.url})`
			: `[${file.name}](${result.url})`;
		const start = textarea.selectionStart;
		textarea.setRangeText(markdown, start, textarea.selectionEnd, 'end');
		textarea.dispatchEvent(new Event('input'));
	} catch (error) {
		alert(error.message);
	} finally {
		textarea.classList.remove('border-primary');
		markDirty();
	}
}
textarea?.addEventListener('dragover', (event) => {
	if ([...event.dataTransfer.types].includes('Files')) {
		event.preventDefault();
		textarea.classList.add('border-primary', 'border-dashed', 'bg-muted');
	}
});
textarea?.addEventListener('dragleave', () =>
	textarea.classList.remove('border-primary', 'border-dashed', 'bg-muted'),
);
textarea?.addEventListener('drop', (event) => {
	const file = event.dataTransfer.files[0];
	if (file) {
		event.preventDefault();
		textarea.classList.remove('border-primary', 'border-dashed', 'bg-muted');
		uploadIntoEditor(file);
	}
});
textarea?.addEventListener('paste', (event) => {
	const file = [...event.clipboardData.files].find((candidate) =>
		candidate.type.startsWith('image/'),
	);
	if (file) {
		event.preventDefault();
		uploadIntoEditor(file);
	}
});
let draggedPage = null;
$$('[data-tree-page]').forEach((item) => {
	item.addEventListener('dragstart', (event) => {
		draggedPage = item;
		item.classList.add('bg-muted');
		event.dataTransfer.effectAllowed = 'move';
	});
	item.addEventListener('dragend', () => {
		item.classList.remove('bg-muted');
		draggedPage = null;
		$$('[data-tree-page]').forEach((page) =>
			page.classList.remove('border-primary'),
		);
	});
	item.addEventListener('dragover', (event) => {
		if (
			draggedPage &&
			draggedPage.parentElement === item.parentElement &&
			draggedPage !== item
		) {
			event.preventDefault();
			item.classList.add('border-primary');
		}
	});
	item.addEventListener('dragleave', () => item.classList.remove('border-primary'));
	item.addEventListener('drop', async (event) => {
		if (
			!draggedPage ||
			draggedPage.parentElement !== item.parentElement ||
			draggedPage === item
		)
			return;
		event.preventDefault();
		item.classList.remove('border-primary');
		const box = item.getBoundingClientRect();
		item.parentElement.insertBefore(
			draggedPage,
			event.clientY < box.top + box.height / 2 ? item : item.nextSibling,
		);
		const files = [...item.parentElement.children]
			.map((child) => $('[data-page-file]', child)?.dataset.pageFile)
			.filter(Boolean);
		const data = new FormData();
		data.set('csrf_token', form.elements.csrf_token.value);
		files.forEach((file) => data.append('files[]', file));
		if (saveState) saveState.textContent = 'Saving navigation…';
		try {
			const response = await fetch('/admin/reorder', {
				method: 'POST',
				body: data,
			});
			const result = await response.json();
			if (!response.ok) throw new Error(result.error || 'Reorder failed.');
			if (saveState) saveState.textContent = 'Navigation updated';
			setTimeout(() => {
				if (saveState) saveState.textContent = 'Saved';
			}, 1200);
		} catch (error) {
			alert(error.message);
			location.reload();
		}
	});
});
updateCount();
preview();

const editorShell = $('[data-editor-shell]');
const contentToggle = $('[data-toggle-content]');
const contentFilter = $('[data-content-filter]');
function setContentPanel(open) {
	if (!editorShell) return;
	editorShell.classList.toggle('content-collapsed', !open);
	contentToggle?.setAttribute('aria-expanded', String(open));
	const panel = $('#studio-content-panel');
	panel?.setAttribute('aria-hidden', String(!open));
	try {
		localStorage.setItem('lightdocs-studio-content', open ? 'open' : 'closed');
	} catch {}
}
contentToggle?.addEventListener('click', () =>
	setContentPanel(editorShell?.classList.contains('content-collapsed')),
);
$$('[data-close-content]').forEach((button) =>
	button.addEventListener('click', () => setContentPanel(false)),
);
try {
	if (
		localStorage.getItem('lightdocs-studio-content') === 'closed' &&
		matchMedia('(min-width:841px)').matches
	)
		setContentPanel(false);
} catch {}
if (matchMedia('(max-width:840px)').matches) setContentPanel(false);
function filterContent() {
	if (!contentFilter) return;
	const query = contentFilter.value.trim().toLowerCase();
	$$('[data-tree-page]').forEach((item) => {
		item.hidden =
			query !== '' && !String(item.dataset.searchText || '').includes(query);
	});
	$$('[data-tree-folder]').forEach((folder) => {
		folder.hidden =
			query !== '' && !$('[data-tree-page]:not([hidden])', folder);
	});
}
contentFilter?.addEventListener('input', filterContent);
contentFilter?.addEventListener('keydown', (event) => {
	if (event.key === 'Escape') {
		contentFilter.value = '';
		filterContent();
		contentFilter.blur();
	}
});
document.addEventListener('keydown', (event) => {
	const target = event.target;
	if (
		event.key === '/' &&
		!event.ctrlKey &&
		!event.metaKey &&
		!event.altKey &&
		target instanceof HTMLElement &&
		!target.matches('input,textarea,select,[contenteditable=true]')
	) {
		event.preventDefault();
		setContentPanel(true);
		contentFilter?.focus();
	}
});

const adminTooltipTargets = $$('[data-tooltip], button[aria-label], a[aria-label], summary[aria-label]');
if (adminTooltipTargets.length) {
	const tooltipEl = document.createElement('div');
	tooltipEl.className =
		'pointer-events-none fixed z-[300] hidden max-w-[min(16rem,calc(100vw-2rem))] rounded-md border border-border bg-popover px-2.5 py-1.5 text-[11px] font-medium leading-4 text-popover-foreground opacity-0 shadow-lg transition-[opacity,transform] duration-150 ease-out translate-y-1';
	tooltipEl.setAttribute('role', 'tooltip');
	tooltipEl.id = 'admin-tooltip';
	document.body.appendChild(tooltipEl);
	let tooltipTarget = null;
	let tooltipTimer = 0;
	function positionAdminTooltip() {
		if (!tooltipTarget) return;
		const rect = tooltipTarget.getBoundingClientRect();
		const preferred = tooltipTarget.dataset.tooltipPlacement || tooltipTarget.dataset.side || 'top';
		const gap = 8;
		const tipRect = tooltipEl.getBoundingClientRect();
		const sides = preferred === 'auto' ? ['top', 'bottom', 'right', 'left'] : [preferred, 'top', 'bottom', 'right', 'left'];
		const side = sides.find((candidate, index) => sides.indexOf(candidate) === index && ((candidate === 'top' && rect.top >= tipRect.height + gap) || (candidate === 'bottom' && window.innerHeight - rect.bottom >= tipRect.height + gap) || (candidate === 'left' && rect.left >= tipRect.width + gap) || (candidate === 'right' && window.innerWidth - rect.right >= tipRect.width + gap))) || 'top';
		let top = side === 'bottom' ? rect.bottom + gap : side === 'left' || side === 'right' ? rect.top + rect.height / 2 - tipRect.height / 2 : rect.top - tipRect.height - gap;
		let left = side === 'left' ? rect.left - tipRect.width - gap : side === 'right' ? rect.right + gap : rect.left + rect.width / 2 - tipRect.width / 2;
		left = Math.max(4, Math.min(left, window.innerWidth - tipRect.width - 4));
		top = Math.max(4, Math.min(top, window.innerHeight - tipRect.height - 4));
		tooltipEl.dataset.placement = side;
		tooltipEl.style.top = `${top}px`;
		tooltipEl.style.left = `${left}px`;
	}
	function showAdminTooltip(target) {
		clearTimeout(tooltipTimer);
		const label = target.dataset.tooltip || target.getAttribute('aria-label') || target.getAttribute('title');
		if (!label || target.closest('[hidden]')) return;
		tooltipTarget = target;
		tooltipEl.textContent = label;
		target.setAttribute('aria-describedby', tooltipEl.id);
		tooltipEl.classList.remove('hidden');
		requestAnimationFrame(() => {
			if (tooltipTarget !== target) return;
			positionAdminTooltip();
			tooltipEl.classList.remove('opacity-0', 'translate-y-1');
		});
	}
	function hideAdminTooltip() {
		clearTimeout(tooltipTimer);
		tooltipTimer = window.setTimeout(() => {
			tooltipTarget?.removeAttribute('aria-describedby');
			tooltipTarget = null;
			tooltipEl.classList.add('opacity-0', 'translate-y-1');
			window.setTimeout(() => tooltipEl.classList.add('hidden'), 150);
		}, 60);
	}
	adminTooltipTargets.forEach((target) => {
		target.addEventListener('mouseenter', () => showAdminTooltip(target));
		target.addEventListener('mouseleave', hideAdminTooltip);
		target.addEventListener('focus', () => showAdminTooltip(target));
		target.addEventListener('blur', hideAdminTooltip);
		target.addEventListener('click', hideAdminTooltip);
	});
	window.addEventListener('scroll', () => tooltipTarget && positionAdminTooltip(), true);
	document.addEventListener('keydown', (event) => {
		if (event.key === 'Escape') hideAdminTooltip();
	});
}

$$('[data-admin-broadcast]').forEach((banner) => {
	const storageKey = 'lightdocs-admin-broadcast-dismissed:' + (banner.dataset.adminBroadcastId || 'default');
	try {
		if (localStorage.getItem(storageKey) === '1') {
			banner.remove();
			return;
		}
	} catch {}

	banner.querySelector('[data-admin-broadcast-dismiss]')?.addEventListener('click', () => {
		try {
			localStorage.setItem(storageKey, '1');
		} catch {}
		banner.remove();
	});
});
