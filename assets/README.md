# assets/module.js

Minimal browser-only module request API.

## Usage
```javascript
'analytics'.log('module active!')

// data1 is dummy data that gets posted to /m/analytics
const parsedJson = await 'analytics'.json({ data1: true });
const parsedJsonWithPath = await 'analytics'.json('path/secondpath', { data1: true });

'analytics'.log('/m/analytics result:', parsedJson)
'analytics'.log('/m/analytics/path/secondpath result:', parsedJsonWithPath)

// data2 is dummy data that gets posted to /m/frame
const parsedHtml = await 'frame'.html({ data2: true });
const parsedHtmlWithPath = await 'frame'.html('path/secondpath', { data2: true });
```

These helpers are bootstrapped by `assets/symfonicat.js`. Module entrypoints do not need to import `./module` directly.

## Behavior
- Every request starts with `/m/{moduleId}`.
- If a path is provided, it becomes `/m/{moduleId}/{path}`.
- If no path is provided, the request goes to `/m/{moduleId}`.
- Both helpers use `POST`.
- Application shells set `window.application`; when present, module requests send the application id, request flag, and signed CSRF token headers so `/m/{moduleId}` can be authorized against the active application.
- The base layout also sets `window.electron` to a boolean that indicates whether the current request is running in Electron mode.
- `''.json(payload)` posts JSON to `/m/{moduleId}` and parses the response as JSON.
- `''.json(path, payload)` posts JSON to `/m/{moduleId}/{path}` and parses the response as JSON.
- `''.html(payload)` posts JSON to `/m/{moduleId}` and returns the response body as HTML text.
- `''.html(path, payload)` posts JSON to `/m/{moduleId}/{path}` and returns the response body as HTML text.
- `''.log(...args)` behaves like `console.log(...)`, but prefixes the output with a styled module label such as `[module][analytics]:`
