# Symfonicat

`symfonicat/core` is the complete Symfonicat Symfony application. The public runtime, admin runtime, Doctrine entities, webpack wiring, Electron-facing commands, Docker/FrankenPHP files, and starter templates all live in this repository.

Canonical repository README: <https://github.com/symfonicat/core/blob/main/README.md>

## Install

For local development, point the seeded domains at your Docker host:

```text
127.0.0.1 example.com
127.0.0.1 project1.example.com
```

Run from a clone without requiring PHP or Composer on the host:

```bash
git clone https://github.com/symfonicat/core symfonicat
cd symfonicat
docker compose up -d
```

Or create the project with Composer on a PHP 8.4 host:

```bash
composer create-project symfonicat/core symfonicat
cd symfonicat
docker compose up -d
```

On container startup the `php` service self-installs PHP dependencies with Composer, synchronizes the Doctrine schema, seeds the local development rows, runs `npm install`, and then runs `npm run build`. The seeded rows include `localhost`, `example.com`, `project1`, the `test` application, the `analytics` module, a `/symfonicat/*/test*` application routing rule, and sample `color` env values. The `test` application and `project1` project both have Analytics enabled by default; the test application uses `color=red`, the default domains use `color=blue`, and `project1` uses `color=green`. After the stack is up, create an admin and synchronize filesystem-backed rows:

```bash
docker exec -it php bin/console symfonicat:admin:create <username>
docker exec -it php bin/console symfonicat:schema:update
```

Set `SYMFONICAT_AUTO_NPM_INSTALL=0` or `SYMFONICAT_AUTO_NPM_BUILD=0` on the `php` service if you want to opt out of those automatic frontend steps. The messenger worker disables both by default.

## Core Model

Symfonicat owns these tables:

- `Application` -> `symfonicat_application`
- `ApplicationEnv` -> `symfonicat_application_env`
- `Domain` -> `symfonicat_domain`
- `DomainEnv` -> `symfonicat_domain_env`
- `Project` -> `symfonicat_project`
- `ProjectEnv` -> `symfonicat_project_env`
- `Env` -> `symfonicat_env`
- `Module` -> `symfonicat_module`
- `RoutingRule` -> `symfonicat_routing_rule`
- `Admin` -> `symfonicat_admin`

`Application.id`, `Domain.id`, `Project.id`, `Module.id`, and `Env.id` are string identifiers. `Project.id` is immutable once created and is also the project subdomain, project asset key, and Electron/runtime key. `Module.id` is immutable and is the module backend and frontend entry key. `Application.id` is the application shell key and maps to `assets/applications/{id}` plus `templates/application/overrides/{id}.html.twig`.

`Domain`, `Project`, and `Application` can each attach `Module` rows. Modules are synchronized from `assets/modules/{id}/package.json`; deleted filesystem modules are only removed after the command shows referencing entity rows and receives confirmation.

## Runtime

Symfonicat resolves requests in layers:

1. The request arrives on a base domain or one project subdomain.
2. Subscribers resolve the active `Domain`, optional `Project`, and any matching `RoutingRule`.
3. Redirect and route rules can take over before shell rendering.
4. Application rules can render an application shell for a regex path.
5. Legacy domain/project rules can invert the default domain/project routing behavior for regex paths.
6. The public controller renders a domain or project shell when the request still belongs to the shell layer.

The default public routes are:

- `/` renders the domain shell when no project is resolved.
- `/{path}` renders the project shell when a project is resolved and the project catch-all remains enabled.
- normal public controllers are imported with a guard so project subdomains keep the project catch-all unless a legacy project routing rule disables it for the current regex path.

The key runtime services and subscribers are:

- [DomainService.php](src/Symfonicat/Service/DomainService.php)
- [ProjectService.php](src/Symfonicat/Service/ProjectService.php)
- [ApplicationService.php](src/Symfonicat/Service/ApplicationService.php)
- [ProjectSubscriber.php](src/Symfonicat/EventSubscriber/ProjectSubscriber.php)
- [RoutingRuleSubscriber.php](src/Symfonicat/EventSubscriber/RoutingRuleSubscriber.php)

