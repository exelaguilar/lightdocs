const $ = (selector, root = document) => root.querySelector(selector);
const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
const escapeHtml = (value) =>
	String(value).replace(
		/[&<>'"]/g,
		(char) =>
			({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[
				char
			],
	);
const escapeRegExp = (value) =>
	String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

if (/mac|iphone|ipad/i.test(navigator.platform || ''))
	$$('.key-command').forEach((key) => (key.textContent = '⌘'));

const themes = ['system', 'light', 'dark'];
const themeButton = $('[data-theme-toggle]');
function currentTheme() {
	return localStorage.getItem('lightdocs-theme') || 'system';
}
function applyTheme(theme) {
	localStorage.setItem('lightdocs-theme', theme);
	if (theme === 'system') delete document.documentElement.dataset.theme;
	else document.documentElement.dataset.theme = theme;
	themeButton?.setAttribute('data-theme', theme);
	themeButton?.setAttribute(
		'aria-label',
		`Color theme: ${theme}. Click to change.`,
	);
}
applyTheme(currentTheme());
themeButton?.addEventListener('click', () =>
	applyTheme(themes[(themes.indexOf(currentTheme()) + 1) % themes.length]),
);

const sidebar = $('#sidebar');
const menu = $('.menu-toggle');
function closeSidebar() {
	sidebar?.classList.remove('open');
	document.body.classList.remove('sidebar-open');
	menu?.setAttribute('aria-expanded', 'false');
	menu?.focus();
}
menu?.addEventListener('click', () => {
	sidebar?.classList.add('open');
	document.body.classList.add('sidebar-open');
	menu.setAttribute('aria-expanded', 'true');
	$('.sidebar-close')?.focus();
});
$$('[data-close-sidebar]').forEach((element) =>
	element.addEventListener('click', closeSidebar),
);
document.addEventListener('keydown', (event) => {
	if (
		event.key === 'Escape' &&
		document.body.classList.contains('sidebar-open')
	)
		closeSidebar();
});

const sectionMenu = $('[data-section-menu]');
if (sectionMenu)
	document.addEventListener('click', (event) => {
		if (sectionMenu.open && !sectionMenu.contains(event.target))
			sectionMenu.open = false;
	});

const folderState = JSON.parse(
	localStorage.getItem('lightdocs-navigation') || '{}',
);
$$('[data-nav-folder]').forEach((folder) => {
	const key = folder.dataset.navFolder;
	if (key in folderState) folder.open = Boolean(folderState[key]);
	folder.addEventListener('toggle', () => {
		folderState[key] = folder.open;
		localStorage.setItem('lightdocs-navigation', JSON.stringify(folderState));
	});
});
$('.nav-list a[aria-current="page"]')?.scrollIntoView({ block: 'center' });

$$('.copy-code').forEach((button) =>
	button.addEventListener('click', async () => {
		const code =
			button.closest('.code-block')?.querySelector('code')?.textContent || '';
		try {
			await navigator.clipboard.writeText(code);
			button.textContent = 'Copied';
			button.classList.add('copied');
			setTimeout(() => {
				button.textContent = 'Copy';
				button.classList.remove('copied');
			}, 1500);
		} catch {
			button.textContent = 'Select text';
		}
	}),
);

const tocLinks = $$(
	'.toc-inner nav a[href^="#"],.toc-mobile nav a[href^="#"],.inline-toc a[href^="#"]',
);
const tocCurrentLabel = $('[data-toc-current]');
const observed = [
	...new Set(
		tocLinks
			.map((link) =>
				document.getElementById(decodeURIComponent(link.hash.slice(1))),
			)
			.filter(Boolean),
	),
];
function updateActiveHeading() {
	const line = (document.querySelector('.site-header')?.offsetHeight || 0) + 56;
	let current = null;
	for (const heading of observed) {
		if (heading.getBoundingClientRect().top <= line) current = heading;
		else break;
	}
	if (!current) current = observed[0];
	if (
		Math.ceil(innerHeight + scrollY) >=
		document.documentElement.scrollHeight - 2
	)
		current = observed[observed.length - 1];
	let currentText = '';
	tocLinks.forEach((link) => {
		const active = link.hash === '#' + current.id;
		link.classList.toggle('active', active);
		if (active && !currentText) currentText = link.textContent.trim();
	});
	if (tocCurrentLabel) tocCurrentLabel.textContent = currentText;
}
if (observed.length) {
	let tocTimer = 0;
	const scheduleActiveHeading = () => {
		if (tocTimer) return;
		tocTimer = setTimeout(() => {
			tocTimer = 0;
			updateActiveHeading();
		}, 80);
	};
	addEventListener('scroll', scheduleActiveHeading, { passive: true });
	addEventListener('resize', scheduleActiveHeading, { passive: true });
	addEventListener('hashchange', scheduleActiveHeading);
	updateActiveHeading();
}
$$('.toc-mobile a').forEach((link) =>
	link.addEventListener('click', () => {
		const details = link.closest('details');
		if (details) details.open = false;
	}),
);

$$('.heading-anchor').forEach((anchor) =>
	anchor.addEventListener('click', async (event) => {
		event.preventDefault();
		history.replaceState(null, '', anchor.hash);
		anchor.closest('h1,h2,h3,h4')?.scrollIntoView();
		try {
			await navigator.clipboard.writeText(location.href);
			anchor.classList.add('copied');
			setTimeout(() => anchor.classList.remove('copied'), 1200);
		} catch {}
	}),
);

class DocsTabs extends HTMLElement {
	connectedCallback() {
		if (this.classList.contains('upgraded')) return;
		const panels = $$('.docs-tab', this);
		if (!panels.length) return;
		this.classList.add('upgraded');
		const list = document.createElement('div');
		list.className = 'tab-list';
		list.setAttribute('role', 'tablist');
		const group = this.dataset.group || '';
		const storageKey = group ? `lightdocs-tabs:${group}` : '';
		const hashValue = location.hash.startsWith('#tab-')
			? location.hash.slice(5)
			: '';
		const saved =
			storageKey &&
			(this.dataset.persist === 'true' ? localStorage : sessionStorage).getItem(
				storageKey,
			);
		const matched = panels.findIndex(
			(panel) => panel.dataset.value === (hashValue || saved),
		);
		const initial =
			matched >= 0
				? matched
				: Math.min(panels.length - 1, Number(this.dataset.default || 0));
		const activate = (panel, button, focus = true) => {
			for (const candidate of $$('button', list))
				candidate.setAttribute('aria-selected', String(candidate === button));
			for (const candidate of panels) candidate.hidden = candidate !== panel;
			if (group) {
				(this.dataset.persist === 'true'
					? localStorage
					: sessionStorage
				).setItem(storageKey, panel.dataset.value || panel.dataset.label);
				$$(`docs-tabs[data-group="${CSS.escape(group)}"]`).forEach((tabs) => {
					if (tabs !== this)
						tabs.activateValue?.(panel.dataset.value || panel.dataset.label);
				});
			}
			if (focus) button.focus();
		};
		panels.forEach((panel, index) => {
			const button = document.createElement('button');
			const id = panel.id || `tab-${Math.random().toString(36).slice(2)}`;
			button.type = 'button';
			button.textContent = panel.dataset.label || `Tab ${index + 1}`;
			button.setAttribute('role', 'tab');
			button.setAttribute('aria-selected', String(index === initial));
			button.setAttribute('aria-controls', id);
			panel.id = id;
			panel.setAttribute('role', 'tabpanel');
			panel.hidden = index !== initial;
			button.addEventListener('click', () => activate(panel, button));
			button.addEventListener('keydown', (event) => {
				if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key))
					return;
				event.preventDefault();
				const buttons = $$('button', list);
				const current = buttons.indexOf(button);
				const target =
					event.key === 'Home'
						? 0
						: event.key === 'End'
							? buttons.length - 1
							: (current +
									(event.key === 'ArrowRight' ? 1 : -1) +
									buttons.length) %
								buttons.length;
				buttons[target].click();
			});
			list.append(button);
		});
		this.activateValue = (value) => {
			const index = panels.findIndex(
				(panel) => (panel.dataset.value || panel.dataset.label) === value,
			);
			if (index >= 0) activate(panels[index], $$('button', list)[index], false);
		};
		this.prepend(list);
	}
}
customElements.define('docs-tabs', DocsTabs);

const dialog = $('[data-search-dialog]');
const searchInput = $('[data-search-input]');
const searchResults = $('[data-search-results]');
let searchIndex = null;
let selectedResult = -1;
function recentPages() {
	try {
		return JSON.parse(localStorage.getItem('lightdocs-recent') || '[]');
	} catch {
		return [];
	}
}
function rememberPage() {
	if (
		!location.pathname ||
		location.pathname === '/search' ||
		location.pathname.startsWith('/admin')
	)
		return;
	const title = $('.article-header h1')?.textContent?.trim();
	if (!title) return;
	const recent = [
		{ url: location.pathname, title },
		...recentPages().filter((item) => item.url !== location.pathname),
	].slice(0, 5);
	localStorage.setItem('lightdocs-recent', JSON.stringify(recent));
}
rememberPage();
function defaultSearchContent() {
	const recent = recentPages();
	const actions = [...document.querySelectorAll('.sidebar-bottom a')]
		.filter((link) => link.href)
		.slice(0, 3);
	const recentHtml = recent.length
		? `<p class="search-group-label">Recent pages</p>${recent.map((item) => `<a class="search-result" href="${escapeHtml(item.url)}"><span class="result-type">Recent</span><strong>${escapeHtml(item.title)}</strong><span>Continue reading</span></a>`).join('')}`
		: '<div class="search-empty compact"><strong>Search the documentation</strong><p>Pages, headings, commands, and concepts.</p></div>';
	const actionHtml = actions.length
		? `<p class="search-group-label">Quick actions</p>${actions.map((link) => `<a class="search-result search-action" href="${escapeHtml(link.getAttribute('href'))}"><span class="result-type">Action</span><strong>${escapeHtml(link.textContent.trim())}</strong><span>Open</span></a>`).join('')}`
		: '';
	searchResults.innerHTML = recentHtml + actionHtml;
}
async function openSearch() {
	dialog?.showModal();
	searchInput?.focus();
	searchInput?.select();
	selectedResult = -1;
	if (!searchIndex) {
		searchResults.innerHTML =
			'<div class="search-empty"><span class="search-loader"></span><strong>Loading search...</strong></div>';
		try {
			searchIndex = await fetch('/search-index.json').then((response) => {
				if (!response.ok) throw new Error();
				return response.json();
			});
			defaultSearchContent();
			buildSearchFilters();
		} catch {
			searchResults.innerHTML =
				'<div class="search-empty"><strong>Search is unavailable</strong><p><a href="/search">Open the search page instead.</a></p></div>';
		}
	} else if (!searchInput.value) defaultSearchContent();
}
function closeSearch() {
	dialog?.close();
	selectedResult = -1;
}
$$('[data-open-search]').forEach((button) =>
	button.addEventListener('click', openSearch),
);
$('[data-close-search]')?.addEventListener('click', closeSearch);
dialog?.addEventListener('click', (event) => {
	if (event.target === dialog) closeSearch();
});
document.addEventListener('keydown', (event) => {
	if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
		event.preventDefault();
		openSearch();
	}
});
function selectResult(index) {
	const results = $$('.search-result', searchResults);
	if (!results.length) return;
	selectedResult = (index + results.length) % results.length;
	results.forEach((result, i) =>
		result.classList.toggle('selected', i === selectedResult),
	);
	results[selectedResult].scrollIntoView({ block: 'nearest' });
}
searchInput?.addEventListener('keydown', (event) => {
	const count = $$('.search-result', searchResults).length;
	if (event.key === 'ArrowDown') {
		event.preventDefault();
		selectResult(selectedResult + 1);
	}
	if (event.key === 'ArrowUp') {
		event.preventDefault();
		selectResult(selectedResult - 1);
	}
	if (event.key === 'Home' && count) {
		event.preventDefault();
		selectResult(0);
	}
	if (event.key === 'End' && count) {
		event.preventDefault();
		selectResult(count - 1);
	}
	if (event.key === 'Enter' && count) {
		event.preventDefault();
		location.href = $$('.search-result', searchResults)[
			Math.max(0, selectedResult)
		].href;
	}
	if (event.key === 'Escape') closeSearch();
});
let searchSectionFilter = '';
const searchFilters = $('[data-search-filters]');
function buildSearchFilters() {
	if (!searchFilters || !searchIndex) return;
	const sections = [
		...new Set(searchIndex.map((item) => item.section).filter(Boolean)),
	];
	if (sections.length < 2) {
		searchFilters.hidden = true;
		return;
	}
	searchFilters.hidden = false;
	searchFilters.innerHTML = ['', ...sections]
		.map(
			(section) =>
				`<button type="button" data-section-filter="${escapeHtml(section)}" aria-pressed="${String(section === searchSectionFilter)}">${escapeHtml(section || 'All')}</button>`,
		)
		.join('');
	$$('button', searchFilters).forEach((button) =>
		button.addEventListener('click', () => {
			searchSectionFilter = button.dataset.sectionFilter;
			$$('button', searchFilters).forEach((candidate) =>
				candidate.setAttribute('aria-pressed', String(candidate === button)),
			);
			runSearch();
			searchInput?.focus();
		}),
	);
}
function runSearch() {
	selectedResult = -1;
	const query = searchInput.value.trim().toLowerCase();
	if (!query) {
		defaultSearchContent();
		return;
	}
	const terms = query.split(/\s+/);
	const currentSection =
		$('.section-menu nav a[aria-current="page"] strong')
			?.textContent.trim()
			.toLowerCase() || '';
	const matches = (searchIndex || [])
		.map((item) => {
			if (searchSectionFilter && item.section !== searchSectionFilter)
				return null;
			const title = item.title.toLowerCase();
			const description = (item.description || '').toLowerCase();
			const text = (item.text || '').toLowerCase();
			const keywords = (item.keywords || []).join(' ').toLowerCase();
			const aliases = (item.aliases || []).join(' ').toLowerCase();
			const breadcrumbs = (item.breadcrumbs || '').toLowerCase();
			let score = item.kind === 'page' ? 2 : 0;
			for (const term of terms) {
				if (title === term) score += 30;
				else if (title.startsWith(term)) score += 18;
				else if (title.includes(term)) score += 12;
				else if (keywords.includes(term)) score += 9;
				else if (aliases.includes(term)) score += 8;
				else if (description.includes(term)) score += 6;
				else if (breadcrumbs.includes(term)) score += 3;
				else if (text.includes(term)) score += 1;
				else return null;
			}
			if (
				currentSection &&
				String(item.section || '').toLowerCase() === currentSection
			)
				score += 2;
			return { ...item, score };
		})
		.filter(Boolean)
		.sort((a, b) => b.score - a.score || a.title.localeCompare(b.title))
		.slice(0, 18);
	const mark = (value) => {
		let html = escapeHtml(value);
		for (const term of terms)
			html = html.replace(
				new RegExp(`(${escapeRegExp(escapeHtml(term))})`, 'ig'),
				'<mark>$1</mark>',
			);
		return html;
	};
	const excerpt = (item) => {
		const text = item.text || '';
		const lower = text.toLowerCase();
		for (const term of terms) {
			const at = lower.indexOf(term);
			if (at >= 0) {
				const start = Math.max(0, at - 32);
				return (
					(start > 0 ? '…' : '') +
					text.slice(start, at + term.length + 62) +
					(at + term.length + 62 < text.length ? '…' : '')
				);
			}
		}
		return '';
	};
	const context = (item) => {
		if (item.kind === 'heading')
			return escapeHtml(
				item.breadcrumbs || item.description || 'Documentation',
			);
		const titleMatched = terms.some((term) =>
			item.title.toLowerCase().includes(term),
		);
		if (!titleMatched) {
			const snippet = excerpt(item);
			if (snippet) return mark(snippet);
		}
		return escapeHtml(item.breadcrumbs || item.description || 'Documentation');
	};
	const renderGroup = (label, items) =>
		items.length
			? `<p class="search-group-label">${label}</p>${items.map((item) => `<a class="search-result${item.kind === 'heading' ? ' search-result-heading' : ''}" href="${escapeHtml(item.url)}"><span class="result-type">${escapeHtml(item.section || label.slice(0, -1))}</span><strong>${mark(item.title)}</strong><span>${context(item)}</span></a>`).join('')}`
			: '';
	searchResults.innerHTML = matches.length
		? renderGroup(
				'Pages',
				matches.filter((item) => item.kind !== 'heading'),
			) +
			renderGroup(
				'Headings',
				matches.filter((item) => item.kind === 'heading'),
			)
		: '<div class="search-empty"><strong>No results found</strong><p>Try fewer words, a page alias, or a broader phrase.</p></div>';
	if (matches.length) selectResult(0);
}
searchInput?.addEventListener('input', runSearch);

