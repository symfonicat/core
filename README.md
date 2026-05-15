# Symfonicat

`symfonicat/core` is the full Symfony application for Symfonicat. Public routing, admin CRUD, Doctrine entities, package module runtime, webpack entry discovery, Electron packaging, and Docker/FrankenPHP infrastructure live in this repository.

## Install

For local development, point the seeded hosts at your Docker host:

```text
127.0.0.1 example.com
127.0.0.1 project1.example.com
```

```bash
git clone https://github.com/symfonicat/core symfonicat
cd symfonicat
docker compose up -d
docker exec -it php bin/console symfonicat:admin:create <email> # prints QR code
touch symfonicat.lock # enables /admin
```

First boot can take several minutes.

The `php` container:

- installs Composer dependencies
- runs `symfonicat:schema:update`
- loads checked-in admin YAML
- runs `npm install`
- builds assets

Redis is used for application cache, sessions, locks, admin login throttling, and Symfony Messenger; Messenger routes messages to the Redis-backed `async` transport by default and Compose starts workers for them.

## Configuration

Package discovery is configured in `config/packages/symfonicat.yaml`. The vendors list determines what composer package vendors are searched for Symfonicat modules:

```yaml
symfonicat:
    vendors:
        - symfonicat
```

`symfonicat:dump` writes all `symfonicat_*` database tables (excluding `symfonicat_admin`) to the same file under `symfonicat.admin` while preserving `symfonicat.vendors`. `symfonicat:load` restores that `symfonicat.admin` section into the database (it likewise ignores `symfonicat_admin`). If the YAML file has no `admin` section, load exits successfully without touching the database.

```bash
docker exec php bin/console symfonicat:dump
docker exec php bin/console symfonicat:load
```

Composer runs `symfonicat:schema:update` and then `symfonicat:load` after `composer install`, so a fresh database gets its tables, package-provided rows, and checked-in admin YAML automatically.

## Ids

`Domain`, `Project`, `Application`, `Module`, and `Electron` store ids with a vendor prefix and expose the full id by default:

```twig
{{ project.id(false) }} {# project1 #}
{{ project.id }}        {# core/project1 #}
```

The separate `vendor` field is read-only in admin forms. Manually created rows use `core`; package-discovered rows use their Composer vendor. Admin lists show clean ids where users read host and project names, but admin route parameters and persistence lookups keep the full id.

## Public Runtime

Runtime resolution is layered:

1. `DomainService` resolves the base host.
2. `ProjectService` resolves the first subdomain when present.
3. `RoutingRuleSubscriber` applies redirect, route, domain, project, and application rules.
4. `ApplicationService` loads application shells from argument rules, route-bound rules, or domain/project application bindings.

Public routes:

- `/` renders the domain shell.
- `/{path}` renders the project shell when a project subdomain is active.
- `/application/{vendor}/{id}/{path}` is the internal application entry route and uses the full vendor-prefixed application id in the URL.

## Assets

### Private

Webpack entry discovery is driven by `symfonicat:data:webpack`. It scans the root package plus installed Composer packages from configured vendors and resolves:

- `assets/applications/{id}`
- `assets/domains/{id}`
- `assets/projects/{id}`
- `assets/modules/{id}`

Bootstrap theme tokens are set in `assets/scss/_variables.scss` and `assets/scss/_variables-dark.scss`; generated selector overrides live in `assets/scss/_overrides.scss`.
The public runtime entry is `assets/app.js`; its internal helpers live under `assets/app/` (`module`, `application_redirect`, `stimulus`, and `mercure`).
Admin pages use the `admin` JavaScript entry from `admin/assets/admin.js`, which loads Bootstrap JavaScript for controls such as the YAML dropdown while keeping admin controllers out of the public runtime entry.

### Public

The `symfonicat_asset(path)` Twig helper resolves shell-specific public assets. Without a second argument, it checks the current project folder first, then the current domain folder, then `public/default/`. A folder only wins if the requested file exists there. If the file is missing from `public/default/`, the helper throws. Passing an `Application`, `Project`, or `Domain` object as the second argument pins the base directly to that object, for example `/core/test/` for an application. The repository ships skeleton `public/default/`, `public/domains/example.com/`, `public/projects/project1/`, and `public/applications/core/test` folders.

Notice how the favicons work on each url:

- `example.com`: purple favicon, `public/domains/example.com/favicon.svg`
- `project1.example.com`: green favicon, `public/projects/project1/favicon.svg`
- `example.com/admin`: blue favicon, `public/default/favicon.svg`

But notice that in `admin/templates/base.html.twig` and `templates/base.html.twig` the only `symfonicat_asset()` call is this:

