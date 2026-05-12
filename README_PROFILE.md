# Symfonicat

`symfonicat/core` is the full Symfonicat Symfony application: public routing, admin CRUD, package module runtime, Electron packaging, webpack wiring, and Docker/FrankenPHP live in this repository.

On first Docker boot, the `php` container installs PHP and Node dependencies, runs `symfonicat:schema:update`, loads checked-in admin YAML, and builds assets before FrankenPHP starts. Its healthcheck includes a startup grace period so the web stack waits for that startup work to finish. Redis backs cache, sessions, locks, login throttling, and Symfony Messenger; Messenger routes messages to the Redis-backed `async` transport by default and Compose starts workers for them.

The Docker image mounts the repository at `/symfonicat`, serves Caddy from `/symfonicat/public`, and includes the PHP extensions the app expects for Symfony 8, Redis, PostgreSQL, image processing, process control, sockets, XML/HTML handling, archives, APCu, OPcache, igbinary, and msgpack. Composer declares those modules as platform requirements. Symfony uses APCu for the system cache, Redis for shared cache/session/lock/limiter state, igbinary for cache and Redis Messenger serialization, and Intervention Image with Imagick for admin image upload conversion.

## Runtime

`DomainService`, `ProjectService`, routing rules, and `ApplicationService` resolve the active domain, project, and application shell. Public routes are `/`, `/{path}`, and the internal `/application/{id}/{path}` application entry route.

Routing rules can render domain and project shells, redirect hosts, hand a root request to a named Symfony route, or render application shells. Application rules can match regex arguments, bind an application to a bare domain, bind one to a project subdomain, bind one to a specific domain/project pair, or attach application context to a Symfony route without replacing that route's response.

The `symfonicat_asset(path)` Twig helper resolves shell-specific public files under `/domains/{domain-id}/` for domain shells when that folder exists, `/projects/{project-id}/` for project shells when that folder exists, and `/default/` when no matching project or domain folder exists. Admin pages use the default asset folder. Project shells fall back to the active domain folder before using `/default/`. Skeleton folders are included for `public/default/`, `public/domains/example.com/`, and `public/projects/project1/`.

Ids for `Domain`, `Project`, `Application`, `Module`, and `Electron` are stored with a vendor prefix. Default template access returns the clean id:

```twig
{{ project.id }}       {# project1 #}
{{ project.id(true) }} {# core/project1 #}
```

Manual rows use the special `core` vendor. Package rows use their Composer vendor.

## Source Layout

The app kernel stays in `src/Kernel.php`. Symfonicat-owned PHP classes live in `admin/src` under the `Symfonicat\` namespace; services and Doctrine entity mappings are loaded from that tree.

## Package Discovery

Configured package vendors live in `config/packages/symfonicat.yaml`:

```yaml
symfonicat:
    vendors:
        - symfonicat
```

Webpack and schema sync discover package entries under configured Composer vendors. The root package is emitted as `core/...`; installed package entries use ids such as `symfonicat/analytics/main`. The same vendor list is used for package service imports and package controller route imports.

## Admin YAML

Admin YAML snapshots live in `config/packages/symfonicat.yaml` under `symfonicat.admin`. Dump and load them with:

```bash
docker exec php bin/console symfonicat:dump
docker exec php bin/console symfonicat:load
```

`symfonicat:dump` writes Symfonicat admin rows to YAML (excluding `symfonicat_admin`) and preserves `symfonicat.vendors`. Composer runs `symfonicat:schema:update` and then `symfonicat:load` after install, so fresh databases get their tables, package-provided rows, and checked-in admin YAML automatically. Without a `symfonicat.admin` section, load exits without changing the database. The admin header has a Bootstrap-backed `yaml` dropdown linking to `/admin/y/dump` and `/admin/y/load`.

## Admin

Create an admin with:

```bash
docker exec -it php bin/console symfonicat:admin:create <username>
```

and then visit `/admin`.


## Modules

Module controllers are package-owned and run only when the active domain, project, or application has the module attached. Runtime module requests use full-qualified URLs such as `/m/symfonicat/analytics/main`, matching frontend module code like this:

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

Build output and favicon paths use vendor-prefixed target ids; generated start URLs use clean host/path ids. The checked-in example Electron row uses `public/electron/favicon/domain/example.com.svg`, matching the default SVG favicon.

## Sync

`symfonicat:schema:update` synchronizes the Doctrine schema and then synchronizes package-provided modules, domains, applications, and projects. Non-interactive runs create missing package rows automatically; stale modules with referencing rows still require an interactive confirmation before deletion.

```bash
docker exec -it php bin/console symfonicat:schema:update
```

## Messenger

Redis-backed Messenger transports are configured, and default routing sends `*` to `async`. Compose starts eight `messenger-worker` replicas by default; override with `MESSENGER_WORKERS` or `docker compose up -d --scale messenger-worker=<count>`.

For full install, routing, env, Electron, and command details, see [README.md](https://github.com/symfonicat/core/blob/main/README.md).
