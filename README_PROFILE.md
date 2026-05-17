# Symfonicat

`symfonicat/core` is the full Symfonicat Symfony application: public routing, admin CRUD, package module runtime, Electron packaging, webpack wiring, and Docker/FrankenPHP live in this repository.

First boot can take several minutes.

The `php` container:

- installs Composer dependencies
- runs `npm install`
- builds assets

Redis is used for application cache, sessions, locks, admin login throttling, and Symfony Messenger.

Messenger routes messages to the Redis-backed `async` transport by default, and Compose starts workers for them.

## Runtime

Runtime services:

- `DomainService`
- `ProjectService`
- `ApplicationService`

They resolve the active domain, subdomain, endpoint, and application shell.

Public routes:

- `/`
- `/{path}`
- `/application/{vendor}/{id}/{path}` for internal application entry

The `symfonicat_asset(path)` Twig helper resolves shell-specific public files.

Without a second argument, it checks:

1. the current subdomain folder
2. the current domain folder
3. `public/default/`

Passing an `Application`, `Project`, `Domain`, or `Electron` object pins the asset base directly to that object.

Electron assets resolve under `public/electron/{electron.id}/`.

The public JavaScript entry is `assets/app.js`; its runtime helpers live under `assets/app/`.

`path_application()` is simple:

- one argument can be the extra path
- one argument can be the wildcard replacement array
- wildcard replacements are applied in array order

Id rules:

- `Project`, `Application`, and `Module` ids are package-scoped
- `Domain` ids are bare hostnames
- `Electron` ids are plain row ids

```twig
{{ domain.id }}        {# example.com #}
{{ electron.id }}      {# example-test #}
{{ subdomain.id(false) }} {# subdomain1 #}
{{ subdomain.id }}        {# core/subdomain1 #}
```

Manual subdomain/application rows use the special `core` vendor. Package rows use their Composer vendor.

## Package Discovery

Configured package vendors live in `config/packages/symfonicat.yaml`:

```yaml
symfonicat:
    vendors:
        - symfonicat
```

Webpack and schema sync discover package entries under configured Composer vendors.

Entry locations:

- core: `assets/{domain,subdomain,module,bundle}/`
- installed packages: `{composer-package-dir}/assets/{domain,subdomain,module,bundle}/`

Id style:

- root package entries are emitted as `core/...`
- installed package entries use ids such as `symfonicat/analytics/main`
- bundle rows use the same vendor-scoped id style and can be attached to domains and subdomains

Env data is layered as bundle, domain, subdomain, then application at runtime.

The same grouped structure is exposed through `window.env` and Twig `env()` lookups.

## Admin YAML

Runtime reads `config/packages/symfonicat.yaml` under `symfonicat.admin`. The database tables are for unlocked admin editing and dumping YAML; production runtime should not need those tables. For local admin work:

```bash
docker exec -it php bin/console symfonicat:schema:update
docker exec php bin/console symfonicat:dump
docker exec php bin/console symfonicat:load
docker exec php bin/console symfonicat:purge
```

`symfonicat:dump` writes Symfonicat admin rows to YAML, excluding `symfonicat_admin`, and preserves `symfonicat.vendors`.

Composer and Docker startup do not run schema update or YAML load automatically.

Without a `symfonicat.admin` section, load exits without changing the database.

The admin header has a Bootstrap-backed `yaml` dropdown linking to `/admin/y/dump` and `/admin/y/load`.

`symfonicat:load` binds boolean columns explicitly when it restores admin rows, so `catch` values load cleanly on PostgreSQL.

Admin YAML dump/load round-trips domains, subdomains, bundles, endpoints, middleware, modules, and their join rows together.
- Middleware sync discovers tagged services and package `src/Middleware` classes during schema update.
- Middleware rows use string ids derived from their package bucket and short class name, and the selector groups them by bucket.
Subdomains keep their bundle ownership when they are dumped and loaded again.
- The checked-in sample YAML includes `example.com` and `core/subdomain1` as starter rows.

Test env pins a router default URI so CLI schema sync and Turbo listeners have context.

## Admin

Create an admin with:

