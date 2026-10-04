// Feedback only: the browser remains responsible for following the anchor.
export function eligibleNavigationLink(event, location) {
    if (event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return null;
    const link = event.target?.closest?.('a[data-navigation-skeleton]');
    if (!link || link.closest('form') || link.hasAttribute('download')) return null;
    if (link.getAttribute('target') && link.getAttribute('target').toLowerCase() !== '_self') return null;
    if ([...link.attributes].some(({ name }) => name.startsWith('@') || name.startsWith('x-') || /^(data-(modal|toggle|action|method)|aria-(controls|haspopup))/.test(name))) return null;
    if (link.getAttribute('role') === 'button') return null;
    const href = link.getAttribute('href');
    if (!href || href.includes('#')) return null;
    try {
        const url = new URL(href, location.href);
        if (!['http:', 'https:'].includes(url.protocol) || url.origin !== location.origin || /\/logout\/?$/.test(url.pathname)) return null;
    } catch {
        return null;
    }
    return link;
}

export function initializeNavigationSkeleton(window, document) {
    const region = document.querySelector('[data-navigation-loading-region]');
    if (!region) return;
    const status = region.querySelector('[data-navigation-loading-status]');
    const reset = () => {
        region.classList.remove('is-navigation-loading');
        region.removeAttribute('aria-busy');
        if (status) status.textContent = '';
    };
    window.addEventListener('click', (event) => {
        const link = eligibleNavigationLink(event, window.location);
        if (!link) return;
        const variant = link.getAttribute('data-navigation-skeleton');
        const layout = [...region.querySelectorAll('[data-navigation-skeleton-layout]')]
            .find((element) => element.getAttribute('data-navigation-skeleton-layout') === variant);
        // If CSS did not load, leave the real content visible and usable.
        if (!layout || window.getComputedStyle(region).getPropertyValue('--navigation-skeleton-ready').trim() !== '1') return;
        region.classList.add('is-navigation-loading');
        region.setAttribute('aria-busy', 'true');
        if (status) status.textContent = 'Loading page.';
    });
    window.addEventListener('pageshow', reset);
    window.addEventListener('pagehide', reset);
}
