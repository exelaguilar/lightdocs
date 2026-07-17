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

const toastRegion = document.createElement('div');
toastRegion.className = 'pointer-events-none fixed bottom-3.5 right-3.5 z-[150] grid gap-2 min-[841px]:bottom-[18px] min-[841px]:right-[18px]';
toastRegion.setAttribute('aria-live', 'polite');
toastRegion.setAttribute('aria-atomic', 'true');
document.body.append(toastRegion);
let toastTimer = 0;
function showToast(message, type = 'success') {
	clearTimeout(toastTimer);
	toastRegion.replaceChildren();
	const toast = document.createElement('div');
	toast.className = 'max-w-[min(360px,calc(100vw-36px))] [transform:translateY(8px)] rounded-[var(--radius-sm)] border border-[var(--border-strong)] bg-[color-mix(in_srgb,var(--surface)_94%,transparent)] px-3 py-[9px] text-xs font-semibold text-[var(--text-strong)] opacity-0 shadow-[var(--shadow-lg)] backdrop-blur-lg transition-[opacity,transform] duration-150';
	if (type === 'error') toast.classList.add('border-[color-mix(in_srgb,var(--danger)_48%,var(--border))]', 'text-[var(--danger)]');
	toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
	toast.textContent = message;
	toastRegion.append(toast);
	requestAnimationFrame(() => toast.classList.remove('[transform:translateY(8px)]', 'opacity-0'));
	toastTimer = setTimeout(() => {
		toast.classList.add('[transform:translateY(8px)]', 'opacity-0');
		setTimeout(() => toast.remove(), 180);
	}, 2600);
}

const tooltip = document.createElement('div');
tooltip.className = 'pointer-events-none fixed z-[160] hidden max-w-[min(240px,calc(100vw-16px))] rounded-[var(--radius-sm)] border border-[var(--border-strong)] bg-[color-mix(in_srgb,var(--surface-raised)_97%,transparent)] px-2.5 py-1.5 text-[11px] font-medium leading-4 text-[var(--text-strong)] opacity-0 shadow-[var(--shadow-sm)] transition-[opacity,transform] duration-150 ease-out translate-y-1';
tooltip.id = 'ui-tooltip';
tooltip.setAttribute('role', 'tooltip');
tooltip.setAttribute('popover', 'manual');
tooltip.hidden = true;
document.body.append(tooltip);
let tooltipTarget = null;
let tooltipTimer = 0;
function positionTooltip() {
	if (!tooltipTarget) return;
	const rect = tooltipTarget.getBoundingClientRect();
	const preferred = tooltipTarget.dataset.tooltipPlacement || tooltipTarget.dataset.side || 'top';
	const gap = 8;
	const tip = tooltip.getBoundingClientRect();
	const sides = preferred === 'auto' ? ['top', 'bottom', 'right', 'left'] : [preferred, 'top', 'bottom', 'right', 'left'];
	const side = sides.find((candidate, index) => sides.indexOf(candidate) === index && ((candidate === 'top' && rect.top >= tip.height + gap) || (candidate === 'bottom' && innerHeight - rect.bottom >= tip.height + gap) || (candidate === 'left' && rect.left >= tip.width + gap) || (candidate === 'right' && innerWidth - rect.right >= tip.width + gap))) || 'top';
	let top = side === 'bottom' ? rect.bottom + gap : side === 'left' || side === 'right' ? rect.top + rect.height / 2 - tip.height / 2 : rect.top - tip.height - gap;
	let left = side === 'left' ? rect.left - tip.width - gap : side === 'right' ? rect.right + gap : rect.left + rect.width / 2 - tip.width / 2;
	tooltip.style.top = `${Math.max(4, Math.min(top, innerHeight - tip.height - 4))}px`;
	tooltip.style.left = `${Math.max(4, Math.min(left, innerWidth - tip.width - 4))}px`;
}
function hideTooltip() {
	clearTimeout(tooltipTimer);
	tooltipTimer = window.setTimeout(() => {
		tooltipTarget?.removeAttribute('aria-describedby');
		tooltipTarget = null;
		tooltip.classList.add('opacity-0', 'translate-y-1');
		window.setTimeout(() => tooltip.classList.add('hidden'), 150);
	}, 60);
}
function showTooltip(target) {
	clearTimeout(tooltipTimer);
	const text = target.dataset.tooltip || target.getAttribute('aria-label');
	if (!text || target.closest('[hidden]')) return;
	tooltipTarget = target;
	tooltip.textContent = text;
	tooltip.hidden = false;
	tooltip.classList.remove('hidden');
	target.setAttribute('aria-describedby', tooltip.id);
	requestAnimationFrame(() => {
		if (tooltipTarget !== target) return;
		positionTooltip();
		tooltip.classList.remove('opacity-0', 'translate-y-1');
	});
}
$$('[data-tooltip],button[aria-label],a[aria-label]').forEach((target) => {
	target.addEventListener('mouseenter', () => showTooltip(target));
	target.addEventListener('mouseleave', hideTooltip);
	target.addEventListener('focus', () => showTooltip(target));
	target.addEventListener('blur', hideTooltip);
});
addEventListener('scroll', () => tooltipTarget && positionTooltip(), { passive: true });
addEventListener('resize', () => tooltipTarget && positionTooltip());

