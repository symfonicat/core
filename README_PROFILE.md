# Symfonicat

`symfonicat/core` is the full Symfonicat Symfony application. It ships the public runtime, admin runtime, Doctrine model, webpack integration, Electron-facing commands, and Docker/FrankenPHP starter shell in one repository.

Bootstrap seeds `localhost`, `example.com`, `project1`, the `test` application, the `analytics` module, a `/symfonicat/*/test*` application routing rule, and sample `color` env values. The `test` application and `project1` project have Analytics enabled by default; the test application uses `color=red`.

## Runtime Shape

- `Domain` resolves the base host.
- `Project` resolves the single supported subdomain layer.
- `Application` resolves from `RoutingRule` regex path matches.
- `/` renders the domain shell when no project is active.
- `/{path}` renders the project shell when a project is active and the project catch-all remains enabled.
- top-level public controllers are guarded so project subdomains keep the project catch-all unless a legacy project routing rule disables it for the current regex path.
- application shells expose a signed CSRF-protected application request context so `/m/{module}` calls can run when the module is attached to that application.
- application URLs can be generated with `path('symfonicat_application', {id: 'test'})`, `path_application('test')`, or `path_application(application)`, and all resolve through the matching routing rule rather than the internal `/application/{id}` route.

## Routing Rules

`RoutingRule.arguments` is a multifield list of regex path segments. The segments are joined with `/` and matched against the full current path. Reserved arguments live in `RoutingRule::RESERVED_ARGUMENTS`; `admin` is reserved and ignored by runtime matching.

Supported rule types:

- `domain`: render the domain shell for a matching regex path.
- `project`: bypass the project catch-all for a matching regex path.
- `application`: either match regex path arguments and render the application shell, or attach an application to a named Symfony route.
- `redirect`: redirect a whole domain or project to a domain or project.
- `route`: render a named Symfony route for a domain root or project root.

Application rules use `applicationType`: `arguments` matches the regex segment list, and `route` uses the Symfony route name stored in `route`. For the seeded `test` application rule `/symfonicat/*/test*`, `path_application('test')` or `path_application(application)` returns `/symfonicat/*/test`, and `path_application('test', 'somepath/path2', ['tay'])` or `path_application(application, 'somepath/path2', ['tay'])` returns `/symfonicat/tay/test/somepath/path2`. Argument-based application rules define the base path, while route-based application rules generate the configured Symfony route path through `ApplicationService`.

The routing-rule form keeps type selectors in the rule card, shows `applicationType` when the rule type is `application`, shows either the regex `arguments` collection or the `route` field based on that application type, keeps scope selectors in the match card, and keeps redirect target on the left with redirect destination on the right. The routing-rule list links application rows only when an application target exists.

## Env

Runtime env values are resolved through `EnvService` and exposed through Twig `env()`.

Precedence is:

1. application env
2. domain env
3. project env

Project values overwrite domain values, and domain values overwrite application values.

## Assets

Webpack entries are discovered from `symfonicat:data:webpack`, with database-backed rows and filesystem fallback under:

- `assets/application/{id}` -> `application/{id}`
- `assets/domains/{id}` -> `domains/{id}`
- `assets/projects/{id}` -> `projects/{id}`
- `assets/modules/{id}` -> `modules/{id}`

The public asset stack uses `assets/symfonicat.js`, `assets/stimulus.js`, `assets/controllers.json`, and `assets/controllers/`. The admin asset stack uses `assets/symfonicat_admin.js`, `assets/stimulus_admin.js`, `assets/controllers_admin.json`, and `assets/controllers_admin/`.

Public module JavaScript uses string helpers installed by `assets/module.js`: `''.json(payload)` posts to `/m/{moduleId}` and parses JSON, `''.json(path, payload)` posts to `/m/{moduleId}/{path}`, `''.html(...)` uses the same argument forms but returns response HTML text, and `''.log(...args)` prefixes console output with `[module][{moduleId}]:`.

## Sync

`symfonicat:schema:update` synchronizes:

- modules from `assets/modules/{id}/package.json`
- applications from `assets/application/{id}`
- projects from `assets/projects/{id}`

Missing application and project rows are created with only their `id`. Module deletions are confirmed when referencing entity rows exist.

Run schema sync with an interactive terminal when confirmations may be needed. With Docker, use `docker exec -it php bin/console symfonicat:schema:update`; non-interactive runs fail instead of accepting defaults.

## Admin

Admin lives under `/admin`, uses its own `Admin` entity/table, and is isolated from any host user system. Login is Symfony security session auth plus TOTP MFA, with admin lookups cached in Redis. Admin CRUD covers applications at `/admin/a*`, domains at `/admin/d*`, projects at `/admin/p*`, env keys at `/admin/e*`, and routing rules at `/admin/r*`.

Admins are managed with:

```bash
bin/console symfonicat:admin:create <username>
bin/console symfonicat:admin:delete <email>
```

`symfonicat:admin:create` prompts for the password with hidden input, so Docker usage should include `-it`.

For full install, Docker, Electron, and runtime details, see [README.md](https://github.com/symfonicat/core/blob/main/README.md).
