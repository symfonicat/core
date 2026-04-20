# Symfonicat

`symfonicat/core` is the full Symfonicat Symfony application: admin, public runtime, webpack, Electron, and the FrankenPHP-oriented starter shell all live here

Canonical repository README: <https://github.com/symfonicat/core/blob/main/README.md>


## Install

```bash
composer create-project symfonicat/core symfonicat
cd symfonicat
docker compose up -d
npm install
npm run dev
```

Add these local host entries so the seeded domain and project resolve to your local stack:

```text
127.0.0.1 example.com
127.0.0.1 project1.example.com
```

You don't need to run `doctrine:schema:create` to get the UI up locally. On first container boot, the `php` service bootstraps the local stack:

- synchronizes the Doctrine schema
- seeds a `localhost` domain row
- seeds an `example.com` domain row
- seeds a `project1` project named `Project 1`
- attaches `Project 1` to `example.com`
- seeds `color=blue` for `localhost` and `example.com`
- seeds `color=green` for `Project 1`

After the containers are up, create an admin:

```bash
docker exec php bin/console symfonicat:admin:create <username> <password>
docker exec php bin/console symfonicat:schema:update
```

## Files To Look At

go to `example.com` and `project1.example.com`, open up DevTools, and then check out these files after running `symfonicat:schema:update`:

- [`assets/domains/example.com/index.js`](assets/domains/example.com/index.js)
- [`assets/projects/project1/index.js`](assets/projects/project1/index.js)
- [`assets/modules/analytics/index.js`](assets/modules/analytics/index.js)
- [`src/Symfonicat/Controller/Module/AnalyticsController.php`](src/Symfonicat/Controller/Module/AnalyticsController.php)
- [`templates/domain/main.html.twig`](templates/domain/main.html.twig)
- [`templates/project/main.html.twig`](templates/project/main.html.twig)

## Philosophy

- only supports one subdomain layer
- a subdomain (`Project`) is usually a second-tier section of a site, so Symfonicat treats it as a first-class runtime shell
- `Project` entities exist in the database for Symfony-native referencing, modularity, extension, and tracking
- `Project` entities can be prepared for Electron applications
- `Project.id` is the canonical, immutable project identifier; it is also the subdomain label, project asset key, and Electron/runtime key
- the admin project form only exposes the `id` field while creating a project; once set, the edit form removes the field and prefixes the `name` label with the immutable project id
- the admin project form uses Bootstrap 5 grid rows for the `name` and `icon` fields rather than legacy `col-xs-*` classes
- the admin domain form uses Bootstrap 5 alignment utilities for the save-button row, not legacy `text-right`
- the shared admin env collection partial passes `pt-0` through `form_label(..., { label_attr: ... })` so the Bootstrap 5 legend does not get top padding
- `symfonicat:schema:update` treats `assets/modules/{id}` as the source of truth for `Module` rows, reads each module name from `assets/modules/{id}/package.json`, and prompts before removing referenced modules that no longer exist on disk
- projects are default client-side routed: when a `Project` is active, the public runtime uses a catch-all route so the rest of the URL can be client-side routed
- domains are default Symfony-side routed: when there is no active `Project`, public paths stay in the normal Symfony route table unless explicitly inverted
- `RoutingRule.argument` inverses that default behavior by the first path segment for either a `Domain` or a `Project`
- a domain-scoped routing rule catches that argument into the domain shell so the domain bundle handles it through `templates/domain/main.html.twig`
- a project-scoped routing rule disables the project catch-all for that argument so Symfony handles the request even though a `Project` is present

## Included

- the public frontend runtime for domains, projects, modules, and routing rules
- the separate `/admin` runtime and admin templates
- the internal `SymfonicatBundle` service/entity/template organization
- shared frontend assets under `assets`
- the webpack helper [webpack.symfonicat.js](webpack.symfonicat.js)
- the Electron desktop shell under [electron](electron)
- the Docker image ships `ext-tidy` so rendered Twig body-block HTML can be formatted at render time
- drop-in FrankenPHP infrastructure files such as [compose.yaml](compose.yaml) and [Caddyfile](Caddyfile)

## Runtime Model