if (/mac|iphone|ipad/i.test(navigator.platform || ''))
	$$('[data-key-command]').forEach((key) => (key.textContent = '⌘'));

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

const readerPreferencesDialog = $('[data-reader-preferences-dialog]');
const readingPreferencesKey = 'lightdocs-reading-preferences';
const readingDefaults = { text_size: 'default', content_width: 'default', layout: 'standard' };
function readingPreferences() {
	try {
		const stored = JSON.parse(localStorage.getItem(readingPreferencesKey) || '{}');
		if (!stored.layout && stored.focus) stored.layout = 'focus';
		return { ...readingDefaults, ...stored };
	} catch {
		return { ...readingDefaults };
	}
}
function applyReadingPreferences(preferences) {
	const values = { ...readingDefaults, ...preferences };
	document.documentElement.dataset.readingTextSize = values.text_size;
	document.documentElement.dataset.readingWidth = values.content_width;
	document.documentElement.dataset.readerLayout = values.layout;
	if (values.layout === 'focus') document.documentElement.dataset.readerFocus = 'true';
	else delete document.documentElement.dataset.readerFocus;
	$('[data-exit-reader-focus]')?.classList.toggle('hidden', values.layout !== 'focus');
	try {
		localStorage.setItem(readingPreferencesKey, JSON.stringify(values));
	} catch {}
	if (!readerPreferencesDialog) return;
	$(`input[name="reading-text-size"][value="${values.text_size}"]`, readerPreferencesDialog).checked = true;
	$(`input[name="reading-content-width"][value="${values.content_width}"]`, readerPreferencesDialog).checked = true;
	$(`input[name="reading-layout"][value="${values.layout}"]`, readerPreferencesDialog).checked = true;
}
applyReadingPreferences(readingPreferences());
$$('[data-reader-preferences-toggle]').forEach((button) =>
	button.addEventListener('click', () => {
		if (!readerPreferencesDialog?.open) readerPreferencesDialog?.showModal();
	}),
);
$$('input', readerPreferencesDialog || document.createElement('div')).forEach((input) =>
	input.addEventListener('change', () => {
		const current = readingPreferences();
		applyReadingPreferences({
			...current,
				text_size: $('input[name="reading-text-size"]:checked', readerPreferencesDialog)?.value || 'default',
				content_width: $('input[name="reading-content-width"]:checked', readerPreferencesDialog)?.value || 'default',
				layout: $('input[name="reading-layout"]:checked', readerPreferencesDialog)?.value || 'standard',
		});
	}),
);
$('[data-reset-reading-preferences]')?.addEventListener('click', () => {
	applyReadingPreferences(readingDefaults);
	showToast('Reading preferences reset');
});
$('[data-exit-reader-focus]')?.addEventListener('click', () => {
	applyReadingPreferences({ ...readingPreferences(), layout: 'standard' });
	showToast('Focus mode disabled');
});
document.addEventListener('keydown', (event) => {
	if (event.key !== 'Escape' || document.documentElement.dataset.readerFocus !== 'true') return;
	if (document.querySelector('dialog[open]') || sidebar?.dataset.open === 'true') return;
	applyReadingPreferences({ ...readingPreferences(), layout: 'standard' });
	showToast('Focus mode disabled');
});

const sidebar = $('#sidebar');
const sidebarBackdrop = $('[data-sidebar-backdrop]');
const menu = $('[data-menu-toggle]');
function closeSidebar() {
	if (sidebar) sidebar.dataset.open = 'false';
	sidebarBackdrop?.classList.add('hidden');
	document.body.classList.remove('overflow-hidden');
	menu?.setAttribute('aria-expanded', 'false');
	menu?.focus();
}
menu?.addEventListener('click', () => {
	if (sidebar) sidebar.dataset.open = 'true';
	sidebarBackdrop?.classList.remove('hidden');
	document.body.classList.add('overflow-hidden');
	menu.setAttribute('aria-expanded', 'true');
	$('[data-sidebar-close]')?.focus();
});
$$('[data-close-sidebar]').forEach((element) =>
	element.addEventListener('click', closeSidebar),
);
document.addEventListener('keydown', (event) => {
	if (
		event.key === 'Escape' &&
		sidebar?.dataset.open === 'true'
	)
		closeSidebar();
});

const readingProgress = $('[data-reading-progress] span');
const backToTop = $('[data-back-to-top]');
function updateReadingProgress() {
	const maximum = document.documentElement.scrollHeight - innerHeight;
	const progress = maximum > 0 ? Math.min(1, Math.max(0, scrollY / maximum)) : 0;
	if (readingProgress) {
		readingProgress.style.transformOrigin = document.documentElement.dir === 'rtl' ? 'right center' : 'left center';
		readingProgress.style.transform = `scaleX(${progress})`;
	}
	const visible = scrollY > Math.min(420, innerHeight * .55);
	backToTop?.classList.toggle('[transform:translateY(8px)]', !visible);
	backToTop?.classList.toggle('opacity-0', !visible);
	backToTop?.classList.toggle('pointer-events-none', !visible);
}
if (readingProgress || backToTop) {
	addEventListener('scroll', updateReadingProgress, { passive: true });
	addEventListener('resize', updateReadingProgress, { passive: true });
	updateReadingProgress();
}
backToTop?.addEventListener('click', () => {
	window.scrollTo({ top: 0, behavior: 'smooth' });
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
	const containsCurrentPage = Boolean(folder.querySelector('[aria-current="page"]'));
	if (containsCurrentPage) folder.open = true;
	else if (key in folderState) folder.open = Boolean(folderState[key]);
	folder.addEventListener('toggle', () => {
		folderState[key] = folder.open;
		localStorage.setItem('lightdocs-navigation', JSON.stringify(folderState));
	});
});
$('[data-nav-list] a[aria-current="page"]')?.scrollIntoView({ block: 'center' });

