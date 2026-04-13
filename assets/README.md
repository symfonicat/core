# assets/module.js

Minimal browser-only module request API.

## Usage
```javascript
const parsedJson = await 'analytics'.json({ working: true });
const parsedJsonWithPath = await 'analytics'.json('path/secondpath', { working: true });
const parsedHtml = await 'frame'.html({ working: true });
const parsedHtmlWithPath = await 'frame'.html('path/secondpath', { working: true });
```

These helpers are bootstrapped by `assets/app.js`. Module entrypoints do not need to import `./module` directly.

## Behavior
- Every request starts with `/m/{module}`.
- If a path is provided, it becomes `/m/{module}/{path}`.
- If no path is provided, the request goes to `/m/{module}`.
- Both helpers use `POST`.
- `String.prototype.json(...)` returns parsed JSON.
- `String.prototype.html(...)` returns response text.
