 const logMercure = (message, payload) => {
  if (typeof console === 'undefined') {
    return;
  }
  if (payload !== undefined) {
    console.info('[mercure]', message, payload);
  } else {
    console.info('[mercure]', message);
  }
};

const logTurbo = (message, payload) => {
  if (typeof console === 'undefined') {
    return;
  }
  if (payload !== undefined) {
    console.info('[turbo-stream]', message, payload);
  } else {
    console.info('[turbo-stream]', message);
  }
};

const setMercureDebug = (status) => {
  if (!document || !document.documentElement) {
    return;
  }
  document.documentElement.dataset.mercureHelper = status;
};

const initMercureHelper = () => {
  const configEl = document.getElementById('mercure-config');
  if (!configEl) {
    return;
  }

  if (window.mercure && window.mercure._initialized) {
    setMercureDebug('already-initialized');
    logMercure('already initialized');
    configEl.remove();
    return;
  }

  let config = null;
  try {
    config = JSON.parse(configEl.textContent || '{}');
  } catch (error) {
    config = null;
  }
  configEl.remove();

  const hubUrl = config && config.hubUrl;
  const topic = config && config.topic;
  const token = config && config.token;

  if (!hubUrl || !topic || !token) {
    setMercureDebug('missing-config');
    logMercure('missing config', { hubUrl, topic });
    return;
  }

  setMercureDebug('initialized');
  logMercure('initialized helper', { hubUrl, topic });
  const decoder = new TextDecoder('utf-8');
  const listeners = new Map();
  let abortController = null;
  let currentTopic = topic;
  let reconnectTimer = null;
  let isConnecting = false;
  let isConnected = false;
  let currentUrl = null;
  let lastEventId = null;

  const emit = (event, payload, id) => {
    const handlers = listeners.get(event);
    if (!handlers) {
      return;
    }

    if (id) {
      lastEventId = id;
    }
    handlers.forEach((handler) => handler({ data: payload, lastEventId: id }));
    logMercure('event received', { event, id });
  };

  const parseStream = async (reader) => {
    let buffer = '';
    let event = 'message';
    let data = '';
    let lastEventId = '';

    while (true) {
      const { value, done } = await reader.read();
      if (done) {
        logMercure('stream closed');
        break;
      }

      buffer += decoder.decode(value, { stream: true });

      let lineEnd = buffer.indexOf('\n');
      while (lineEnd !== -1) {
        const line = buffer.slice(0, lineEnd).replace(/\r$/, '');
        buffer = buffer.slice(lineEnd + 1);

        if (line === '') {
          if (data !== '') {
            emit(event, data.replace(/\n$/, ''), lastEventId);
          }
          event = 'message';
          data = '';
          lastEventId = '';
        } else if (line.startsWith('event:')) {
          event = line.slice(6).trim() || 'message';
        } else if (line.startsWith('data:')) {
          data += `${line.slice(5).trim()}\n`;
        } else if (line.startsWith('id:')) {
          lastEventId = line.slice(3).trim();
        }

        lineEnd = buffer.indexOf('\n');
      }
    }
  };

  const buildUrl = (nextTopic) => {
    const url = new URL(hubUrl);
    url.searchParams.append('topic', nextTopic);
    if (lastEventId) {
      url.searchParams.append('lastEventID', lastEventId);
      url.searchParams.append('Last-Event-ID', lastEventId);
    }
    return url.toString();
  };

  const connect = async () => {
    if (isConnecting) {
      return;
    }

    if (abortController) {
      abortController.abort();
    }

    const url = buildUrl(currentTopic);
    if (isConnected && currentUrl === url) {
      return;
    }

    abortController = new AbortController();
    currentUrl = url;
    isConnecting = true;

    try {
      logMercure('connecting', { url });
      const response = await fetch(url, {
        method: 'GET',
        headers: {
          Accept: 'text/event-stream',
          Authorization: `Bearer ${token}`
        },
        signal: abortController.signal,
        cache: 'no-store',
        credentials: 'omit'
      });

      if (!response.ok || !response.body) {
        throw new Error(`Mercure stream failed (${response.status})`);
      }

      logMercure('stream connected', { url });
      isConnected = true;
      await parseStream(response.body.getReader());
    } catch (error) {
      if (abortController && abortController.signal.aborted) {
        return;
      }
      const message = error instanceof Error ? error.message : 'unknown error';
      logMercure('stream error', { message });
      isConnected = false;
    } finally {
      isConnecting = false;
      if (!abortController || abortController.signal.aborted) {
        return;
      }

      reconnectTimer = window.setTimeout(connect, 3000);
    }
  };

  const disconnect = () => {
    if (reconnectTimer) {
      window.clearTimeout(reconnectTimer);
      reconnectTimer = null;
    }

    isConnected = false;
    if (abortController) {
      abortController.abort();
      abortController = null;
    }
  };

  const subscribe = (nextTopic) => {
    if (!nextTopic) {
      return;
    }

    currentTopic = nextTopic;
    connect();
  };

  const addEventListener = (event, handler) => {
    if (!listeners.has(event)) {
      listeners.set(event, new Set());
    }
    listeners.get(event).add(handler);
  };

  const removeEventListener = (event, handler) => {
    const handlers = listeners.get(event);
    if (handlers) {
      handlers.delete(handler);
    }
  };

  window.mercure = {
    connect,
    disconnect,
    subscribe,
    addEventListener,
    removeEventListener,
    _initialized: true
  };

  const start = () => {
    if (!window.mercure) {
      return;
    }
    connect();
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }

  document.addEventListener('turbo:load', start);
  document.addEventListener('turbo:before-cache', disconnect);
};

initMercureHelper();

const logTurboStreamListeners = () => {
  const elements = document.querySelectorAll('[data-controller~=\"symfony--ux-turbo--mercure-turbo-stream\"]');
  logTurbo('listeners', {
    count: elements.length,
    turbo: typeof window.Turbo !== 'undefined',
    url: window.location.href
  });
  elements.forEach((element) => {
    const hub = element.getAttribute('data-symfony--ux-turbo--mercure-turbo-stream-hub-value');
    const topic = element.getAttribute('data-symfony--ux-turbo--mercure-turbo-stream-topic-value');
    const topics = element.getAttribute('data-symfony--ux-turbo--mercure-turbo-stream-topics-value');
    logTurbo('listener', { hub, topic, topics, tag: element.tagName, id: element.id });
  });
};

const logTurboStreamEvent = (event) => {
  const stream = event.target;
  if (!stream || !stream.getAttribute) {
    return;
  }
  const action = stream.getAttribute('action');
  const target = stream.getAttribute('target');
  const targetEl = target ? document.getElementById(target) : null;
  logTurbo('stream received', { action, target });
  if (target && !targetEl) {
    logTurbo('missing target element', { target });
  }
  if (target === 'mercure-debug') {
    logTurbo('debug stream rendered', { action, target });
  }
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', logTurboStreamListeners);
} else {
  logTurboStreamListeners();
}

document.addEventListener('turbo:load', logTurboStreamListeners);
document.addEventListener('turbo:before-stream-render', logTurboStreamEvent);