$('[data-copy-link]')?.addEventListener('click', async (event) => {
	const target = event.currentTarget;
	try {
		await navigator.clipboard.writeText(location.href);
		target.textContent = 'Copied';
		setTimeout(() => (target.textContent = 'Copy link'), 1300);
	} catch {}
});
$$('[data-copy-markdown]').forEach((button) =>
	button.addEventListener('click', async (event) => {
		const target = event.currentTarget;
		const original = target.innerHTML;
		try {
			const markdown = await fetch(target.dataset.markdownUrl).then(
				(response) => {
					if (!response.ok) throw new Error();
					return response.text();
				},
			);
			await navigator.clipboard.writeText(markdown);
			target.textContent = 'Copied';
			setTimeout(() => (target.innerHTML = original), 1400);
		} catch {
			target.textContent = 'Unable to copy';
			setTimeout(() => (target.innerHTML = original), 1400);
		}
	}),
);

$$('.copy-filetree').forEach((button) =>
	button.addEventListener('click', async () => {
		const text =
			button.closest('.docs-filetree')?.querySelector('code')?.textContent ||
			'';
		try {
			await navigator.clipboard.writeText(text);
			button.textContent = 'Copied';
			setTimeout(() => (button.textContent = 'Copy'), 1200);
		} catch {}
	}),
);
$$('.docs-code-frame').forEach((frame) => {
	const code = $('code', frame);
	if (!code) return;
	const lines = code.innerHTML.split('\n');
	const highlighted = new Set();
	String(frame.dataset.highlightLines || '')
		.split(',')
		.forEach((part) => {
			const [start, end] = part.split('-').map(Number);
			if (!start) return;
			for (let i = start; i <= (end || start); i++) highlighted.add(i);
		});
	if (frame.dataset.lineNumbers === 'true' || highlighted.size) {
		code.innerHTML = lines
			.map(
				(line, index) =>
					`<span class="code-line${highlighted.has(index + 1) ? ' highlighted' : ''}" data-line="${index + 1}">${line || ' '}</span>`,
			)
			.join('\n');
		$$('.code-line', code).forEach((line) => {
			if (line.textContent.startsWith('+')) line.classList.add('diff-add');
			if (line.textContent.startsWith('-')) line.classList.add('diff-remove');
		});
	}
	frame.classList.toggle('code-wrap', frame.dataset.wrap === 'true');
	$('[data-toggle-code-wrap]', frame)?.addEventListener('click', (event) => {
		frame.classList.toggle('code-wrap');
		event.currentTarget.textContent = frame.classList.contains('code-wrap')
			? 'No wrap'
			: 'Wrap';
	});
	$('[data-copy-frame-code]', frame)?.addEventListener(
		'click',
		async (event) => {
			let text = code.textContent || '';
			const prompt = frame.dataset.copyPrompt;
			if (prompt)
				text = text
					.split('\n')
					.map((line) =>
						line.trimStart().startsWith(prompt)
							? line.slice(line.indexOf(prompt) + prompt.length).trimStart()
							: line,
					)
					.join('\n');
			try {
				await navigator.clipboard.writeText(text);
				event.currentTarget.textContent = 'Copied';
				setTimeout(() => (event.currentTarget.textContent = 'Copy'), 1200);
			} catch {}
		},
	);
});
$$('.zoom-image').forEach((button) =>
	button.addEventListener('click', () => {
		const image = $('img', button);
		if (!image) return;
		const modal = document.createElement('dialog');
		modal.className = 'image-lightbox';
		modal.innerHTML = `<button type="button" aria-label="Close image">×</button><img src="${escapeHtml(image.src)}" alt="${escapeHtml(image.alt)}">`;
		document.body.append(modal);
		modal.showModal();
		const close = () => {
			modal.close();
			modal.remove();
		};
		$('button', modal).addEventListener('click', close);
		modal.addEventListener('click', (event) => {
			if (event.target === modal) close();
		});
	}),
);

