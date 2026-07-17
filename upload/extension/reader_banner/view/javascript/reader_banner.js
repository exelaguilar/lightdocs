document.querySelectorAll('[data-reader-banner]').forEach((banner) => {
	const storageKey = 'lightdocs-reader-banner-dismissed:' + (banner.dataset.readerBannerKey || 'default');
	try {
		if (localStorage.getItem(storageKey) === '1') {
			banner.remove();
			return;
		}
	} catch {}

	banner.querySelector('[data-reader-banner-dismiss]')?.addEventListener('click', () => {
		try {
			localStorage.setItem(storageKey, '1');
		} catch {}
		banner.remove();
	});
});
