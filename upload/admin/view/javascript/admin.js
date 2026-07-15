const $ = (selector, root = document) => root.querySelector(selector);
const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];

const toastRegion = document.createElement('div');
toastRegion.className = 'ui-toast-region';
toastRegion.setAttribute('aria-live', 'polite');
toastRegion.setAttribute('aria-atomic', 'true');
document.body.append(toastRegion);
let toastTimer = 0;
function showAdminToast(message, type = 'success') {
	clearTimeout(toastTimer);
	toastRegion.replaceChildren();
	const toast = document.createElement('div');
	toast.className = `ui-toast is-${type}`;
	toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
	toast.textContent = message;
	toastRegion.append(toast);
	requestAnimationFrame(() => toast.classList.add('is-visible'));
	toastTimer = setTimeout(() => {
		toast.classList.remove('is-visible');
		setTimeout(() => toast.remove(), 180);
	}, 2600);
}

const tooltip = document.createElement('div');
tooltip.className = 'ui-tooltip';
tooltip.id = 'admin-ui-tooltip';
tooltip.setAttribute('role', 'tooltip');
tooltip.hidden = true;
document.body.append(tooltip);
let tooltipTarget = null;
function hideTooltip() {
	tooltip.hidden = true;
	tooltipTarget?.removeAttribute('aria-describedby');
	tooltipTarget = null;
}
function showTooltip(target) {
	const text = target.dataset.tooltip || target.getAttribute('aria-label');
	if (!text || target.closest('[hidden]')) return;
	tooltipTarget = target;
	tooltip.textContent = text;
	tooltip.hidden = false;
	target.setAttribute('aria-describedby', tooltip.id);
	const bounds = target.getBoundingClientRect();
	const tooltipBounds = tooltip.getBoundingClientRect();
	const gap = 8;
	const leftPlacement = target.dataset.tooltipPlacement === 'left';
	tooltip.classList.toggle('is-left', leftPlacement);
	if (leftPlacement) {
		tooltip.style.top = `${Math.min(innerHeight - gap - tooltipBounds.height / 2, Math.max(gap + tooltipBounds.height / 2, bounds.top + bounds.height / 2))}px`;
		tooltip.style.left = `${Math.max(gap + tooltipBounds.width, bounds.left - gap)}px`;
		return;
	}
	const top =
		bounds.bottom + gap + tooltipBounds.height <= innerHeight - gap
			? bounds.bottom + gap
			: Math.max(gap, bounds.top - gap - tooltipBounds.height);
	const left = Math.min(
		innerWidth - gap - tooltipBounds.width / 2,
		Math.max(gap + tooltipBounds.width / 2, bounds.left + bounds.width / 2),
	);
	tooltip.style.top = `${top}px`;
	tooltip.style.left = `${left}px`;
}
$$('[data-tooltip],button[aria-label]').forEach((target) => {
	target.addEventListener('mouseenter', () => showTooltip(target));
	target.addEventListener('mouseleave', hideTooltip);
	target.addEventListener('focus', () => showTooltip(target));
	target.addEventListener('blur', hideTooltip);
});
addEventListener('scroll', hideTooltip, { passive: true });
addEventListener('resize', hideTooltip);

document.addEventListener('submit', (event) => {
	const form = event.target;
	if (!(form instanceof HTMLFormElement) || !form.dataset.confirm) return;
	if (!window.confirm(form.dataset.confirm)) event.preventDefault();
});

$$('[data-table-filter]').forEach((input) => {
	const table = document.querySelector(`[data-table="${input.dataset.tableFilter}"]`);
	if (!table) return;
	const rows = $$('tbody tr', table);
	const emptyRow = document.createElement('tr');
	emptyRow.className = 'table-filter-empty';
	emptyRow.hidden = true;
	const emptyCell = document.createElement('td');
	emptyCell.colSpan = Math.max(1, $$('thead th', table).length);
	emptyCell.textContent = 'No rows match this filter.';
	emptyRow.append(emptyCell);
	table.querySelector('tbody')?.append(emptyRow);
	const status = document.createElement('span');
	status.className = 'table-filter-status';
	status.setAttribute('role', 'status');
	const toolbar = input.closest('.table-toolbar');
	if (toolbar) toolbar.insertBefore(status, input);
	const filter = () => {
		const query = input.value.trim().toLowerCase();
		let visible = 0;
		rows.forEach((row) => {
			row.hidden = query !== '' && !row.textContent.toLowerCase().includes(query);
			if (!row.hidden) visible++;
		});
		emptyRow.hidden = query === '' || visible > 0;
		status.textContent = query === '' ? `${rows.length} total` : `${visible} of ${rows.length} shown`;
	};
	input.addEventListener('input', filter);
	filter();
});

