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

These helpers are bootstrapped by `assets/app.js`. Module entrypoints do not need to import `./module` directly.

## Behavior
- Every request starts with `/m/{moduleId}`.
- If a path is provided, it becomes `/m/{moduleId}/{path}`.
- If no path is provided, the request goes to `/m/{moduleId}`.
- Both helpers use `POST`.
- Application shells set `window.symfonicatApplication`; when present, module requests send it as context headers so `/m/{moduleId}` can be authorized against the active application.
- `.log(...)` behaves like `console.log(...)`, but prefixes the output with a styled module label such as `[module][analytics]:`
- `.json(...)` expects a JSON response body.
- `.html(...)` expects a raw HTML response body.
