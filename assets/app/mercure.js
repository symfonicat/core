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

const logTurboStreamListeners = () => {
  const elements = document.querySelectorAll('[data-controller~="symfony--ux-turbo--mercure-turbo-stream"]');
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
