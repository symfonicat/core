function normalizePath(value = '') {
    const path = String(value).trim();

    return path === '' ? '' : `/${path.replace(/^\/+/, '')}`;
}

function redirectApplicationUrl() {
    if (typeof window === 'undefined' || !window.history?.replaceState) {
        return;
    }

    const context = window.application ?? {};
    const redirectTo = normalizePath(context.redirectTo ?? '');

    if (redirectTo === '') {
        return;
    }

    const current = `${window.location.pathname}${window.location.search}${window.location.hash}`;
    if (current === redirectTo) {
        return;
    }

    window.history.replaceState(window.history.state, '', redirectTo);
}

redirectApplicationUrl();