$$('.copy-command').forEach((button) =>
	button.addEventListener('click', async () => {
		const command =
			button.closest('.command-block')?.querySelector('.command-content code')
				?.textContent || '';
		try {
			await navigator.clipboard.writeText(command);
			button.textContent = 'Copied';
			setTimeout(() => (button.textContent = 'Copy command'), 1300);
		} catch {
			button.textContent = 'Select command';
		}
	}),
);

const feedbackButtons = $$('.page-feedback button');
if (feedbackButtons.length) {
	const feedbackKey = `lightdocs-feedback:${location.pathname}`;
	const applyFeedback = (value) =>
		feedbackButtons.forEach((button) =>
			button.setAttribute(
				'aria-pressed',
				String(
					(button.dataset.feedback ||
						button.textContent.trim().toLowerCase()) === value,
				),
			),
		);
	feedbackButtons.forEach((button) =>
		button.addEventListener('click', () => {
			const value =
				button.dataset.feedback || button.textContent.trim().toLowerCase();
			applyFeedback(value);
			try {
				localStorage.setItem(feedbackKey, value);
			} catch {}
			const container = button.closest('.page-feedback');
			if (container && !$('.feedback-thanks', container)) {
				const thanks = document.createElement('span');
				thanks.className = 'feedback-thanks';
				thanks.setAttribute('role', 'status');
				thanks.textContent = 'Thanks for the feedback';
				container.append(thanks);
			}
		}),
	);
	try {
		const saved = localStorage.getItem(feedbackKey);
		if (saved) applyFeedback(saved);
	} catch {}
}