$$('[data-copy-code]').forEach((button) =>
	button.addEventListener('click', async () => {
		const code =
			button.closest('[data-code-block]')?.querySelector('code')?.textContent || '';
		try {
			await navigator.clipboard.writeText(code);
			button.textContent = 'Copied';
			showToast('Code copied');
			setTimeout(() => {
				button.textContent = 'Copy';
			}, 1500);
		} catch {
			button.textContent = 'Select text';
			showToast('Copy is unavailable in this browser.', 'error');
		}
	}),
);

const tocLinks = $$(
	'[data-toc-inner] nav a[href^="#"],[data-toc-mobile] nav a[href^="#"],[data-inline-toc] a[href^="#"]',
);
const tocCurrentLabel = $('[data-toc-current]');
const tocIndicator = $('[data-toc-indicator]');
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
	const line = (document.querySelector('[data-site-header]')?.offsetHeight || 0) + 56;
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
		if (active) link.setAttribute('data-active', 'true');
		else link.removeAttribute('data-active');
		if (active && !currentText) currentText = link.textContent.trim();
	});
	if (tocCurrentLabel) tocCurrentLabel.textContent = currentText;
	const activeDesktop = $('[data-toc-inner] nav a[data-active="true"]');
	if (tocIndicator && activeDesktop) {
		const list = activeDesktop.closest('ul');
		const linkRect = activeDesktop.getBoundingClientRect();
		const listRect = list.getBoundingClientRect();
		tocIndicator.style.height = `${Math.max(18, linkRect.height - 4)}px`;
		tocIndicator.style.transform = `translateY(${linkRect.top - listRect.top + 2}px)`;
		tocIndicator.classList.remove('opacity-0');
	}
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
$$('[data-toc-mobile] a').forEach((link) =>
	link.addEventListener('click', () => {
		const details = link.closest('details');
		if (details) details.open = false;
	}),
);

$$('[data-heading-anchor]').forEach((anchor) =>
	anchor.addEventListener('click', async (event) => {
		event.preventDefault();
		history.replaceState(null, '', anchor.hash);
		anchor.closest('h1,h2,h3,h4')?.scrollIntoView();
		try {
			await navigator.clipboard.writeText(location.href);
			anchor.textContent = '✓';
			anchor.classList.add('text-green-500');
			showToast('Heading link copied');
			setTimeout(() => {
				anchor.textContent = '#';
				anchor.classList.remove('text-green-500');
			}, 1200);
		} catch {}
	}),
);

const glossaryPopover = document.createElement('div');
glossaryPopover.className = 'pointer-events-none fixed z-[165] w-[min(300px,calc(100vw-16px))] rounded-[var(--radius-sm)] border border-[var(--border-strong)] bg-[color-mix(in_srgb,var(--surface-raised)_97%,transparent)] px-[11px] py-[9px] text-xs leading-normal text-[var(--text-strong)] shadow-[var(--shadow-lg)]';
glossaryPopover.id = 'glossary-popover';
glossaryPopover.setAttribute('role', 'tooltip');
glossaryPopover.hidden = true;
document.body.append(glossaryPopover);
let glossaryTarget = null;
function closeGlossaryPopover() {
	glossaryPopover.hidden = true;
	glossaryTarget?.setAttribute('aria-expanded', 'false');
	glossaryTarget = null;
}
function openGlossaryPopover(target) {
	const definition = target.dataset.glossaryDefinition;
	if (!definition) return;
	if (glossaryTarget === target && !glossaryPopover.hidden) {
		closeGlossaryPopover();
		return;
	}
	glossaryTarget?.setAttribute('aria-expanded', 'false');
	glossaryTarget = target;
	glossaryPopover.textContent = definition;
	glossaryPopover.hidden = false;
	target.setAttribute('aria-describedby', glossaryPopover.id);
	target.setAttribute('aria-expanded', 'true');
	const bounds = target.getBoundingClientRect();
	const popoverBounds = glossaryPopover.getBoundingClientRect();
	const gap = 8;
	const top = bounds.bottom + gap + popoverBounds.height <= innerHeight - gap
		? bounds.bottom + gap
		: Math.max(gap, bounds.top - gap - popoverBounds.height);
	const left = Math.max(gap, Math.min(innerWidth - popoverBounds.width - gap, bounds.left));
	glossaryPopover.style.top = `${top}px`;
	glossaryPopover.style.left = `${left}px`;
}
$$('[data-glossary-term]').forEach((term) => {
	term.setAttribute('aria-expanded', 'false');
	term.addEventListener('click', (event) => {
		event.stopPropagation();
		event.preventDefault();
		openGlossaryPopover(term);
	});
	term.addEventListener('keydown', (event) => {
		if (event.key === 'Escape') closeGlossaryPopover();
	});
});
document.addEventListener('click', (event) => {
	if (glossaryTarget && !glossaryPopover.contains(event.target)) closeGlossaryPopover();
});
addEventListener('scroll', closeGlossaryPopover, { passive: true });

