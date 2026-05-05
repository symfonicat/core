# Symfonicat

`symfonicat/core` is the full Symfonicat Symfony application: public routing, admin CRUD, package module runtime, Electron packaging, webpack wiring, and Docker/FrankenPHP live in this repository.

## Runtime

`DomainService`, `ProjectService`, routing rules, and `ApplicationService` resolve the active domain, project, and application shell. Public routes are `/`, `/{path}`, and the internal `/application/{id}/{path}` application entry route.

Ids for `Domain`, `Project`, `Application`, `Module`, and `Electron` are stored with a vendor prefix. Default template access returns the clean id:

```twig
{{ project.id }}       {# project1 #}
{{ project.id(true) }} {# core/project1 #}
```

Manual rows use the special `core` vendor. Package rows use their Composer vendor.

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

`symfonicat:dump` writes Symfonicat admin rows to YAML (excluding `symfonicat_admin`) and preserves `symfonicat.vendors`. `symfonicat:load` is also run after `composer install`; without a `symfonicat.admin` section it exits without changing the database. The admin header has a `yaml` dropdown linking to `/admin/y/dump` and `/admin/y/load`.

## Admin

Create an admin with:

```bash
docker exec -it php bin/console symfonicat:admin:create <username>
```

and then visit `/admin`.


## Modules

Module controllers are package-owned and run only when the active domain, project, or application has the module attached. Runtime module requests use full-qualified URLs such as `/m/symfonicat/analytics/main`, matching frontend module code like `const mod = 'symfonicat/analytics/main'`.

## Electron

Electron rows are managed under `/admin/e` and build with:

```bash
docker exec php bin/console symfonicat:electron:build
docker exec php bin/console symfonicat:electron:build <name>
```

Build output and favicon paths use vendor-prefixed target ids; generated start URLs use clean host/path ids.

## Sync

`symfonicat:schema:update` synchronizes package-provided modules, domains, applications, and projects:

```bash
docker exec -it php bin/console symfonicat:schema:update
```

For full install, routing, env, Electron, and command details, see [README.md](https://github.com/symfonicat/core/blob/main/README.md).