## Routing Rules

`RoutingRule.arguments` is an ordered list of regex path segments. The list is imploded with `/` and matched against the full current path. For example, arguments `u`, `*`, and `x*` are treated as the path regex `/u/*/x*`; a bare `*` segment is treated as a wildcard segment. Reserved arguments are listed in `RoutingRule::RESERVED_ARGUMENTS`; `admin`, `m`, and `application` are reserved and ignored by runtime matching.

Supported rule types:

- `domain`: legacy rule that renders the domain shell for a matching regex path.
- `project`: legacy rule that disables the project catch-all for a matching regex path so Symfony routes can handle it.
- `application`: either matches regex path arguments and renders the application shell, or attaches an application to a named Symfony route.
- `redirect`: redirects a whole domain or project to a target domain or project, regardless of path.
- `route`: renders a named Symfony route for the root of a domain or project.

Redirect rules use `redirectType` to choose the matched scope (`domain` or `project`) and `redirectTarget` to choose the destination type (`domain`, `project`, or `domain and project`). The combined redirect target renders `redirectProject.id.redirectDomain.id`. Route rules use `routeType` to choose whether the root route applies to a domain or a project. Application rules use `applicationType`: `arguments` matches `RoutingRule.arguments`, while `route` uses `RoutingRule.route` as the Symfony route name that should receive the application context.

The routing-rule admin form groups related fields into cards. The rule card contains `type`, `applicationType`, `redirectType`, `routeType`, and the relevant match settings. Application rules only show `arguments` for `applicationType=arguments`, and only show `route` for `applicationType=route`. The match card shows the relevant domain, project, or application selector. The redirect card keeps `redirectTarget` on the left and the selected redirect domain/project destination on the right; choosing `domain and project` shows both destination fields. The routing-rule list spreads rule data across dedicated columns for arguments, route, domain, project, application, application mode, route type, and redirect targets so type-specific values are not collapsed into one mixed cell.

## Env

`Env` defines keys; scoped env rows define values. Runtime env values are resolved through `EnvService` and exposed to Twig through `env()` and the global `env` array.

Precedence is:

1. `ApplicationEnv`
2. `DomainEnv`
3. `ProjectEnv`

Project values overwrite domain values, and domain values overwrite application values. The base layout emits the merged env map into `window.env`.

## Templates

Public shell templates live under:

- `templates/application/main.html.twig`
- `templates/application/overrides/{application.id}.html.twig`
- `templates/domain/main.html.twig`
- `templates/domain/overrides/{domain.id}.html.twig`
- `templates/project/main.html.twig`
- `templates/project/overrides/{project.id}.html.twig`

Each shell template loads attached module entrypoints before its own scope entrypoint. Application templates use `encore_entry_script_tags_application()` and `encore_entry_link_tags_application()`. Domain and project templates use the corresponding domain/project helpers.

## Assets

Asset source lives in `assets`, and the build target is `public/build`.

Scope entrypoints are discovered from `symfonicat:data:webpack`; the command falls back to filesystem discovery when database-backed rows are unavailable:

- `assets/applications/{id}/index.js` -> `application/{id}`
- `assets/domains/{id}/index.js` -> `domains/{id}`
- `assets/projects/{id}/index.js` -> `projects/{id}`
- `assets/modules/{id}/index.js` -> `modules/{id}`

The public asset stack is separate from the admin asset stack:

- public: `assets/symfonicat.js`, `assets/stimulus.js`, `assets/controllers.json`, `assets/controllers/`
- admin: `assets/symfonicat_admin.js`, `assets/stimulus_admin.js`, `assets/controllers_admin.json`, `assets/controllers_admin/`

Admin-specific JavaScript belongs in the admin stack and should not be registered in the public Stimulus bridge.

## Module Runtime

Backend module controllers live under `/m/{id}` and should extend [AbstractModuleController.php](src/Symfonicat/Controller/AbstractModuleController.php). A module endpoint only runs when the current project, application, or domain context has that module attached. Application modules are loaded by application shell templates as frontend entrypoints.