class DocsTabs extends HTMLElement {
	connectedCallback() {
		if (this.dataset.tabsUpgraded === 'true') return;
		const panels = $$('[data-docs-tab]', this);
		if (!panels.length) return;
		this.dataset.tabsUpgraded = 'true';
		const list = document.createElement('div');
		list.className = 'flex gap-1 border-b border-[var(--border)] bg-[var(--surface-subtle)] p-1.5';
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
			button.className = 'rounded-md px-3 py-1.5 text-[13px] text-[var(--muted)] hover:text-[var(--text)] aria-selected:bg-[var(--surface)] aria-selected:text-[var(--text-strong)] aria-selected:shadow-sm';
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
const searchStatus = $('[data-search-status]');
const searchResultClass = 'group relative grid grid-cols-[28px_1fr_auto] gap-x-2.5 gap-y-px rounded-[var(--radius-sm)] px-2.5 py-2.5 hover:bg-[var(--brand-soft)] data-[selected=true]:bg-[var(--brand-soft)]';
const searchGroupClass = 'm-[8px_9px_5px] text-[11px] font-bold uppercase tracking-[.09em] text-[var(--faint)]';
let searchIndex = null;
let selectedResult = -1;
let searchTrigger = null;
function recentPages() {
	try {
		return JSON.parse(localStorage.getItem('lightdocs-recent') || '[]');
	} catch {
		return [];
	}
}
function recentSearches() {
	try {
		return JSON.parse(localStorage.getItem('lightdocs-recent-searches') || '[]');
	} catch {
		return [];
	}
}
function rememberSearch(query) {
	const value = String(query || '').trim();
	if (!value) return;
	const searches = [value, ...recentSearches().filter((item) => item !== value)].slice(0, 5);
	localStorage.setItem('lightdocs-recent-searches', JSON.stringify(searches));
}
function rememberPage() {
	if (
		!location.pathname ||
		location.pathname === '/search' ||
		location.pathname.startsWith('/admin')
	)
		return;
	const title = $('[data-docs-article] h1')?.textContent?.trim();
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
	const actions = [...document.querySelectorAll('[data-sidebar-bottom] a')]
		.filter((link) => link.href)
		.slice(0, 3);
	const searches = recentSearches();
	const recentHtml = recent.length
		? `<p class="${searchGroupClass}">Recent pages</p>${recent.map((item) => `<a class="${searchResultClass}" data-search-result href="${escapeHtml(item.url)}"><span class="row-span-2 grid h-7 w-7 place-items-center self-center rounded-md border border-[var(--border)] bg-[var(--surface-subtle)] text-[10px] font-bold text-[var(--faint)]" aria-hidden="true">P</span><strong class="col-start-2 text-sm font-semibold text-[var(--text-strong)]">${escapeHtml(item.title)}</strong><span class="col-start-2 line-clamp-2 text-[12.5px] text-[var(--muted)]">Continue reading</span><span class="col-start-3 row-span-2 self-center text-[var(--faint)]" aria-hidden="true">↗</span></a>`).join('')}`
		: '<div class="flex min-h-[190px] flex-col items-center justify-center p-[30px] text-center text-[var(--muted)]"><strong class="text-[var(--text-strong)]">Search the documentation</strong><p class="mt-1 text-[13px]">Pages, headings, commands, and concepts.</p></div>';
	const searchHtml = searches.length
		? `<p class="${searchGroupClass}">Recent searches</p><div class="flex flex-wrap gap-1.5 px-2.5 pb-1">${searches.map((query) => `<button class="rounded-full border border-[var(--border)] bg-[var(--surface)] px-2.5 py-1 text-xs text-[var(--muted)] hover:border-[var(--border-strong)] hover:text-[var(--text)]" type="button" data-recent-search="${escapeHtml(query)}">${escapeHtml(query)}</button>`).join('')}</div>`
		: '';
	const actionHtml = actions.length
		? `<p class="${searchGroupClass}">Quick actions</p>${actions.map((link) => `<a class="${searchResultClass}" data-search-result href="${escapeHtml(link.getAttribute('href'))}"><span class="row-span-2 grid h-7 w-7 place-items-center self-center rounded-md border border-[var(--border)] bg-[var(--surface-subtle)] text-[13px] text-[var(--faint)]" aria-hidden="true">→</span><strong class="col-start-2 text-sm font-semibold text-[var(--text-strong)]">${escapeHtml(link.textContent.trim())}</strong><span class="col-start-2 line-clamp-2 text-[12.5px] text-[var(--muted)]">Open</span></a>`).join('')}`
		: '';
	searchResults.innerHTML = searchHtml + recentHtml + actionHtml;
	$$('[data-recent-search]', searchResults).forEach((button) => button.addEventListener('click', () => {
		searchInput.value = button.dataset.recentSearch || '';
		runSearch();
		searchInput.focus();
	}));
	if (searchStatus) searchStatus.textContent = 'Search suggestions are ready.';
}
async function openSearch() {
	searchTrigger = document.activeElement;
	dialog?.showModal();
	searchInput?.focus();
	searchInput?.select();
	selectedResult = -1;
	if (!searchIndex) {
		searchResults.innerHTML =
			'<div class="flex min-h-[190px] flex-col items-center justify-center p-[30px] text-center text-[var(--muted)]"><span class="mb-3 h-[22px] w-[22px] animate-spin rounded-full border-2 border-[var(--border)] border-t-[var(--brand)]"></span><strong class="text-[var(--text-strong)]">Loading search...</strong></div>';
		try {
			searchIndex = await fetch('/search-index.json').then((response) => {
				if (!response.ok) throw new Error();
				return response.json();
			});
			defaultSearchContent();
			buildSearchFilters();
		} catch {
			searchResults.innerHTML =
				'<div class="flex min-h-[190px] flex-col items-center justify-center p-[30px] text-center text-[var(--muted)]"><strong class="text-[var(--text-strong)]">Search is unavailable</strong><p class="mt-1 text-[13px]"><a href="/search">Open the search page instead.</a></p></div>';
		}
	} else if (!searchInput.value) defaultSearchContent();
}
function closeSearch() {
	dialog?.close();
	selectedResult = -1;
	if (searchTrigger instanceof HTMLElement) searchTrigger.focus();
}
$$('[data-open-search]').forEach((button) =>
	button.addEventListener('click', openSearch),
);
$('[data-close-search]')?.addEventListener('click', closeSearch);
dialog?.addEventListener('click', (event) => {
	if (event.target === dialog) closeSearch();
});
dialog?.addEventListener('cancel', (event) => {
	event.preventDefault();
	closeSearch();
});
document.addEventListener('keydown', (event) => {
	if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
		event.preventDefault();
		openSearch();
	}
});
function selectResult(index) {
	const results = $$('[data-search-result]', searchResults);
	if (!results.length) return;
	selectedResult = (index + results.length) % results.length;
	results.forEach((result, i) =>
		result.toggleAttribute('data-selected', i === selectedResult),
	);
	results.forEach((result, i) => result.setAttribute('aria-selected', String(i === selectedResult)));
	results[selectedResult].scrollIntoView({ block: 'nearest' });
}
searchInput?.addEventListener('keydown', (event) => {
	const count = $$('[data-search-result]', searchResults).length;
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
		rememberSearch(searchInput.value);
		location.href = $$('[data-search-result]', searchResults)[
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
				`<button class="rounded-full border border-[var(--border)] bg-[var(--surface)] px-2.5 py-1 text-xs text-[var(--muted)] hover:border-[var(--border-strong)] hover:text-[var(--text)] aria-pressed:bg-[var(--brand-soft)] aria-pressed:text-[var(--brand-strong)]" type="button" data-section-filter="${escapeHtml(section)}" aria-pressed="${String(section === searchSectionFilter)}">${escapeHtml(section || 'All')}</button>`,
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
		$('[data-section-menu] nav a[aria-current="page"] strong')
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
				'<mark class="rounded-sm bg-[color-mix(in_srgb,var(--brand)_18%,transparent)] px-0 text-inherit">$1</mark>',
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
			? `<p class="${searchGroupClass}">${label}</p>${items.map((item) => `<a class="${searchResultClass}" data-search-result role="option" href="${escapeHtml(item.url)}"><span class="row-span-2 grid h-7 w-7 place-items-center self-center rounded-md border border-[var(--border)] bg-[var(--surface-subtle)] text-[11px] font-bold text-[var(--faint)]" aria-hidden="true">${item.kind === 'heading' ? '#' : 'P'}</span><strong class="col-start-2 text-sm font-semibold text-[var(--text-strong)]">${mark(item.title)}</strong><span class="col-start-2 line-clamp-2 text-[12.5px] text-[var(--muted)]">${context(item)}</span><span class="col-start-3 row-span-2 self-center text-[var(--faint)] opacity-0 group-hover:opacity-100" aria-hidden="true">↗</span></a>`).join('')}`
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
		: '<div class="flex min-h-[190px] flex-col items-center justify-center p-[30px] text-center text-[var(--muted)]"><strong class="text-[var(--text-strong)]">No results found</strong><p class="mt-1 text-[13px]">Try fewer words, a page alias, or a broader phrase.</p></div>';
	if (searchStatus) searchStatus.textContent = matches.length ? `${matches.length} search results available.` : 'No search results found.';
	if (matches.length) selectResult(0);
}
searchInput?.addEventListener('input', runSearch);
searchResults?.addEventListener('click', (event) => {
	if (event.target.closest('[data-search-result]')) rememberSearch(searchInput?.value);
});

$$('[data-copy-link]').forEach((button) => button.addEventListener('click', async (event) => {
	const target = event.currentTarget;
	try {
		await navigator.clipboard.writeText(location.href);
		target.textContent = 'Copied';
		showToast('Page link copied');
		setTimeout(() => (target.textContent = 'Copy page link'), 1300);
	} catch {}
}));
$$('[data-copy-ai-prompt]').forEach((button) => button.addEventListener('click', async () => {
	const source = new URL(button.dataset.markdownUrl || location.pathname, location.origin).href;
	const prompt = `Use this documentation as the source of truth for my question about ${button.dataset.pageTitle || 'this topic'}: ${source}`;
	try {
		await navigator.clipboard.writeText(prompt);
		showToast('AI prompt copied');
	} catch {
		showToast('AI prompt could not be copied.', 'error');
	}
}));
const pageActions = $('[data-page-actions]');
if (pageActions) document.addEventListener('click', (event) => {
	if (pageActions.open && !pageActions.contains(event.target)) pageActions.open = false;
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
			showToast('Markdown copied');
			setTimeout(() => (target.innerHTML = original), 1400);
		} catch {
			target.textContent = 'Unable to copy';
			showToast('Markdown could not be copied.', 'error');
			setTimeout(() => (target.innerHTML = original), 1400);
		}
	}),
);

$$('[data-copy-filetree]').forEach((button) =>
	button.addEventListener('click', async () => {
		const text =
			button.closest('[data-docs-filetree]')?.querySelector('code')?.textContent ||
			'';
		try {
			await navigator.clipboard.writeText(text);
			button.textContent = 'Copied';
			showToast('File tree copied');
			setTimeout(() => (button.textContent = 'Copy'), 1200);
		} catch {}
	}),
);
$$('[data-docs-code-frame]').forEach((frame) => {
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
		const numbered = frame.dataset.lineNumbers === 'true';
		code.innerHTML = lines
			.map((line, index) => {
				const lineNumber = index + 1;
				const isHighlighted = highlighted.has(lineNumber);
				const isAddition = line.startsWith('+');
				const isRemoval = line.startsWith('-');
				const classes = ['relative', 'block', 'min-h-[1.55em]', 'px-0'];
				if (numbered) classes.push('pl-[42px]');
				if (isHighlighted) {
					classes.push(
						'-mx-[19px]',
						'px-[19px]',
						'bg-[color-mix(in_srgb,var(--brand)_12%,transparent)]',
						'shadow-[inset_2px_0_var(--brand)]',
					);
					if (numbered) classes.push('pl-[61px]');
				}
				if (isAddition)
					classes.push(
						'bg-[color-mix(in_srgb,#22c55e_11%,transparent)]',
						'text-[color-mix(in_srgb,#22c55e_72%,var(--text))]',
					);
				if (isRemoval)
					classes.push(
						'bg-[color-mix(in_srgb,#ef4444_10%,transparent)]',
						'text-[color-mix(in_srgb,#ef4444_72%,var(--text))]',
						'opacity-[.82]',
					);
				const number = numbered
					? `<span class="pointer-events-none absolute ${isHighlighted ? 'left-[19px]' : 'left-0'} w-[27px] select-none text-right text-[#72727d]" aria-hidden="true">${lineNumber}</span>`
					: '';
				return `<span class="${classes.join(' ')}" data-code-line>${number}${line || ' '}</span>`;
			})
			.join('\n');
	}
	const setCodeWrap = (enabled) => {
		frame.dataset.wrap = String(enabled);
		$$('pre, code', frame).forEach((element) =>
			element.classList.toggle('whitespace-pre-wrap', enabled),
		);
	};
	setCodeWrap(frame.dataset.wrap === 'true');
	const body = $('[data-code-frame-body]', frame);
	const heightToggle = $('[data-toggle-code-height]', frame);
	const fade = $('[data-code-fade]', frame);
	const setCodeExpanded = (expanded) => {
		if (!body || !heightToggle) return;
		body.classList.toggle('max-h-[420px]', !expanded);
		body.classList.toggle('overflow-hidden', !expanded);
		fade?.classList.toggle('hidden', expanded);
		heightToggle.textContent = expanded ? 'Collapse' : 'Expand';
		heightToggle.setAttribute('aria-expanded', String(expanded));
	};
	if (body && heightToggle && (frame.dataset.collapse === 'true' || body.scrollHeight > 520)) {
		heightToggle.classList.remove('hidden');
		setCodeExpanded(frame.dataset.collapse !== 'true');
		heightToggle.addEventListener('click', () => setCodeExpanded(heightToggle.getAttribute('aria-expanded') !== 'true'));
	}
	$('[data-toggle-code-wrap]', frame)?.addEventListener('click', (event) => {
		const enabled = frame.dataset.wrap !== 'true';
		setCodeWrap(enabled);
		event.currentTarget.textContent = enabled ? 'No wrap' : 'Wrap';
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
				const icon = $('[data-copy-frame-icon]', event.currentTarget);
				if (icon) icon.textContent = '✓';
				event.currentTarget.setAttribute('aria-label', 'Code copied');
				showToast('Code copied');
				setTimeout(() => {
					if (icon) icon.textContent = '⧉';
					event.currentTarget.setAttribute('aria-label', 'Copy code');
				}, 1200);
			} catch {}
		},
	);
});
function openImageLightbox(image) {
	const modal = document.createElement('dialog');
	modal.className = 'm-auto max-h-[min(88vh,920px)] max-w-[min(92vw,1200px)] overflow-visible border-0 bg-transparent p-0 opacity-0 shadow-none transition-opacity duration-150 open:opacity-100 backdrop:bg-black/72 backdrop:backdrop-blur-sm';
modal.innerHTML = `<button class="absolute -right-2.5 -top-2.5 grid h-8 w-8 place-items-center rounded-full border border-white/20 bg-black/70 text-xl leading-none text-white" type="button" aria-label="Close image">×</button><img class="block max-h-[min(88vh,920px)] max-w-[min(92vw,1200px)] rounded-[var(--radius)] bg-[var(--surface)] shadow-[0_20px_70px_rgba(0,0,0,.42)]" src="${escapeHtml(image.src)}" alt="${escapeHtml(image.alt)}">`;
	document.body.append(modal);
	modal.showModal();
	const close = () => modal.close();
	$('button', modal).addEventListener('click', close);
	modal.addEventListener('click', (event) => {
		if (event.target === modal) close();
	});
	modal.addEventListener('close', () => modal.remove(), { once: true });
}