const runbook = $('[data-page-type="runbook"]');
if (runbook) {
	const checks = $$('.markdown-body input[type="checkbox"]', runbook);
	const progress = $('[data-runbook-progress]', runbook);
	if (!checks.length && progress) progress.hidden = true;
	const storageKey = `lightdocs-runbook:${location.pathname}`;
	let saved = {};
	try {
		saved = JSON.parse(localStorage.getItem(storageKey) || '{}');
	} catch {}
	const progressLabel = $('[data-progress-label]', runbook);
	const progressBar = $('[data-progress-bar]', runbook);
	const updateProgress = () => {
		const complete = checks.filter((check) => check.checked).length;
		if (progressLabel)
			progressLabel.textContent = `${complete} of ${checks.length} complete`;
		if (progressBar)
			progressBar.style.width = `${checks.length ? (complete / checks.length) * 100 : 0}%`;
	};
	checks.forEach((check, index) => {
		check.disabled = false;
		check.checked = Boolean(saved[index]);
		check.addEventListener('change', () => {
			saved[index] = check.checked;
			localStorage.setItem(storageKey, JSON.stringify(saved));
			updateProgress();
		});
	});
	$('[data-reset-runbook]', runbook)?.addEventListener('click', () => {
		if (!confirm('Reset all runbook checkboxes on this device?')) return;
		saved = {};
		checks.forEach((check) => (check.checked = false));
		localStorage.removeItem(storageKey);
		updateProgress();
	});
	updateProgress();
}

const sidebarCollapse = $('[data-sidebar-collapse]');
function syncSidebarCollapse() {
	const collapsed =
		document.documentElement.classList.contains('sidebar-collapsed');
	sidebarCollapse?.setAttribute('aria-expanded', String(!collapsed));
	sidebarCollapse?.setAttribute(
		'aria-label',
		collapsed ? 'Expand navigation' : 'Collapse navigation',
	);
}
sidebarCollapse?.addEventListener('click', () => {
	document.documentElement.classList.toggle('sidebar-collapsed');
	const collapsed =
		document.documentElement.classList.contains('sidebar-collapsed');
	try {
		localStorage.setItem(
			'lightdocs-sidebar',
			collapsed ? 'collapsed' : 'expanded',
		);
	} catch {}
	syncSidebarCollapse();
});
syncSidebarCollapse();