$('[data-permissions-select-all]')?.addEventListener('click', () => {
	$$('[data-role-permission]').forEach((permission) => {
		permission.checked = true;
	});
});

const adminSidebarToggle = $('[data-admin-sidebar-toggle]');
const adminThemeToggles = $$('[data-admin-theme-toggle]');
const adminThemeIcons = $$('[data-admin-theme-icon]');
const adminThemeLabel = $('[data-admin-theme-label]');
const adminThemes = ['system', 'light', 'dark'];
const adminThemeIconMarkup = {
	system: '<svg viewBox="0 0 24 24"><path d="M12 3a9 9 0 0 0 0 18Z" fill="currentColor" stroke="none"/><circle cx="12" cy="12" r="9"/></svg>',
	light: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>',
	dark: '<svg viewBox="0 0 24 24"><path d="M20 14.5A8.5 8.5 0 0 1 9.5 4a7 7 0 1 0 10.5 10.5Z"/></svg>',
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
	try {
		localStorage.setItem('lightdocs-theme', theme);
	} catch {}
	if (adminThemeLabel)
		adminThemeLabel.textContent = theme[0].toUpperCase() + theme.slice(1);
	adminThemeIcons.forEach((icon) => {
		icon.innerHTML = adminThemeIconMarkup[theme] || adminThemeIconMarkup.system;
		icon.closest('button')?.setAttribute('data-theme', theme);
	});
	const themeLabel = theme[0].toUpperCase() + theme.slice(1);
	adminThemeToggles.forEach((toggle) => toggle.setAttribute('aria-label', `Color theme: ${themeLabel}. Click to change.`));
}
function syncAdminSidebar() {
	const collapsed = document.documentElement.classList.contains(
		'admin-sidebar-collapsed',
	);
	adminSidebarToggle?.setAttribute('aria-expanded', String(!collapsed));
	adminSidebarToggle?.setAttribute(
		'aria-label',
		collapsed ? 'Expand navigation' : 'Collapse navigation',
	);
	adminSidebarToggle?.setAttribute(
		'title',
		collapsed ? 'Expand navigation' : 'Collapse navigation',
	);
}
adminSidebarToggle?.addEventListener('click', () => {
	document.documentElement.classList.toggle('admin-sidebar-collapsed');
	try {
		localStorage.setItem(
			'lightdocs-admin-sidebar',
			document.documentElement.classList.contains('admin-sidebar-collapsed')
				? 'collapsed'
				: 'expanded',
		);
	} catch {}
	syncAdminSidebar();
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
commandInput?.addEventListener('input', () => {
	const query = commandInput.value.trim().toLowerCase();
	$$('[data-admin-command-item]', commandDialog).forEach((item) => {
		item.hidden = query !== '' && !item.textContent.toLowerCase().includes(query);
	});
});
commandDialog?.addEventListener('click', (event) => {
	if (event.target === commandDialog) commandDialog.close();
});
document.addEventListener('keydown', (event) => {
	if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
		event.preventDefault();
		openCommandMenu();
	}
});
applyAdminTheme(adminTheme());
syncAdminSidebar();

const accountMenu = $('.admin-account-menu');
document.addEventListener('click', (event) => {
	if (accountMenu?.open && !accountMenu.contains(event.target)) accountMenu.open = false;
});
document.addEventListener('keydown', (event) => {
	if (event.key === 'Escape' && accountMenu?.open) accountMenu.open = false;
});

const form = $('[data-editor-form]');
const workspace = $('.editor-workspace');
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
	outline.innerHTML = headings.length
		? headings
				.map(
					(match) =>
						`<button type="button" data-outline-index="${match.index}" class="outline-level-${match[1].length}">${match[2].replace(/<[^>]*>/g, '')}</button>`,
				)
				.join('')
		: '<span>No headings yet</span>';
	$$('[data-outline-index]', outline).forEach((button) =>
		button.addEventListener('click', () => {
			const index = Number(button.dataset.outlineIndex);
			textarea.focus();
			textarea.setSelectionRange(index, index);
			textarea.scrollTop = Math.max(
				0,
				textarea.value.slice(0, index).split(/\r?\n/).length * 20 - 80,
			);
		}),
	);
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
	saveState.closest('.studio-status')?.classList.toggle('dirty', dirty);
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
	const main = $('.editor-main');
	if (!main) return;
	showAdminToast(text, isError ? 'error' : 'success');
	$$('[data-flash]', main).forEach((note) => note.remove());
	const note = document.createElement('p');
	note.className = isError ? 'form-error' : 'form-success';
	note.dataset.flash = 'true';
	note.setAttribute('role', isError ? 'alert' : 'status');
	note.textContent = text;
	main.prepend(note);
	if (!isError) setTimeout(() => note.remove(), 4000);
}
function updateRevisions(revisions) {
	const badge = $('[data-toggle-revisions] span');
	if (badge) badge.textContent = String(revisions.length);
	const list = $('[data-revision-panel] .revision-list');
	if (!list) return;
	list.replaceChildren(
		...revisions.map((revision) => {
			const row = document.createElement('div');
			const info = document.createElement('span');
			const when = document.createElement('strong');
			when.textContent = revision.label;
			const size = document.createElement('small');
			size.textContent = revision.size;
			info.append(when, size);
			const actions = document.createElement('span');
			actions.className = 'revision-actions';
			const compare = document.createElement('button');
			compare.type = 'button';
			compare.dataset.compareRevision = revision.id;
			compare.textContent = 'Compare';
			const restore = document.createElement('button');
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
	let source = textarea.value;
	let body = source;
	let lines = [];
	const match = source.match(/^---\r?\n([\s\S]*?)\r?\n---\r?\n?/);
	if (match) {
		lines = match[1].split(/\r?\n/);
		body = source.slice(match[0].length);
	}
	const keys = [
		'title',
		'description',
		'keywords',
		'aliases',
		'order',
		'visibility',
		'type',
		'reviewed',
		'review_after',
		'status',
		'publish_at',
		'draft',
		'nav',
		'contains_secrets',
		'ai_exclude',
	];
	const retained = lines.filter(
		(line) => !keys.some((key) => new RegExp(`^${key}:`).test(line)),
	);
	const quote = (value) =>
		`"${String(value).replace(/\\/g, '\\\\').replace(/"/g, '\\"')}"`;
	const list = (value) =>
		JSON.stringify(
			String(value || '')
				.split(',')
				.map((item) => item.trim())
				.filter(Boolean),
		);
	const generated = [
		`title: ${quote(fields.title || 'New Page')}`,
		`description: ${quote(fields.description || '')}`,
		list(fields.keywords) !== '[]' ? `keywords: ${list(fields.keywords)}` : '',
		list(fields.aliases) !== '[]' ? `aliases: ${list(fields.aliases)}` : '',
		`order: ${Number(fields.order) || 100}`,
		fields.visibility && fields.visibility !== 'public'
			? `visibility: ${fields.visibility}`
			: '',
		fields.type && fields.type !== 'article' ? `type: ${fields.type}` : '',
		fields.reviewed ? `reviewed: ${quote(fields.reviewed)}` : '',
		Number(fields.review_after) && Number(fields.review_after) !== 180
			? `review_after: ${Number(fields.review_after)}`
			: '',
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
metadataButton?.addEventListener('click', () => {
	metadataPanel?.classList.toggle('open');
	metadataButton.classList.toggle('active');
});
revisionsButton?.addEventListener('click', () =>
	revisionsPanel?.classList.toggle('open'),
);
$('[data-close-revisions]')?.addEventListener('click', () =>
	revisionsPanel?.classList.remove('open'),
);
gitHistoryButton?.addEventListener('click', () =>
	gitHistoryPanel?.classList.toggle('open'),
);
$('[data-close-git-history]')?.addEventListener('click', () =>
	gitHistoryPanel?.classList.remove('open'),
);
const studioMenus = $$('details.editor-more,details.studio-nav-menu');
studioMenus.forEach((menu) =>
	menu.addEventListener('toggle', () => {
		if (!menu.open) return;
		studioMenus.forEach((other) => {
			if (other !== menu) other.open = false;
		});
	}),
);
document.addEventListener('click', (event) =>
	studioMenus.forEach((menu) => {
		if (menu.open && !menu.contains(event.target)) menu.open = false;
	}),
);
document.addEventListener('click', (event) => {
	const button = event.target.closest('[data-restore-revision]');
	if (
		button &&
		!confirm(
			'Restore this revision? The current page will be saved in history first.',
		)
	)
		event.preventDefault();
});
$$('[data-meta-field]').forEach((field) =>
	field.addEventListener('change', syncFrontmatter),
);
$$('[data-meta-field][type="text"], [data-meta-field][type="number"]').forEach(
	(field) =>
		field.addEventListener('input', () => {
			clearTimeout(timer);
			timer = setTimeout(syncFrontmatter, 250);
		}),
);
textarea?.addEventListener('input', () => {
	markDirty();
	updateCount();
	schedulePreview();
	if (/(^|\s)@image\s*$/.test(textarea.value.slice(0, textarea.selectionStart))) openAssetPicker();
});
form?.addEventListener('submit', (event) => {
	const submitter = event.submitter;
	const action =
		submitter && submitter.name === 'action' && submitter.value
			? submitter.value
			: 'save';
	if (action !== 'save') {
		syncFrontmatter();
		navigatingAway = true;
		return;
	}
	event.preventDefault();
	saveDocument();
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
			candidate.classList.toggle('active', candidate === button),
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
			line.className = `diff-line ${item ? `diff-${item.type}` : 'diff-empty'}`;
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
	const title = dialog?.querySelector('.revision-compare-head strong');
	if (title) {
		title.dataset.baseTitle ||= title.textContent;
		title.textContent = `${title.dataset.baseTitle} · +${additions} −${removals}`;
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
	data.set('csrf', form.elements.csrf.value);
	data.set('asset', file);
	textarea.classList.add('uploading');
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
		textarea.classList.remove('uploading');
		markDirty();
	}
}
textarea?.addEventListener('dragover', (event) => {
	if ([...event.dataTransfer.types].includes('Files')) {
		event.preventDefault();
		textarea.classList.add('drag-active');
	}
});
textarea?.addEventListener('dragleave', () =>
	textarea.classList.remove('drag-active'),
);
textarea?.addEventListener('drop', (event) => {
	const file = event.dataTransfer.files[0];
	if (file) {
		event.preventDefault();
		textarea.classList.remove('drag-active');
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
		item.classList.add('dragging');
		event.dataTransfer.effectAllowed = 'move';
	});
	item.addEventListener('dragend', () => {
		item.classList.remove('dragging');
		draggedPage = null;
		$$('[data-tree-page]').forEach((page) =>
			page.classList.remove('drag-over'),
		);
	});
	item.addEventListener('dragover', (event) => {
		if (
			draggedPage &&
			draggedPage.parentElement === item.parentElement &&
			draggedPage !== item
		) {
			event.preventDefault();
			item.classList.add('drag-over');
		}
	});
	item.addEventListener('dragleave', () => item.classList.remove('drag-over'));
	item.addEventListener('drop', async (event) => {
		if (
			!draggedPage ||
			draggedPage.parentElement !== item.parentElement ||
			draggedPage === item
		)
			return;
		event.preventDefault();
		item.classList.remove('drag-over');
		const box = item.getBoundingClientRect();
		item.parentElement.insertBefore(
			draggedPage,
			event.clientY < box.top + box.height / 2 ? item : item.nextSibling,
		);
		const files = [...item.parentElement.children]
			.map((child) => $('[data-page-file]', child)?.dataset.pageFile)
			.filter(Boolean);
		const data = new FormData();
		data.set('csrf', form.elements.csrf.value);
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
	if (!editorShell || !contentToggle) return;
	editorShell.classList.toggle('content-collapsed', !open);
	contentToggle.setAttribute('aria-expanded', String(open));
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