$$('[data-zoom-image]').forEach((button) =>
	button.addEventListener('click', () => {
		const image = $('img', button);
		if (image) openImageLightbox(image);
	}),
);

$$('[data-markdown-body] img').forEach((image) => {
	if (image.closest('[data-zoom-image]') || image.closest('a')) return;
	image.classList.add('cursor-zoom-in', 'focus-visible:outline-2', 'focus-visible:outline-[var(--brand)]', 'focus-visible:outline-offset-3');
	image.tabIndex = 0;
	image.setAttribute('role', 'button');
	image.setAttribute('aria-label', `Enlarge ${image.alt || 'image'}`);
	image.addEventListener('click', () => openImageLightbox(image));
	image.addEventListener('keydown', (event) => {
		if (event.key !== 'Enter' && event.key !== ' ') return;
		event.preventDefault();
		openImageLightbox(image);
	});
});

$$('[data-copy-command]').forEach((button) =>
	button.addEventListener('click', async () => {
		const command =
			button.closest('[data-command-block]')?.querySelector('code')
				?.textContent || '';
		try {
			await navigator.clipboard.writeText(command);
			button.textContent = 'Copied';
			showToast('Command copied');
			setTimeout(() => (button.textContent = 'Copy command'), 1300);
		} catch {
			button.textContent = 'Select command';
		}
	}),
);

