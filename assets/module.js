function trimSlashes(value = '') {
    return String(value).trim().replace(/^\/+|\/+$/g, '');
}

function buildModulePath(moduleName, path = '') {
    const raw = trimSlashes(moduleName);
    if (raw === '') {
        throw new Error('Missing module name.');
    }

    // Normalize vendor package names like "symfonicat/analytics" to "analytics/main",
    // and preserve existing "analytics/main" or simple "analytics" names.
    let moduleSlug = raw;
    if (moduleSlug.startsWith('symfonicat/')) {
        moduleSlug = moduleSlug.replace(/^symfonicat\//, '');
        if (!moduleSlug.includes('/')) {
            moduleSlug = `${moduleSlug}/main`;
        }
    }

    const modulePath = trimSlashes(path);
    return modulePath === '' ? `/m/${moduleSlug}` : `/m/${moduleSlug}/${modulePath}`;
}

function normalizeRequestArguments(pathOrPayload, payload) {
    if (typeof pathOrPayload === 'string') {
        return {
            path: pathOrPayload,
            payload: payload ?? {},
        };
    }

    return {
        path: '',
        payload: pathOrPayload ?? {},
    };
}

function getApplicationContextHeaders() {
    if (typeof window === 'undefined') {
        return {};
    }

    const context = window.application ?? {};
    const applicationId = String(context.id ?? '').trim();
    const csrfToken = String(context.csrfToken ?? '').trim();
    const headers = {};

    if (applicationId !== '') {
        headers[String(context.requestHeader ?? 'X-Symfonicat-Application-Request')] = '1';
        headers['X-Symfonicat-Application'] = applicationId;
    }

    if (csrfToken !== '') {
        headers[String(context.tokenHeader ?? 'X-Symfonicat-Application-Token')] = csrfToken;
    }

    return headers;
}

async function requestModule(moduleName, responseType, path = '', payload = {}) {
    const response = await fetch(buildModulePath(moduleName, path), {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: responseType === 'html' ? 'text/html' : 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...getApplicationContextHeaders(),
        },
        body: JSON.stringify(payload ?? {}),
    });

    if (!response.ok) {
        const details = await response.text();
        const message = details.trim() || `Module request failed with status ${response.status}.`;
        const error = new Error(message);
        error.status = response.status;
        throw error;
    }

    if (responseType === 'html') {
        return await response.text();
    }

    const text = await response.text();
    return text.trim() === '' ? {} : JSON.parse(text);
}

function installPrototypeMethod(name) {
    if (typeof String.prototype[name] === 'function') {
        return;
    }

    Object.defineProperty(String.prototype, name, {
        configurable: true,
        writable: true,
        value(pathOrPayload = '', payload = {}) {
            const normalized = normalizeRequestArguments(pathOrPayload, payload);
            return requestModule(String(this), name, normalized.path, normalized.payload);
        },
    });
}

function installPrototypeLogMethod() {
    if (typeof String.prototype.log === 'function') {
        return;
    }

    Object.defineProperty(String.prototype, 'log', {
        configurable: true,
        writable: true,
        value(...args) {
            if (typeof console === 'undefined' || typeof console.log !== 'function') {
                return;
            }

            const moduleName = trimSlashes(String(this));

            console.log(
                '%c[module]%c[%s]:',
                'color: #6ec1ff',
                'font-weight: 700',
                moduleName,
                ...args,
            );
        },
    });
}

installPrototypeMethod('json');
installPrototypeMethod('html');
installPrototypeLogMethod();
