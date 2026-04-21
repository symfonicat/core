# Symfonicat

`symfonicat/core` is the full Symfonicat Symfony application: admin, public runtime, webpack, Electron, and the FrankenPHP-oriented starter shell all live here

Canonical repository README: <https://github.com/symfonicat/core/blob/main/README.md>


## Install

Before the stack can serve the seeded domains and projects, add these entries to your local `/etc/hosts` so `example.com` and `project1.example.com` resolve to the Docker host:

```text
127.0.0.1 example.com
127.0.0.1 project1.example.com
```

You can stand up Symfonicat two ways. Both end at the same running stack.

### From a git clone (no PHP or Composer on the host required)

```bash
git clone https://github.com/symfonicat/core symfonicat
cd symfonicat
docker compose up -d
npm install
npm run dev
```

On the first boot the `php` container will notice `vendor/` is missing and run `composer install` inside the container before it hands off to FrankenPHP. Set `SYMFONICAT_AUTO_COMPOSER_INSTALL=0` on the `php` service to opt out (e.g. production images that bake vendor/ in at build time).

### From `composer create-project` (PHP 8.4 + Composer on the host)

```bash
composer create-project symfonicat/core symfonicat
cd symfonicat
docker compose up -d
npm install
npm run dev
```

You don't need to run `doctrine:schema:create` to get the UI up locally. On first container boot, the `php` service bootstraps the local stack:

- synchronizes the Doctrine schema
- seeds a `localhost` domain row
- seeds an `example.com` domain row
- seeds a `project1` project named `Project 1`
- attaches `Project 1` to `example.com`
- seeds `color=blue` for `localhost` and `example.com`
- seeds `color=green` for `Project 1`

After the containers are up, create an admin and update the module schema:

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
- `symfonicat:schema:update` treats `assets/modules/{id}` as the source of truth for `Module` rows, reads each module name from `assets/modules/{id}/package.json`, and prompts before removing referenced modules that no longer exist on disk
- projects are default client-side routed: when a `Project` is active, the public runtime uses a catch-all route so the rest of the URL can be client-side routed
- domains are default Symfony-side routed: when there is no active `Project`, public paths stay in the normal Symfony route table unless explicitly inverted
- `RoutingRule.argument` only applies to the legacy `domain` and `project` rule types
- legacy `domain` and `project` rules require a non-empty argument through entity validation; redirect and route rules omit it and normalize it to an empty string
- legacy `domain` rules still force the matching argument into the domain shell
- legacy `project` rules still bypass the project catch-all so Symfony handles the request
- redirect rules apply to an entire `Domain` or `Project` and redirect to another `Domain` or `Project`
- route rules apply to the root of a `Domain` or `Project` and render a named Symfony route
- the routing rule form groups rule, match, redirect, and route fields into separate cards, keeps `type`, `redirectType`, and `routeType` together in the rule card, hides `argument` for redirect and route rules, hides unused match-field columns cleanly so the full-width project selector does not leave an empty slot above it, and keeps redirect target on the left with the selected redirect domain or project field on the right

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
2. Subscribers resolve the active `Domain`, current `Project`, and, when needed, the first path segment argument.
3. Domain requests are default Symfony-side routed and project requests are default client-side routed through the project catch-all.
4. `RoutingRule` can redirect a whole domain/project, render a root route for a domain/project, or apply the legacy path-segment inversions.
5. The public controller decides whether to render a domain shell or a project shell when the request is still on the shell path.
6. Encore entrypoints are selected from the current database-backed domain, project, and module state.

`MainController` resolves the active domain and project once per request and reuses them for shell rendering.

Top-level public controllers are imported separately from the public shell route. They are guarded so project subdomains keep the project catch-all unless a legacy project routing rule disables it for the current first path segment. Admin and module controllers use their own route imports. The public shell entry routes live in [MainController.php](src/Symfonicat/Controller/MainController.php):

- `/` renders the domain shell when there is no resolved project.
- `/{path}` renders the project shell when a project is resolved onto the request.
- domain paths without a resolved project are left for the Symfony app route table.
- project paths with a resolved project use the project catch-all unless a legacy project rule disables it or a root route rule renders a Symfony route.

Resolution is driven primarily by subdomain and routing-rule context. The key runtime pieces are:

- [ProjectService.php](src/Symfonicat/Service/ProjectService.php)
- [ProjectSubscriber.php](src/Symfonicat/EventSubscriber/ProjectSubscriber.php)
- [RoutingRuleSubscriber.php](src/Symfonicat/EventSubscriber/RoutingRuleSubscriber.php)

## Routing Rules

`RoutingRule` records are first-path-segment rules stored on the `argument` field.

- `TYPE_DOMAIN` preserves the legacy domain-shell inversion for a matching first path segment
- `TYPE_PROJECT` preserves the legacy project catch-all bypass for a matching first path segment
- `TYPE_REDIRECT` applies a redirect for the current domain or project regardless of path
- `TYPE_ROUTE` renders a Symfony route for the current domain or project root
- only the legacy `domain` and `project` rule types use the `argument` field
- `REDIRECT_TYPE_DOMAIN` and `REDIRECT_TYPE_PROJECT` decide whether the redirect rule matches a `Domain` or `Project`
- `TARGET_TYPE_DOMAIN` and `TARGET_TYPE_PROJECT` decide whether the redirect points to a `Domain` or `Project`
- `ROUTE_TYPE_DOMAIN` and `ROUTE_TYPE_PROJECT` decide whether the route rule matches a `Domain` or `Project`
- domain route rules only apply when no project is active and the request is for `/`
- project route rules apply when the current request resolved a project and the request is for `/`
- the routing rule argument `admin` is reserved through entity validation and ignored by runtime matching

That gives six explicit patterns:

- set a legacy domain rule with argument `foo` when `example.com/foo/...` should enter the domain shell instead of normal Symfony-side domain routing
- set a legacy project rule with argument `foo` when `project1.example.com/foo/...` should bypass the project catch-all and be handled by Symfony routes/controllers
- set a domain redirect rule when the current domain should redirect to another domain or project
- set a project redirect rule when the current project should redirect to another domain or project
- set a domain route rule when the root of a domain should render a named Symfony route while no project is active
- set a project route rule when the root of a project should render a named Symfony route instead of the project shell

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

- project rendering checks `project/overrides/{project.id}.html.twig` first, then falls back to `project/main.html.twig`
- domain rendering checks `domain/overrides/{domain.id}.html.twig` first, then falls back to `domain/main.html.twig`
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

- admin auth requires a Symfony session login and a TOTP MFA code
- admin credentials live in the separate `symfonicat_admin` table
- the admin frontend uses the `symfonicat_admin` asset entrypoint, `assets/stimulus_admin.js`, `assets/controllers_admin.json`, and `assets/controllers_admin/` for admin-only JavaScript and Stimulus behavior
- the admin Stimulus app is independent from the public one; `/admin` does not boot `assets/stimulus.js` or use the public bridge registry from `assets/controllers.json`
- admin CRUD Twig templates live under `templates/admin/...`; module rows are synchronized from the filesystem and do not have dedicated admin CRUD templates or routes
- the shared base layouts run the rendered `body` block through a Tidy-backed `indent_body` Twig filter so emitted HTML source stays readable; the first emitted line stays flush with the Twig print site and later lines are padded by the layout indent
- the admin firewall is configured through Symfony security YAML, uses a Redis-backed `AdminUserProvider`, and keeps first-factor login separate from the MFA step
- `symfonicat:admin:create <email> <password>` creates or updates an admin and prints a terminal QR code for MFA enrollment
- `/admin/login` first accepts the admin email/password session login and then serves the MFA checkpoint for the authenticated admin

MFA flow:

1. Run `docker compose exec php bin/console symfonicat:admin:create <email> <password>`.
2. Scan the QR code shown in the terminal with a TOTP authenticator app.
3. Open `/admin/login` and sign in with the same email and password.
4. After the session login succeeds, enter the current TOTP code on `/admin/login`.
5. Only after MFA succeeds does the full admin navigation and admin runtime become available.

Notes:

- MFA verification is tied to the current admin session through the session-backed gate.
- Logging out clears the admin MFA state and then hands the request to the firewall logout path, which invalidates the browser session.
- Admin lookup during the first-factor login is cached in Redis for a short TTL so repeated admin requests do not hit the database on every request.

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
