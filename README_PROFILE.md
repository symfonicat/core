# Symfonicat

`symfonicat/core` is the full Symfonicat Symfony application: public routing, admin CRUD, package module runtime, Electron packaging, webpack wiring, and Docker/FrankenPHP are all in this repository.

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

## Admin

Admin is isolated from host users and uses Symfonicat-owned admin rows with HTTP basic plus TOTP MFA. The main surfaces are applications, domains, Electron rows, env keys, projects, and routing rules. Vendor fields are read-only in admin forms.

Create an admin with:

```bash
docker exec -it php bin/console symfonicat:admin:create <username>
```

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

`symfonicat:bootstrap` synchronizes package rows and seeds local defaults such as `core/example.com`, `core/project1`, `core/test`, `symfonicat/analytics/main`, and the sample Electron row.

`symfonicat:schema:update` synchronizes package-provided modules, domains, applications, and projects:

```bash
docker exec -it php bin/console symfonicat:schema:update
```

For full install, routing, env, Electron, and command details, see [README.md](https://github.com/symfonicat/core/blob/main/README.md).