const feedbackContainer = $('[data-feedback-page]');
const feedbackButtons = $$('[data-feedback-section] button');
if (feedbackContainer && feedbackButtons.length) {
	const feedbackKey = `lightdocs-feedback:${feedbackContainer.dataset.feedbackPage}`;
	const feedbackTokenKey = 'lightdocs-feedback-token';
	const feedbackSummary = $('[data-feedback-summary]', feedbackContainer);
	const applyFeedback = (value) =>
		feedbackButtons.forEach((button) =>
			button.setAttribute('aria-pressed', String(button.dataset.feedback === value)),
		);
	const token = () => {
		try {
			const saved = localStorage.getItem(feedbackTokenKey);
			if (saved) return saved;
			const created = crypto.randomUUID().replace(/-/g, '_');
			localStorage.setItem(feedbackTokenKey, created);
			return created;
		} catch {
			return `${Date.now()}_${Math.random().toString(36).slice(2)}_feedback`;
		}
	};
	const renderFeedbackSummary = (summary) => {
		if (!feedbackSummary) return;
		feedbackSummary.textContent = summary.total > 0
			? `${summary.helpful_percent}% helpful from ${summary.total} response${summary.total === 1 ? '' : 's'}`
			: 'Be the first to respond';
	};
	feedbackButtons.forEach((button) =>
		button.addEventListener('click', async () => {
			const vote = button.dataset.feedback;
			if (!vote) return;
			feedbackButtons.forEach((candidate) => (candidate.disabled = true));
			try {
				const response = await fetch('/feedback', {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded', Accept: 'application/json' },
					body: new URLSearchParams({
						path: feedbackContainer.dataset.feedbackPage || '',
						token: token(),
						vote,
					}),
				});
				const result = await response.json();
				if (!response.ok) throw new Error(result.error || 'Feedback could not be saved.');
				applyFeedback(vote);
				renderFeedbackSummary(result.summary || {});
				try {
					localStorage.setItem(feedbackKey, vote);
				} catch {}
				showToast('Thanks for the feedback');
			} catch (error) {
				showToast(error.message || 'Feedback could not be saved.', 'error');
			} finally {
				feedbackButtons.forEach((candidate) => (candidate.disabled = false));
			}
		}),
	);
	try {
		const saved = localStorage.getItem(feedbackKey);
		if (saved) applyFeedback(saved);
	} catch {}
}