```bash
docker exec -it php bin/console symfonicat:admin:create <username>
touch symfonicat.lock # enables /admin
```

and then visit `/admin`.

Every path beginning with `/admin` returns a Symfony-rendered 404 until the root `symfonicat.lock` file exists.

Caddy catches those requests before public static files can be served, marks them, and routes them into Symfony.
Symfony keeps the same guard for non-Caddy runtimes.

Remove the ignored lock file to close the admin area again.

Admin header and forms:

- bundle management: `/admin/b`
- endpoint management: `/admin/end`
- middleware management: `/admin/m`
- applications: `/admin/a`
- schema sync: `/admin/s`
- YAML tools
- `/admin/f` uploads named files into `public/domains/{domain-id}/` or `public/subdomains/{subdomain-id}/`

Form behavior:

- domain and subdomain edit forms can attach bundle rows, repeatable middleware rows, and a `catch` flag
- parcel selects are grouped by vendor, with the last path segment used as the visible option label
- endpoint rows belong to a bundle, can be marked as catch-all, can carry repeatable middleware rows, modules, and scoped env rows, and use the shared multifield editor for repeatable arguments
- application edit/delete routes resolve their ids explicitly and fall back to the index with a flash when the row is missing
- the delete route is kept separate from the edit matcher so `/admin/a/{id}/delete` is not captured by edit routing
- subdomain delete routes use `/admin/s/{id}/delete` so POST deletes do not collide with edit
- project and application lookups by clean id are strict; if multiple rows share the same clean id, runtime resolution throws, the matching admin list flashes a duplicate-id warning, and schema sync fails fast before syncing those rows
- application rows can target a domain, subdomain, or endpoint
- the endpoint select shows the endpoint id in the admin form and the runtime catalog loads the `endpoint_id` relation for application rows
- bundle edits keep the stored path field visible but disabled, so saving env changes does not clear the path and schema sync remains the source of truth for bundle paths
- domain, subdomain, and endpoint catch checkboxes treat blank submissions as false, which keeps boolean columns from receiving empty strings

## Modules

Module controllers are package-owned and run only when the active domain, subdomain, or application has the module attached.

Runtime module requests use full-qualified URLs such as `/m/symfonicat/analytics/main`, matching frontend module code like this:

```javascript
const mod = 'symfonicat/analytics/main'

mod.log('module active!')

// posts { test: true } to /m/symfonicat/analytics/main
const result = await mod.json({ test: true })
mod.log('/m/symfonicat/analytics/main result:', result)
```

## Electron

Electron rows are managed under `/admin/e` and build with:

```bash
docker exec php bin/console symfonicat:electron:build
docker exec php bin/console symfonicat:electron:build <name>
```

Electron rows use plain ids.

Generated start URLs include `?electron={electron.id}` so the `electron` Twig variable resolves to the active Electron entity row.

## Sync

`symfonicat:schema:update` synchronizes the Doctrine schema and then synchronizes package-provided bundles, middleware, endpoints, modules, domains, applications, and subdomains.

It does the following:

- creates missing package rows in non-interactive runs
- removes stale package-backed bundle rows
- clears any domain/subdomain references to removed bundles
- mirrors tagged middleware services into `symfonicat_middleware`
- deletes stale middleware rows when their class disappears
- stores domain, subdomain, and endpoint middleware attachments in dedicated join tables
- uses the shared env collection pattern for domain, subdomain, bundle, application, and endpoint env rows
- exposes named `EnvService` flatten helpers for bundle, domain, subdomain, endpoint, and application layers in that order
- stores domain and subdomain `catch` flags on their own rows
- keeps endpoints keyed by string ids with bundle ownership, `catch`, repeatable middleware rows, scoped env rows, and repeatable arguments
- still requires interactive confirmation before deleting stale modules that have referencing rows

```bash
docker exec -it php bin/console symfonicat:schema:update
```

## Picture of @dunglas at the Zoo

This repository includes an AI-generated picture of Kévin Dunglas at the zoo:

[dunglas_at_zoo.png](https://github.com/symfonicat/core/blob/main/dunglas_at_zoo.png)


For full install, runtime, env, Electron, and command details, see [symfonicat/core](https://github.com/symfonicat/core).
