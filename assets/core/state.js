function toBoolean(value, fallback = false) {
    if (typeof value === 'boolean') {
        return value;
    }

    if (typeof value === 'string') {
        const normalized = value.trim().toLowerCase();
        if (normalized === '1' || normalized === 'true') {
            return true;
        }
        if (normalized === '0' || normalized === 'false') {
            return false;
        }
    }

    if (typeof value === 'number') {
        return value !== 0;
    }

    return fallback;
}

function toStringValue(value, fallback = '') {
    if (value === null || value === undefined) {
        return fallback;
    }

    return String(value);
}

function setInputValue(input, value = '') {
    if (!input) {
        return;
    }

    input.value = toStringValue(value, '');
}

function setTextValue(element, value, fallback = '') {
    if (!element) {
        return;
    }

    const text = toStringValue(value, fallback).trim();
    element.textContent = text === '' ? fallback : text;
}

export {
    setInputValue,
    setTextValue,
    toBoolean,
    toStringValue,
};
