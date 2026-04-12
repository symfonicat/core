const BLOCKED_PREFIXES = ['/m/', '/_', '/connect'];

function toAbsoluteUrl(raw) {
    return new URL(raw, window.location.origin);
}

function isAppPath(pathname, blockedPrefixes = BLOCKED_PREFIXES) {
    if (pathname === '/') {
        return true;
    }

    return !blockedPrefixes.some((prefix) => pathname.startsWith(prefix));
}

function pushHistory(path, replace = false) {
    const normalized = normalizeHistoryPath(path);
    const current = `${window.location.pathname}${window.location.search}${window.location.hash}`;

    if (normalized === current) {
        return;
    }

    if (replace) {
        window.history.replaceState({}, '', normalized);
    } else {
        window.history.pushState({}, '', normalized);
    }
}

function normalizeHistoryPath(path) {
    try {
        const url = toAbsoluteUrl(path);
        return `${url.pathname}${url.search}${url.hash}`;
    } catch (error) {
        return path;
    }
}

function isPlainLeftClick(event) {
    return event.button === 0 && !event.metaKey && !event.ctrlKey && !event.shiftKey && !event.altKey;
}

function resolveNavigableLink(event) {
    if (event.defaultPrevented || !isPlainLeftClick(event)) {
        return null;
    }

    const link = event.target.closest('a');
    if (!link) {
        return null;
    }

    const href = link.getAttribute('href');
    if (!href || href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:') || href.startsWith('javascript:')) {
        return null;
    }

    if (link.target && link.target !== '_self') {
        return null;
    }

    let url;
    try {
        url = toAbsoluteUrl(href);
    } catch (error) {
        return null;
    }

    if (url.origin !== window.location.origin || !isAppPath(url.pathname)) {
        return null;
    }

    return {
        url,
        href,
        path: `${url.pathname}${url.search}${url.hash}`,
    };
}

export {
    isAppPath,
    isPlainLeftClick,
    normalizeHistoryPath,
    pushHistory,
    resolveNavigableLink,
};
