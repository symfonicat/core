const brotliPromise = require('../vendor/brotli-wasm/index.browser.js');

function trimSlashes(value = '') {
    return String(value).trim().replace(/^\/+|\/+$/g, '');
}

function buildModulePath(moduleName, path = '') {
    const raw = trimSlashes(moduleName);
    if (raw === '') {
        throw new Error('Missing module name.');
    }

    const modulePath = trimSlashes(path);
    return modulePath === '' ? `/m/${raw}` : `/m/${raw}/${modulePath}`;
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

function getRequestHeaders() {
    if (typeof window === 'undefined') {
        return {};
    }

    const requestContext = window.request ?? {};
    const contextId = String(requestContext.contextId ?? requestContext.context_id ?? '').trim();
    const token = String(requestContext.token ?? '').trim();

    if (contextId === '' || token === '') {
        return {};
    }

    return {
        'X-Symfonicat-Module-Context': contextId,
        'X-CSRF-Token': token,
    };
}

async function brotliEncodeJson(payload) {
    const json = JSON.stringify(payload ?? {});
    const input = new TextEncoder().encode(json);
    const brotli = await brotliPromise;
    const body = brotli.compress(input, { quality: 6 });

    if (!(body instanceof Uint8Array)) {
        throw new Error('Brotli compression failed.');
    }

    return body;
}

async function requestModule(moduleName, responseType, path = '', payload = {}) {
    const body = await brotliEncodeJson(payload);

    const response = await fetch(buildModulePath(moduleName, path), {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: responseType === 'html' ? 'text/html' : 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...getRequestHeaders(),
        },
        body,
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

            const moduleName = parseModuleName(String(this));

            console.log(
                '%c[mod]%c[%s]%c[%s]:',
                'color: #6ec1ff; font-weight: 700',
                'color: #fff; font-weight: 700',
                moduleName.packageName,
                'color: #8eea8e',
                moduleName.modName,
                ...args,
            );
        },
    });
}

function parseModuleName(value = '') {
    const raw = trimSlashes(value);
    const parts = raw.split('/').filter(Boolean);

    if (parts.length >= 3) {
        return {
            packageName: `${parts[0]}/${parts[1]}`,
            modName: parts.slice(2).join('/'),
        };
    }

    return {
        packageName: raw,
        modName: '',
    };
}

installPrototypeMethod('json');
installPrototypeMethod('html');
installPrototypeLogMethod();
