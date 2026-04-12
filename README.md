# Symfonicat

`symfonicat/core` is the full Symfonicat Symfony application: admin, public runtime, webpack, Electron, and the FrankenPHP-oriented starter shell all live here


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
- seeds and enables the `analytics` module for `localhost`, `example.com`, and `Project 1`
- seeds `color=blue` for `localhost` and `example.com`
- seeds `color=green` for `Project 1`

After the containers are up, create an admin:

```bash
docker exec php bin/console symfonicat:admin:create <username> <password>
```

## Files To Look At

go to `example.com` and `project1.example.com`, open up DevTools, and then check out these files:

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
- when a `Project` is active, the public runtime uses a catch-all route so the rest of the URL can be client-side routed
- if no `Project` is active, the `Domain` runtime is loaded
- if you are on a `Domain` and there is a path, Symfony handles it
- if you are on a `Domain`, there is a path, and a matching `RoutingRule` exists for the first path segment, Symfonicat can redirect to the correct `Domain` and let Symfony continue from there

## Included

- the public frontend runtime for domains, projects, modules, and routing rules
- the separate `/admin` runtime and admin templates
- the internal `SymfonicatBundle` service/entity/template organization
- shared frontend assets under `assets`
- the webpack helper [webpack.symfonicat.js](webpack.symfonicat.js)
- the Electron desktop shell under [electron](electron)
- drop-in FrankenPHP infrastructure files such as [compose.yaml](compose.yaml) and [Caddyfile](Caddyfile)

## Runtime Model

Symfonicat resolves requests in layers.

1. A request arrives on the base domain or a subdomain.
2. Subscribers resolve the active `Domain`, `Project`, and `RoutingRule`.
3. The public controller decides whether to render a domain shell or a project shell.
4. If there is no resolved project and the domain request has a path, the request continues through the normal Symfony application routes.
5. Encore entrypoints are selected from the current database-backed domain, project, and module state.

The public entry routes live in [MainController.php](src/Symfonicat/Controller/MainController.php):

- `/` renders the domain shell when there is no resolved project.
- `/{path}` renders the project shell when a project is resolved onto the request.
- domain paths without a resolved project are left for the Symfony app route table, such as `/admin`, or other application routes.

Resolution is driven primarily by subdomain and routing-rule context. The key runtime pieces are:

- [ProjectService.php](src/Symfonicat/Service/ProjectService.php)
- [ProjectSubscriber.php](src/Symfonicat/EventSubscriber/ProjectSubscriber.php)
- [RoutingRuleSubscriber.php](src/Symfonicat/EventSubscriber/RoutingRuleSubscriber.php)
- [DomainRedirectSubscriber.php](src/Symfonicat/EventSubscriber/DomainRedirectSubscriber.php)

## Module System

The module system is database-backed and route-aware.

- A `Module` record is identified by slug.
- Modules can be attached to a `Project` or a `Domain`.
- Backend module endpoints live under `/m/{slug}`.
- Frontend module entrypoints build under `modules/{slug}`.

The runtime guard is in [AbstractModuleController.php](src/Symfonicat/Controller/AbstractModuleController.php). A module controller only runs when the current request context actually has that module attached.

That gives you a clean rule:

- attach a module to a project when it should run only inside that project
- attach a module to a domain when it should run at the domain level without a project

The current concrete server example is [AnalyticsController.php](src/Symfonicat/Controller/Module/AnalyticsController.php), which exposes `POST /m/analytics`.

## Client-Side Routing and Assets

Symfonicat keeps frontend routing simple: the server resolves the page shell, then project/module entrypoints attach behavior.

The project shell in [project/main.html.twig](templates/project/main.html.twig) loads:

- a project entrypoint named `projects/{project.slug}`
- zero or more module entrypoints named `modules/{module.slug}`

The shared webpack helper [webpack.symfonicat.js](webpack.symfonicat.js) discovers entries from:

- `symfonicat:data:webpack`
- or, if that command is unavailable during build time, the filesystem under `assets/domains`, `assets/projects`, and `assets/modules`

Frontend bootstrap is split into a few small pieces:

- [app.js](assets/app.js) is the shared app entry
- [stimulus.js](assets/stimulus.js) starts Stimulus through `@symfony/stimulus-bridge`
- [controllers.json](assets/controllers.json) enables Symfony UX controllers
- Turbo is started through the `symfony--ux-turbo--turbo-core` controller mounted on the `<body>` in the base layouts

Browser-side module requests are intentionally simple and live in [module.js](assets/module.js). The conventions are:

- all module requests are `POST`
- all module requests begin with `/m/{module}`
- if a path is provided, the final URL becomes `/m/{module}/{path}`
- `.json(...)` expects a JSON response body
- `.html(...)` expects a raw HTML response body

Examples:

```js
const parsedJson = await 'analytics'.json({ working: true });
const parsedJsonWithPath = await 'analytics'.json('path/secondpath', { working: true });

const parsedHtml = await 'frame'.html({ working: true });
const parsedHtmlWithPath = await 'frame'.html('path/secondpath', { working: true });
```

Those helpers are available through the shared [app.js](assets/app.js) bootstrap. Module entrypoints do not need to import `./module` themselves.

## Admin Area

The admin runtime is separate from any public app user system.

- admin auth requires both HTTP basic credentials and a TOTP MFA code
- admin credentials live in the separate `symfonicat_admin` table
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

- `bin/console make:module <Name> <slug>` creates a module controller, module service, and `assets/modules/{slug}/index.js` entrypoint

Admin management:

- `symfonicat:admin:create` creates or updates an admin account and prints a terminal QR code for TOTP enrollment
- `symfonicat:admin:delete` deletes an admin account by email
- `symfonicat:bootstrap` waits for PostgreSQL, synchronizes the schema, and seeds the local development domains/projects

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