Application shells expose the active application id and a signed, expiring CSRF token through `applicationHelper()`. Browser module requests send those values as headers, and `ApplicationService` resolves the application from the signed request context before `/m/{id}` is allowed to run from an application module attachment.

Application URLs are generated through `ApplicationService`. `path('symfonicat_application', {id: 'test'})`, `path_application('test')`, and `path_application(application)` all resolve through the application routing rule instead of the internal controller path. For the seeded `test` application rule `/symfonicat/*/test*`, those helpers produce `/symfonicat/*/test`; passing a path appends it, and `path_application('test', 'somepath/path2', ['tay'])` or `path_application(application, 'somepath/path2', ['tay'])` replaces the wildcard segment to produce `/symfonicat/tay/test/somepath/path2`. Route-based application rules generate the configured Symfony route path instead. The internal `/application/{id}/{path}` route renders the same application shell and uses client-side history replacement to show the public application URL.

Browser-side module requests use the string helpers installed by [module.js](assets/module.js):

```javascript
'analytics'.log('module active!')

const rootJson = await 'analytics'.json({ event: 'pageview' });
const nestedJson = await 'analytics'.json('events/pageview', { path: window.location.pathname });

const rootHtml = await 'frame'.html({ slot: 'main' });
const nestedHtml = await 'frame'.html('partials/card', { id: 'hero' });
```

- `''.json(payload)` posts JSON to `/m/{moduleId}` and parses the response as JSON.
- `''.json(path, payload)` posts JSON to `/m/{moduleId}/{path}` and parses the response as JSON.
- `''.html(payload)` posts JSON to `/m/{moduleId}` and returns the response body as HTML text.
- `''.html(path, payload)` posts JSON to `/m/{moduleId}/{path}` and returns the response body as HTML text.
- `''.log(...args)` behaves like `console.log(...)`, but prefixes output with `[module][{moduleId}]:`.
- application module requests also include the application id, request flag, and signed CSRF token headers.

## Admin

Admin lives under `/admin` and is isolated from any host app user system.

- admin users live in `symfonicat_admin`
- admin login is session-backed and configured through Symfony security YAML
- TOTP MFA is required after first-factor login
- admin lookups are cached through Redis-backed `cache.app`
- admin assets use the `symfonicat_admin` entrypoint and admin Stimulus app
- application CRUD lives under `/admin/a*`
- domain CRUD lives under `/admin/d*`
- project CRUD lives under `/admin/p*`
- env CRUD lives under `/admin/e*`
- routing-rule CRUD lives under `/admin/r*`
- modules do not have admin CRUD; module rows come from `symfonicat:schema:update`

Useful commands:

```bash
docker exec -it php bin/console symfonicat:admin:create <email>
docker exec php bin/console symfonicat:admin:delete <email>
```

## Commands

Important Symfonicat commands include:

- `symfonicat:bootstrap`: waits for the database, synchronizes the schema, and seeds local defaults
- `symfonicat:schema:update`: synchronizes module rows from `assets/modules/*/package.json`, application rows from `assets/applications/*`, and project rows from `assets/projects/*`
- `symfonicat:data:webpack`: emits application/domain/project/module entry data for webpack with filesystem fallback
- `symfonicat:admin:create`: creates or updates an admin, prompts for a hidden password, and prints the MFA QR code
- `symfonicat:admin:delete`: deletes an admin
- `symfonicat:public-suffix:refresh`: refreshes `public_suffix_list.dat`
- `electron:*`: prepares, runs, builds, and packages the Electron shell

Run `symfonicat:schema:update` with an interactive terminal whenever it may need confirmation. With Docker, use `docker exec -it php bin/console symfonicat:schema:update`; without `-it`, the command fails instead of silently accepting defaults.

## Electron

Electron talks to the live Symfony server over HTTP. Project data remains keyed by `Project.id`, and the Electron commands continue to use the same database/runtime model as the public web shell.