Symfonicat resolves requests in layers.

1. A request arrives on the base domain or a subdomain.
2. Subscribers resolve the active `Domain`, current `Project`, and the first path segment argument.
3. Domain requests are default Symfony-side routed and project requests are default client-side routed through the project catch-all.
4. `RoutingRule` can inverse that default for a matching argument:
   - a domain rule catches the argument into the domain shell and renders `templates/domain/main.html.twig`
   - a project rule disables the project catch-all and lets the normal Symfony route table handle the request
5. The public controller decides whether to render a domain shell or a project shell when the request is still on the shell path.
6. Encore entrypoints are selected from the current database-backed domain, project, and module state.

`MainController` resolves the active domain and project once per request and reuses them for route overrides plus shell rendering.

The public entry routes live in [MainController.php](src/Symfonicat/Controller/MainController.php):

- `/` renders the domain shell when there is no resolved project.
- `/{path}` renders the project shell when a project is resolved onto the request.
- domain paths without a resolved project are left for the Symfony app route table.
- project paths with a resolved project use the project catch-all unless a matching project routing rule disables it for that argument.

Resolution is driven primarily by subdomain and routing-rule context. The key runtime pieces are:

- [ProjectService.php](src/Symfonicat/Service/ProjectService.php)
- [ProjectSubscriber.php](src/Symfonicat/EventSubscriber/ProjectSubscriber.php)
- [RoutingRuleSubscriber.php](src/Symfonicat/EventSubscriber/RoutingRuleSubscriber.php)
- [DomainRedirectSubscriber.php](src/Symfonicat/EventSubscriber/DomainRedirectSubscriber.php)

## Routing Rules

`RoutingRule` records are first-path-segment routing inversions stored on the `argument` field.

- `TYPE_DOMAIN` applies to a `Domain`
- `TYPE_PROJECT` applies to a `Project`
- the rule matches the first path segment argument from the current request
- domains are default Symfony-side routed, so a matching domain rule catches that argument into the domain shell and renders `templates/domain/main.html.twig`
- projects are default client-side routed, so a matching project rule forces Symfony to handle that argument even though a project is active on the request

That gives two explicit patterns:

- set a domain routing rule with argument `foo` when `example.com/foo/...` should enter the domain bundle catch-all instead of normal Symfony-side domain routing
- set a project routing rule with argument `foo` when `project1.example.com/foo/...` should bypass the project catch-all and be handled by Symfony routes/controllers

## Module System

The module system is database-backed and route-aware.

- `Module.id` is the canonical, immutable module identifier.
- Modules can be attached to a `Project` or a `Domain`.
- Backend module endpoints live under `/m/{id}`.
- Frontend module entrypoints build under `modules/{id}`.
- `symfonicat:schema:update` synchronizes module rows from `assets/modules/*/package.json` and confirms any reference cleanup before deleting modules removed from disk.

The runtime guard is in [AbstractModuleController.php](src/Symfonicat/Controller/AbstractModuleController.php). A module controller only runs when the current request context actually has that module attached.

That gives you a clean rule:

- attach a module to a project when it should run only inside that project
- attach a module to a domain when it should run at the domain level without a project

The current concrete server example is [AnalyticsController.php](src/Symfonicat/Controller/Module/AnalyticsController.php), which exposes `POST /m/analytics`.

## Client-Side Routing and Assets

Symfonicat keeps frontend routing simple: the server resolves the page shell, then project/module entrypoints attach behavior.

The project shell in [project/main.html.twig](templates/project/main.html.twig) loads:

- a project entrypoint named `projects/{project.id}`
- zero or more module entrypoints named `modules/{module.id}`

Shell templates support file overrides:

- project rendering checks `templates/project/overrides/{project.id}.html.twig` first, then falls back to `templates/project/main.html.twig`
- domain rendering checks `templates/domain/overrides/{domain.id}.html.twig` first, then falls back to `templates/domain/main.html.twig`
- domains and projects can also set `routeOverride=true` with a `routeName` to render a Symfony route before shell-template rendering runs
- public shell templates stay under `templates/domain` and `templates/project`, while admin CRUD templates live under `templates/admin/{domain,project,env,routing_rule}`