const prefetchedPages = new Map();
const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
const prefetchAllowed = !connection?.saveData && !/^(?:slow-)?2g$/i.test(connection?.effectiveType || '');
async function prefetchPage(link) {
	if (!prefetchAllowed || !link || link.dataset.noPrefetch !== undefined) return;
	const url = new URL(link.href, location.href);
	if (url.origin !== location.origin || url.hash || /\.(?:md|json|xml|txt|pdf)$/i.test(url.pathname) || url.pathname.startsWith('/admin') || prefetchedPages.has(url.href)) return;
	prefetchedPages.set(url.href, 'pending');
	try {
		const response = await fetch(url.href, { credentials: 'same-origin', priority: 'low' });
		if (!response.ok || !String(response.headers.get('content-type')).includes('text/html')) throw new Error();
		prefetchedPages.set(url.href, await response.text());
		while (prefetchedPages.size > 8) prefetchedPages.delete(prefetchedPages.keys().next().value);
	} catch {
		prefetchedPages.delete(url.href);
	}
}
$$('a[href]').forEach((link) => {
	link.addEventListener('pointerenter', () => prefetchPage(link), { once: true, passive: true });
	link.addEventListener('focus', () => prefetchPage(link), { once: true });
});

const graphRoot = $('[data-docs-graph]');
if (graphRoot) {
	const svg = $('[data-graph-canvas]', graphRoot);
	const filter = $('[data-graph-filter]');
	const ns = 'http://www.w3.org/2000/svg';
	let nodes = [];
	let links = [];
	try {
		nodes = JSON.parse(graphRoot.dataset.graphNodes || '[]');
		links = JSON.parse(graphRoot.dataset.graphLinks || '[]');
	} catch {}
	const width = 1000;
	const height = 620;
	svg?.setAttribute('viewBox', `0 0 ${width} ${height}`);
	const positioned = new Map();
	const radius = Math.min(width, height) * .39;
	nodes.forEach((node, index) => {
		const angle = (Math.PI * 2 * index) / Math.max(1, nodes.length) - Math.PI / 2;
		const ring = index % 3 === 0 ? .58 : index % 3 === 1 ? .8 : 1;
		positioned.set(node.url, { ...node, x: width / 2 + Math.cos(angle) * radius * ring, y: height / 2 + Math.sin(angle) * radius * ring });
	});
	links.forEach((link) => {
		const source = positioned.get(link.source);
		const target = positioned.get(link.target);
		if (!source || !target || !svg) return;
		const line = document.createElementNS(ns, 'line');
		line.setAttribute('x1', source.x);
		line.setAttribute('y1', source.y);
		line.setAttribute('x2', target.x);
		line.setAttribute('y2', target.y);
		line.setAttribute('stroke', 'var(--border-strong)');
		line.setAttribute('stroke-opacity', '.6');
		line.dataset.graphSource = link.source;
		line.dataset.graphTarget = link.target;
		svg.append(line);
	});
	positioned.forEach((node) => {
		if (!svg) return;
		const anchor = document.createElementNS(ns, 'a');
		anchor.setAttribute('href', node.url);
		anchor.setAttribute('aria-label', `${node.title}, ${node.inbound} inbound links`);
		anchor.dataset.graphNode = node.url;
		anchor.dataset.graphTitle = node.title.toLowerCase();
		const circle = document.createElementNS(ns, 'circle');
		circle.setAttribute('cx', node.x);
		circle.setAttribute('cy', node.y);
		circle.setAttribute('r', Math.min(16, 7 + Number(node.inbound || 0) * 1.4));
		circle.setAttribute('fill', 'var(--surface-raised)');
		circle.setAttribute('stroke', 'var(--brand)');
		circle.setAttribute('stroke-width', '2');
		circle.setAttribute('class', 'cursor-pointer');
		const title = document.createElementNS(ns, 'title');
		title.textContent = node.title;
		circle.append(title);
		anchor.append(circle);
		if (nodes.length <= 42 || Number(node.inbound) > 1) {
			const label = document.createElementNS(ns, 'text');
			label.setAttribute('x', node.x);
			label.setAttribute('y', node.y + 27);
			label.setAttribute('text-anchor', 'middle');
			label.setAttribute('fill', 'var(--muted)');
			label.setAttribute('class', 'pointer-events-none text-[11px]');
			label.textContent = node.title.length > 24 ? `${node.title.slice(0, 22)}…` : node.title;
			anchor.append(label);
		}
		svg.append(anchor);
	});
	filter?.addEventListener('input', () => {
		const query = filter.value.trim().toLowerCase();
		$$('[data-graph-node]', svg).forEach((node) => node.style.opacity = Boolean(query) && !node.dataset.graphTitle.includes(query) ? '.15' : '1');
		$$('line', svg).forEach((line) => {
			const source = positioned.get(line.dataset.graphSource);
			const target = positioned.get(line.dataset.graphTarget);
			line.style.opacity = Boolean(query) && !source?.title.toLowerCase().includes(query) && !target?.title.toLowerCase().includes(query) ? '.1' : '.6';
		});
		$$('[data-graph-list-item]').forEach((item) => item.classList.toggle('hidden', Boolean(query) && !item.dataset.graphTitle.includes(query)));
	});
}

const runbook = $('[data-page-type="runbook"]');
if (runbook) {
	const checks = $$('[data-markdown-body] input[type="checkbox"]', runbook);
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
		document.documentElement.dataset.sidebarCollapsed === 'true';
	sidebarCollapse?.setAttribute('aria-expanded', String(!collapsed));
	sidebarCollapse?.setAttribute(
		'aria-label',
		collapsed ? 'Expand navigation' : 'Collapse navigation',
	);
}
sidebarCollapse?.addEventListener('click', () => {
	const collapsed = document.documentElement.dataset.sidebarCollapsed !== 'true';
	if (collapsed) document.documentElement.dataset.sidebarCollapsed = 'true';
	else delete document.documentElement.dataset.sidebarCollapsed;
	try {
		localStorage.setItem(
			'lightdocs-sidebar',
			collapsed ? 'collapsed' : 'expanded',
		);
	} catch {}
	syncSidebarCollapse();
});
syncSidebarCollapse();
