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
docker exec -it php bin/console symfonicat:admin:create <email>
```

The `php` container installs Composer dependencies, synchronizes the Doctrine schema, syncs package-provided rows, seeds local defaults, runs `npm install`, and builds assets.

## Configuration

Package discovery is configured in `config/packages/symfonicat.yaml`:

```yaml
symfonicat:
    vendors:
        - symfonicat
```

The configured vendor list drives package service imports, package controller routes, schema sync, webpack fallback discovery, and package-owned asset discovery. The root package is emitted as the special `core` vendor; installed packages under configured vendors use ids such as `symfonicat/analytics/main`.

`symfonicat:dump` writes all `symfonicat_*` database tables (excluding `symfonicat_admin`) to the same file under `symfonicat.admin` while preserving `symfonicat.vendors`. `symfonicat:load` restores that `symfonicat.admin` section into the database (it likewise ignores `symfonicat_admin`). If the YAML file has no `admin` section, load exits successfully without touching the database.

```bash
docker exec php bin/console symfonicat:dump
docker exec php bin/console symfonicat:load
```

Composer runs `symfonicat:schema:update` and then `symfonicat:load` after `composer install`, so a fresh database gets its tables, package-provided rows, and checked-in admin YAML automatically.

## Source Layout

The root Symfony kernel remains in `src/Kernel.php`. Symfonicat-owned runtime, admin, entity, form, command, Twig, routing, and bundle classes live under `admin/src` with the `Symfonicat\` namespace. Composer autoloads `Symfonicat\` from `admin/src`, Symfony service discovery scans `admin/src`, and Doctrine maps Symfonicat entities from `admin/src/Entity`.

## Ids

`Domain`, `Project`, `Application`, `Module`, and `Electron` store ids with a vendor prefix and expose the clean id by default:

```twig
{{ project.id }}       {# project1 #}
{{ project.id(true) }} {# core/project1 #}
```

The separate `vendor` field is read-only in admin forms. Manually created rows use `core`; package-discovered rows use their Composer vendor. Use clean ids in public URLs and templates. Use full ids for admin route parameters and persistence lookups.

## Public Runtime

Runtime resolution is layered:

1. `DomainService` resolves the base host.
2. `ProjectService` resolves the first subdomain when present.
3. `RoutingRuleSubscriber` applies redirect, route, domain, project, and application rules.
4. `ApplicationService` loads application shells from argument rules or route-bound rules.

Public routes:

- `/` renders the domain shell.
- `/{path}` renders the project shell when a project subdomain is active.
- `/application/{id}/{path}` is the internal application entry route.

Env resolution is application, then domain, then project, then Electron for Electron requests only. The same grouped structure is emitted into `window.env`. Twig uses the `env()` helper for dotted lookups:

```twig
{{ env('colors.primary') }}
```

`path_application()` generates URLs for application routing rules:

```twig
{# for the test application #}
{# which has a catch-all routing rule pointing it to /symfonicat/*/test* #}

{# /symfonicat/*/test #}
{{ path_application(application) }}

{# /symfonicat/PARAM/test #}
{{ path_application(application, ['PARAM']) }}

{# /symfonicat/*/test/somepath/testpath #}
{{ path_application(application, 'somepath/testpath') }}

{# /symfonicat/PARAM/test/somepath/testpath #}
{{ path_application('core/test', 'somepath/testpath', ['PARAM']) }}
```

## Routing Rules

Supported rule types:

- `domain`: render the domain shell for a matching regex path.
- `project`: suppress the project catch-all for a matching regex path.
- `application`: render an application shell from regex arguments or a named Symfony route.
- `redirect`: redirect a domain or project to another domain, project, or `project.domain` pair.
- `route`: render a named Symfony route for the root of a domain or project.

## Assets

Webpack entry discovery is driven by `symfonicat:data:webpack`. It scans the root package plus installed Composer packages from configured vendors and resolves:

- `assets/applications/{id}`
- `assets/domains/{id}`
- `assets/projects/{id}`
- `assets/modules/{id}`

Public assets use `assets/**`, and admin code lives under `admin/**/` .

Bootstrap theme tokens are set in `assets/scss/_variables.scss` and `assets/scss/_variables-dark.scss`; generated selector overrides live in `assets/scss/_overrides.scss`.

## Modules

Backend module controllers live in installed packages and are exposed under full package routes such as `/m/symfonicat/analytics/main`. Frontend module code should use the same full qualifier:

```javascript
const mod = 'symfonicat/analytics/main'

mod.log('module active!')

// posts { test: true } to /m/symfonicat/analytics/main
const result = await mod.json({ test: true })
mod.log('/m/symfonicat/analytics/main result:', result)
```

Controllers should extend `Symfonicat\Controller\AbstractModuleController`, which only runs a module when it is attached to the active project, domain, or application context.

## Admin

Admin is isolated from host users and uses Symfonicat-owned `Admin` rows with HTTP basic plus TOTP MFA.

Admin surfaces:

- `/admin/a*` applications
- `/admin/d*` domains
- `/admin/e*` Electron rows
- `/admin/env*` env parents and env keys
- `/admin/p*` projects
- `/admin/r*` routing rules
- `/admin/y/dump` dumps `symfonicat_*` tables to YAML
- `/admin/y/load` loads `symfonicat.admin` YAML into the database

The admin header includes a `yaml` dropdown with dump and load actions. Both actions redirect back to admin and flash a success message.

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
