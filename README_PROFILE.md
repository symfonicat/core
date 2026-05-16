# Symfonicat

`symfonicat/core` is the full Symfonicat Symfony application: public routing, admin CRUD, package module runtime, Electron packaging, webpack wiring, and Docker/FrankenPHP live in this repository.

First boot can take several minutes.

The `php` container:

- installs Composer dependencies
- runs `npm install`
- builds assets

Redis is used for application cache, sessions, locks, admin login throttling, and Symfony Messenger; Messenger routes messages to the Redis-backed `async` transport by default and Compose starts workers for them.

## Runtime

`DomainService`, `ProjectService`, routing rules, and `ApplicationService` resolve the active domain, subdomain, and application shell. Public routes are `/`, `/{path}`, and the internal `/application/{vendor}/{id}/{path}` application entry route.

Routing rules can render domain and subdomain shells, redirect hosts, hand a root request to a named Symfony route, or render application shells. Application rules can match regex arguments, bind an application to a bare domain, bind one to a subdomain affix, bind one to a specific domain/subdomain pair, or attach application context to a Symfony route without replacing that route's response.

The `symfonicat_asset(path)` Twig helper resolves shell-specific public files. Without a second argument, it checks the current subdomain folder first, then the current domain folder, then `public/default/`. Passing an `Application`, `Project`, `Domain`, or `Electron` object pins the asset base directly to that object; Electron assets resolve under `public/electron/{electron.id}/`.
The public JavaScript entry is `assets/app.js`; its runtime helpers live under `assets/app/`.
`path_application()` is simple:

- one argument can be the extra path
- one argument can be the wildcard replacement array
- wildcard replacements are applied in array order

`Project`, `Application`, and `Module` ids are package-scoped. `Domain` ids are bare hostnames, and `Electron` ids are plain row ids:

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

Webpack and schema sync discover package entries under configured Composer vendors. Core bundle entries live under `assets/bundles/{domain,subdomain,module}/`; installed package bundle entries live under `{composer-package-dir}/bundles/{domain,subdomain,module}/`. The root package is emitted as `core/...`; installed package entries use ids such as `symfonicat/analytics/main`. The same vendor list is used for package service imports and package controller route imports.

## Admin YAML

Runtime reads `config/packages/symfonicat.yaml` under `symfonicat.admin`. The database tables are for unlocked admin editing and dumping YAML; production runtime should not need those tables. For local admin work:

```bash
docker exec -it php bin/console symfonicat:schema:update
docker exec php bin/console symfonicat:dump
docker exec php bin/console symfonicat:load
docker exec php bin/console symfonicat:purge
```

`symfonicat:dump` writes Symfonicat admin rows to YAML (excluding `symfonicat_admin`) and preserves `symfonicat.vendors`. Composer and Docker startup do not run schema update or YAML load automatically. Without a `symfonicat.admin` section, load exits without changing the database. The admin header has a Bootstrap-backed `yaml` dropdown linking to `/admin/y/dump` and `/admin/y/load`.

## Admin

Create an admin with:

```bash
docker exec -it php bin/console symfonicat:admin:create <username>
touch symfonicat.lock # enables /admin
```

and then visit `/admin`. Every path beginning with `/admin` returns a Symfony-rendered 404 until the root `symfonicat.lock` file exists; Caddy catches those requests before public static files can be served, marks them, and routes them into Symfony. Symfony keeps the same guard for non-Caddy runtimes. Remove the ignored lock file to close the admin area again.

The admin header includes the YAML tools and `/admin/f`, which uploads named files into `public/domains/{domain-id}/` or `public/subdomains/{subdomain-id}/` for domain and subdomain asset scopes. Project and application lookups by clean id are strict; if multiple rows share the same clean id, runtime resolution throws, the matching admin list flashes a duplicate-id warning, and `symfonicat:schema:update` fails fast before syncing those rows.

## Modules

Module controllers are package-owned and run only when the active domain, subdomain, or application has the module attached. Runtime module requests use full-qualified URLs such as `/m/symfonicat/analytics/main`, matching frontend module code like this:

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

Electron rows use plain ids. Generated start URLs include `?electron={electron.id}` so the `electron` Twig variable resolves to the active Electron entity row.

## Sync

`symfonicat:schema:update` synchronizes the Doctrine schema and then synchronizes package-provided modules, domains, applications, and subdomains. Non-interactive runs create missing package rows automatically; stale modules with referencing rows still require an interactive confirmation before deletion.

```bash
docker exec -it php bin/console symfonicat:schema:update
```

## Picture of @dunglas at the Zoo

This repository includes an AI-generated picture of Kévin Dunglas at the zoo:

[dunglas_at_zoo.png](https://github.com/symfonicat/core/blob/main/dunglas_at_zoo.png)


For full install, routing, env, Electron, and command details, see [symfonicat/core](https://github.com/symfonicat/core).
