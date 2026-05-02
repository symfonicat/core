# Symfonicat

`symfonicat/core` is the full Symfony application for Symfonicat. Public routing, admin CRUD, Doctrine entities, webpack entry discovery, module runtime, Electron packaging, and Docker/FrankenPHP live in this repository.

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

The `php` container installs Composer dependencies, synchronizes the schema, seeds local defaults, runs `npm install`, and builds assets.

## Ids And Vendors

`Domain`, `Project`, `Application`, `Module`, and `Electron` store ids with a vendor prefix and expose the clean id by default:

```twig
{{ project.id }}       {# project1 #}
{{ project.id(true) }} {# core/project1 #}
```

The separate `vendor` field is read-only in admin forms. Manually created rows use `core`; package-discovered rows use their Composer vendor. New ids must be vendor-prefixed internally.

Package discovery is configured in `config/packages/symfonicat.yaml`:

```yaml
symfonicat:
    vendors:
        - symfonicat
```

The root package is treated as the special `core` vendor. Installed packages under configured vendors use ids such as `symfonicat/analytics/main`. The same vendor list drives package service imports, package controller route imports, schema sync, and webpack fallback discovery.

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

Use clean ids in public URLs and templates. Use full ids for admin route parameters and persistence lookups.

Runtime precedence is application, then domain, then project, then Electron for Electron requests only. The same grouped structure is emitted into `window.env`.

## Routing Rules

Supported rule types:

- `domain`: render the domain shell for a matching regex path.
- `project`: suppress the project catch-all for a matching regex path.
- `application`: render an application shell from regex arguments or a named Symfony route.
- `redirect`: redirect a domain or project to another domain, project, or `project.domain` pair.
- `route`: render a named Symfony route for the root of a domain or project.

## Twig

Twig, for the `env` helper, uses dotted lookups such as:

```twig
{{ env('colors.primary') }}
```
There is also a `path_application` helper that generates URL's for Application entities with RoutingRule entities configured for them that works like this:

```twig
{# https://example.com/symfony/*/test #}
{{ path_application(application) }}

{# https://example.com/symfony/PARAM/test #}
{{ path_application(application, ['PARAM']) }}

{# https://example.com/symfony/*/test/somepath/testpath #}
{{ path_application(application, 'somepath/testpath') }}

{# https://example.com/symfony/PARAM/test/somepath/testpath #}
{{ path_application(application, 'somepath/testpath', ['PARAM']) }}

{# application ID also works #}
{{ path_application('core/test', 'somepath/testpath', ['PARAM']) }}
```

## Assets

Webpack entry discovery is driven by `symfonicat:data:webpack`. It scans the root package plus installed Composer packages from configured vendors and resolves:

- `assets/applications/{id}`
- `assets/domains/{id}`
- `assets/projects/{id}`
- `assets/modules/{id}`

Public assets use `assets/symfonicat.js`, `assets/stimulus.js`, `assets/controllers.json`, and `assets/controllers/`. Admin-only JavaScript belongs on `assets/symfonicat_admin.js`, `assets/stimulus_admin.js`, `assets/controllers_admin.json`, and `assets/controllers_admin/`.

## Module Runtime

Backend module controllers live in installed packages and are exposed under their full-qualified package routes, for example `/m/symfonicat/analytics/main`. Frontend module code should keep a full qualifier such as `const mod = 'symfonicat/analytics/main'`; `mod.json()` and `mod.html()` call that full `/m` endpoint and module event subscribers resolve the same full id.

Here is the example Symfonicat module that ships via the `symfonicat/analytics` package:

```javascript
const mod = 'symfonicat/analytics/main'

mod.log('module active!')

const run = async () => {
    
    const result = await mod.json({
        test: true,
    })

    mod.log('/m/symfonicat/analytics/main result:', result)
}

await run()

```

This is the recommended usage pattern. Symfonicat modules are multi-tiered, meaning that in this example `symfonicat` is the vendor, `analytics` is the package, and `main` is the module. Any given Symfonicat composer package can ship with multiple modules.

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

Modules do not have manual CRUD. They synchronize from package assets.

## Electron

Electron rows have vendor-scoped ids, a `type` (`domain`, `project`, or `application`), a matching target relation, an optional favicon, and scoped env values.

Build outputs with:

```bash
docker exec php bin/console symfonicat:electron:build
docker exec php bin/console symfonicat:electron:build <name>
```

The build command renders `templates/electron/{type}/main.twig.js` or `templates/electron/{type}/overrides/{targetId}.twig.js`, writes `electron/{type}/{targetId}/app.js`, writes a local `package.json`, and runs `electron-builder` into `electron/{type}/{targetId}/build`.

Target ids include the vendor prefix for filesystem output and favicon paths. Start URLs use clean host/path ids.

## Sync And Bootstrap

`symfonicat:bootstrap` synchronizes package entries and seeds local defaults:

- `core/localhost`
- `core/example.com`
- `core/project1`
- `core/test`
- `symfonicat/analytics/main`
- `core/example-test` Electron row bound to `core/example.com`
- sample `colors.primary` env values

`symfonicat:schema:update` synchronizes modules, domains, applications, and projects from package assets. Run it interactively when confirmations may be required:

```bash
docker exec -it php bin/console symfonicat:schema:update
```

Other useful commands:

```bash
docker exec php bin/console symfonicat:data:webpack
docker exec php bin/console symfonicat:data:dns
docker exec php bin/console symfonicat:public-suffix:refresh
```