The shared webpack helper [webpack.symfonicat.js](webpack.symfonicat.js) discovers entries from:

- `symfonicat:data:webpack`
- or, if that command is unavailable during build time, the filesystem under `assets/domains`, `assets/projects`, and `assets/modules`

Frontend bootstrap is split into a few small pieces:

- [symfonicat.js](assets/symfonicat.js) is the public app entry
- [stimulus.js](assets/stimulus.js) starts Stimulus through `@symfony/stimulus-bridge`
- [controllers.json](assets/controllers.json) is the public Symfony UX controller registry used by the Stimulus bridge
- [symfonicat_admin.js](assets/symfonicat_admin.js) is the admin app entry and does not import the public entrypoint
- [stimulus_admin.js](assets/stimulus_admin.js) starts a separate plain Stimulus application for `/admin`
- [controllers_admin.json](assets/controllers_admin.json) controls which manually-registered admin UX controllers are available
- [no_prefetch.js](assets/no_prefetch.js) is shared by the public and admin entrypoints without forcing the admin bundle to boot the public runtime
- Turbo is started through the `symfony--ux-turbo--turbo-core` controller mounted on the `<body>` in the base layouts
- Mercure Turbo stream listening comes from Symfony UX Turbo helpers and controllers in the base layouts; there is no custom `templates/turbo` override layer in this repo

Browser-side module requests are intentionally simple and live in [module.js](assets/module.js). The conventions are:

- all module requests are `POST`
- all module requests begin with `/m/{moduleId}`
- if a path is provided, the final URL becomes `/m/{moduleId}/{path}`
- `.log(...)` behaves like `console.log(...)`, but prefixes the output with a styled module label such as `[module][analytics]:`
- `.json(...)` expects a JSON response body
- `.html(...)` expects a raw HTML response body

Examples:

```js
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

Those helpers are available through the public [symfonicat.js](assets/symfonicat.js) bootstrap. Module entrypoints do not need to import `./module` themselves.

## Admin Area

The admin runtime is separate from any public app user system.

- admin auth requires both HTTP basic credentials and a TOTP MFA code
- admin credentials live in the separate `symfonicat_admin` table
- the admin frontend uses the `symfonicat_admin` asset entrypoint, `assets/stimulus_admin.js`, `assets/controllers_admin.json`, and `assets/controllers_admin/` for admin-only JavaScript and Stimulus behavior
- the admin Stimulus app is independent from the public one; `/admin` does not boot `assets/stimulus.js` or use the public bridge registry from `assets/controllers.json`
- admin CRUD Twig templates live under `templates/admin/...`; module rows are synchronized from the filesystem and do not have dedicated admin CRUD templates or routes
- the shared base layouts run the rendered `body` block through a Tidy-backed `indent_body` Twig filter so emitted HTML source stays readable; the first emitted line stays flush with the Twig print site and later lines are padded by the layout indent
- `symfonicat:admin:create <email> <password>` creates or updates an admin and prints a terminal QR code for MFA enrollment
- `/admin/login` is the MFA checkpoint; the request only reaches the admin runtime after the current HTTP basic credentials and current MFA code both pass

MFA flow:

1. Run `docker compose exec php bin/console symfonicat:admin:create <email> <password>`.
2. Scan the QR code shown in the terminal with a TOTP authenticator app.
3. Open any `/admin/*` URL and complete the HTTP basic prompt with the same email and password.
4. After HTTP basic succeeds, enter the current TOTP code on `/admin/login`.
5. Only after MFA succeeds does the full admin navigation and admin runtime become available.

Notes:

- MFA verification is tied to the current HTTP basic credentials through the session-backed admin gate.
- Logging out clears the admin MFA state and the browser's cached basic-auth credentials.

## Env

Env values are split into two layers:

- `Env` defines the key itself in `symfonicat_env`, keyed by the string `id`
- `Domain` can hold env values through its `env` collection in `symfonicat_domain_env`
- `Project` can hold env values through its `env` collection in `symfonicat_project_env`

Admin management:

- `Env` CRUD lives under `/admin/e/*`
- the admin nav exposes this as `env`
- Domain and Project forms both expose an `env` multifield collection
- each env row contains:
  - an `Env` dropdown for the key
  - a text field for the value

Runtime lookup:

- `EnvService` is the canonical runtime accessor
- `EnvService::get(string $id, Domain|Project|null $entity = null)` resolves env values
- when no entity is provided, the service defaults to the current domain, and then to the current project when one is present
- project env values override domain env values when both exist for the same key
- the Twig function `env('key')` resolves through `EnvService`

Local development bootstrap:

- `symfonicat:bootstrap` seeds the `Env` row `color`
- `symfonicat:bootstrap` also seeds:
  - `localhost` with `color=blue`
  - `example.com` with `color=blue`
  - `Project 1` / `project1` with `color=green`

## Redis

The Docker stack provides Redis at the standard `REDIS_URL` environment variable:

```dotenv
REDIS_URL=redis://redis:6379
MESSENGER_TRANSPORT_DSN=redis://redis:6379/symfonicat_messages
MESSENGER_FAILED_TRANSPORT_DSN=redis://redis:6379/symfonicat_failed_messages
MESSENGER_CONSUMER_NAME=symfonicat
MESSENGER_WORKERS=2
```

Symfony uses Redis for shared runtime infrastructure:

- `cache.app` uses `cache.adapter.redis_tag_aware`
- `cache.system` stays on Symfony's default local adapter for container and warmup data
- sessions use `RedisSessionHandler` with a `symfonicat_session:` key prefix
- `cache.symfonicat` is available as a one-hour application cache pool for Symfonicat runtime data
- Messenger uses Redis streams for the `async` and `failed` transports
- `Symfony\Component\Mercure\Update` messages dispatched through Messenger are routed to `async`
- Docker Compose runs `MESSENGER_WORKERS` dedicated `messenger-worker` containers, defaulting to `2`

The main `php` container owns `symfonicat:bootstrap`. Worker containers set `SYMFONICAT_AUTO_BOOTSTRAP=0` and export their container hostname as `MESSENGER_CONSUMER_NAME`, so Redis stream consumers stay distinct without racing bootstrap.

Start or refresh the worker pool by bringing up the stack after changing `MESSENGER_WORKERS`:

```bash
docker compose up -d
docker compose ps messenger-worker
docker compose exec php bin/console messenger:stats
docker compose exec php bin/console messenger:failed:show
```

## Key console commands

Scaffolding:

- `bin/console make:module <Name> <id>` creates a module controller, module service, `assets/modules/{id}/index.js`, and `assets/modules/{id}/package.json`

Admin management:

- `symfonicat:admin:create` creates or updates an admin account and prints a terminal QR code for TOTP enrollment
- `symfonicat:admin:delete` deletes an admin account by email
- `symfonicat:bootstrap` waits for PostgreSQL, synchronizes the schema, and seeds the local development domains/projects
- `symfonicat:schema:update` synchronizes `Module` rows from `assets/modules/*/package.json` and prompts before deleting referenced modules that disappeared from disk

Runtime data export:

- `symfonicat:data:webpack` outputs domain, project, and module data for Encore entry discovery
- `symfonicat:data:electron` outputs project metadata for the Electron layer
- `symfonicat:data:dns` outputs project/domain data for DNS-related workflows

Electron support:

- `symfonicat:electron:prepare` prepares Electron project directories
- `symfonicat:electron:dev` prepares Electron directories for local development
- `symfonicat:electron:build` runs the Electron build flow
- `symfonicat:electron:package` packages Electron bundles

Ops and diagnostics:

- `symfonicat:public-suffix:refresh` downloads `public_suffix_list.dat`
- `symfonicat:test:mercure` publishes a Turbo Stream test event
- `symfonicat:test:redis` checks Redis connectivity

## Infrastructure

This repo ships app-level infrastructure files directly:

- [compose.yaml](compose.yaml) for FrankenPHP, Mercure, PostgreSQL, Messenger Workers, and Redis
- [Caddyfile](Caddyfile) for the FrankenPHP/Caddy setup
- [electron.js](electron.js) plus the [electron](electron) directory for desktop packaging