```twig
<link rel="icon" href="{{ symfonicat_asset('favicon.svg') }}" />
```

but it can be used like this:

```twig
<link rel="icon" href="{{ symfonicat_asset('favicon.svg', application) }}" />
```

## Env

Env resolution is application, then domain, then project, then Electron for Electron requests only. The same grouped structure is emitted into `window.env`. Twig uses the `env()` helper for dotted lookups:

```twig
{{ env('colors.primary') }}
```

## Paths

`path_application()` generates URLs for application routing rules:

```twig
{# for the test application #}
{# which has a catch-all routing rule pointing it to /symfonicat/*/test* #}

{# /symfonicat/*/test #}
{{ path_application(application) }}

{# /symfonicat/tay/test #}
{{ path_application(application, ['tay']) }}

{# /symfonicat/*/test/somepath/testpath #}
{{ path_application(application, 'somepath/testpath') }}

{# /symfonicat/tay/test/somepath #}
{{ path_application('core/test', 'somepath', ['tay']) }}
```

The helper is simple:

- one argument can be the extra path, like `somepath/testpath`
- one argument can be the wildcard replacement array
- wildcard replacements are applied in array order

For domain-bound and project-bound application rules, `path_application()` returns the bound path on the current host. Use the matching domain or project host when linking across hosts.

## Routing Rules

Supported rule types:

- `domain`: render the domain shell for a matching regex path.
- `project`: suppress the project catch-all for a matching regex path.
- `application`: render an application shell from regex arguments, bind an application to a domain, project, or domain/project pair, or attach application context to a named Symfony route.
- `redirect`: redirect a domain or project to another domain, project, or `project.domain` pair.
- `route`: render a named Symfony route for the root of a domain or project.

Application rules support these application types:

- `arguments`: match regex path segments and render the application shell.
- `route`: attach application context to a named Symfony route without replacing that route's response.
- `domain`: render the application shell for the bare matching domain.
- `project`: render the application shell for the matching project subdomain.
- `domain_project`: render the application shell for the matching project on the matching domain.

Root-level `route` rules are evaluated before domain/project application bindings, so a domain or project can still hand its root request to a Symfony-only route.

## Modules

Backend module controllers live in installed packages and are exposed under full package routes such as `/m/symfonicat/analytics/main`. Frontend module code should use the same full qualifier:

```javascript
const mod = 'symfonicat/analytics/main'

mod.log('module active!')

// posts { test: true } to /m/symfonicat/analytics/main
const result = await mod.json({ test: true })
mod.log('/m/symfonicat/analytics/main result:', result)
```

Module controllers should extend `Symfonicat\Controller\AbstractModuleController`, which only runs a module when it is attached to the active project, domain, or application context.

## Admin

The magic is in the `/admin` section. Every path beginning with `/admin` is hard-disabled unless `<repo>/symfonicat.lock` exists. When the lock file is missing, Caddy catches the request before public static files can be served, marks it, and routes it into Symfony so Symfony renders the 404. Symfony has the same early request gate for non-Caddy runtimes. Create the ignored lock file with `touch symfonicat.lock` to open the admin area, and remove it to close the admin area again.

## Electron

There are `electron` and `electron_favicon` Twig variables available in any template if the request is coming from an electron app:

```twig
{% if electron %}

    {# output Electron-specific code #}

{% endif %}

{% if electron_icon %}

    <img src="{{ electron_icon }}" />

{% endif %}
```

Electron rows have vendor-scoped ids, a `type` (`domain`, `project`, or `application`), a matching target relation, an optional favicon, and scoped env values.
The checked-in example Electron row uses `public/electron/favicon/domain/example.com.svg`, matching the default SVG favicon.

Build outputs with:

```bash
docker exec php bin/console symfonicat:electron:build
docker exec php bin/console symfonicat:electron:build <name>
```

The build command renders `templates/electron/{type}/main.twig.js` or `templates/electron/{type}/overrides/{targetId}.twig.js`, writes `electron/{type}/{targetId}/app.js`, writes a local `package.json`, and runs `electron-builder` into `electron/{type}/{targetId}/build`.

## Sync

`symfonicat:schema:update` first synchronizes the Doctrine schema and then synchronizes modules, domains, applications, and projects from package assets:

```bash
docker exec -it php bin/console symfonicat:schema:update
```

In non-interactive runs, such as Composer install scripts, missing package-provided rows are created automatically. Removing a stale module that still has referencing rows requires an interactive run so the affected rows can be reviewed before deletion.

## Picture of @dunglas at the Zoo

This repository includes an AI-generated picture of Kévin Dunglas at the zoo:

[dunglas_at_zoo.png](https://github.com/symfonicat/core/blob/main/dunglas_at_zoo.png)
