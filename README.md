## Symfonicat

Edit `/etc/hosts`:

```text
127.0.0.1 example.com
127.0.0.1 subdomain1.example.com
```

```bash
git clone https://github.com/symfonicat/core symfonicat
cd symfonicat
docker compose up -d
docker exec -it php bin/console symfonicat:schema:update
docker exec php bin/console symfonicat:load
docker exec -it php bin/console symfonicat:admin:create <email> # prints QR code
touch symfonicat.lock # enables /admin
```

First boot can take several minutes.

The `php` container:

- installs Composer dependencies
- runs `npm install`
- builds assets

## Configuration

Package discovery is configured in `config/packages/symfonicat.yaml`.

The `vendors` list determines which Composer package vendors are searched for Symfonicat modules:

```yaml
symfonicat:
    vendors:
        - symfonicat
```

Admin YAML commands:

```bash
docker exec php bin/console symfonicat:dump # writes database to symfonicat.yaml
docker exec php bin/console symfonicat:load # imports symfonicat.yaml into database
docker exec php bin/console symfonicat:purge # drops all symfonicat_* tables
```

Runtime reads `symfonicat.admin` from this YAML file.

The `symfonicat_*` tables are for unlocked admin editing and regenerating YAML.
Production runtime should not need them after deployment.

## Ids

Id rules:

- `Project`, `Application`, and `Module` store package-scoped ids
- `Domain` ids are always bare domain names
- `Electron` ids are plain row ids

```twig
{{ domain.id }}        {# example.com #}
{{ application.id }}      {# example-test #}
{{ subdomain.id }}        {# core/subdomain1 #}
{{ subdomain.id(false) }} {# subdomain1 #}
```
## Assets

### Private

Webpack entry discovery is driven by `symfonicat:data:webpack`.

It scans the root package plus installed Composer packages from configured vendors and resolves:

- core entries from `assets/{module,bundle}/{id}`
- installed package entries from `{composer-package-dir}/assets/{module,bundle}/{id}`

Endpoint rows can also attach modules with the same grouped multi-select used on domains and subdomains.

Bootstrap is available at `assets/bootstrap` with some overrides at `assets/scss`

### Public

The `symfonicat_asset(path)` Twig helper resolves shell-specific public assets.

Without a second argument, it searches the public folder for the file, prioritizing:

1. subdomain
2. domain
3. default

Favicons by URL:

- `example.com`: purple favicon, `public/domains/example.com/favicon.svg`
- `subdomain1.example.com`: green favicon, `public/subdomains/subdomain1/favicon.svg`
- `example.com/admin`: blue favicon, `public/default/favicon.svg`

In `admin/templates/base.html.twig` and `templates/base.html.twig` the common call is:

```twig
<link rel="icon" href="{{ symfonicat_asset('favicon.svg') }}" />
```

It can also target an entity directly:

```twig
<link rel="icon" href="{{ symfonicat_asset('favicon.svg', application) }}" />
<link rel="icon" href="{{ symfonicat_asset('favicon.svg', electron) }}" />
```

Passing an Electron row resolves assets under `public/electron/{electron.id}/`.

## Env

Env resolution order:

- bundle
- domain
- subdomain
- application
- Electron requests only: Electron layer last

The same grouped structure is emitted into `window.env`.

Twig uses the `env()` helper for dotted lookups:

```twig
{{ env('colors.primary') }}
```

## Paths

`path_application()` generates URLs for application shells:

```twig
{# for the test application #}
{# which has a catch-all application binding pointing it to /symfonicat/*/test* #}

{# /symfonicat/*/test #}
{{ path_application(application) }}

{# /symfonicat/tay/test #}
{{ path_application(application, ['tay']) }}

{# /symfonicat/*/test/somepath/testpath #}
{{ path_application(application, 'somepath/testpath') }}

{# /symfonicat/tay/test/somepath #}
{{ path_application('core/test', 'somepath', ['tay']) }}
```

Application rows can target a domain, subdomain, or endpoint.

Endpoint-backed applications use the endpoint id in the admin select and in runtime resolution.

- Domain, subdomain, and endpoint catch flags are submitted through checkbox fields that treat blank values as false.
- Empty POST data does not leak into boolean columns.

- `symfonicat:load` binds boolean columns explicitly when it restores admin rows, so `catch` values load cleanly on PostgreSQL.
- Admin YAML dump/load round-trips domains, subdomains, bundles, endpoints, middleware, modules, and their join rows together.
- Subdomains keep their bundle ownership when they are dumped and loaded again.
- The checked-in sample YAML includes `example.com` and `core/subdomain1` as starter rows.

`path_application()` is simple:

- one argument can be the extra path, like `somepath/testpath`
- one argument can be the wildcard replacement array
- wildcard replacements are applied in array order

For domain-bound and subdomain-bound application rules, `path_application()` returns the bound path on the current host.

Use the matching domain or subdomain host when linking across hosts.

## Routing Rules

Supported rule types:

- `domain`: render the domain shell for a matching regex path.
- `subdomain`: suppress the subdomain catch-all for a matching regex path.
- `application`: render an application shell from regex arguments, bind an application to a domain, subdomain, or domain/subdomain pair, or attach application context to a named Symfony route.
- `redirect`: redirect a domain or subdomain to another domain, subdomain, or `subdomain.domain` pair.
- `route`: render a named Symfony route for the root of a domain or subdomain.

Application rules support these application types:

- `arguments`: match regex path segments and render the application shell.
- `route`: attach application context to a named Symfony route without replacing that route's response.
- `domain`: render the application shell for the bare matching domain.
- `subdomain`: render the application shell for the matching subdomain affix.
- `domain_subdomain`: render the application shell for the matching subdomain on the matching domain.

Root-level `route` rules are evaluated before domain/subdomain application bindings.

That means a domain or subdomain can still hand its root request to a Symfony-only route.

## Modules

Backend module controllers live in installed packages and are exposed under full package routes such as `/m/symfonicat/analytics/main`.

Frontend module code should use the same full qualifier:

```javascript
const mod = 'symfonicat/analytics/main'
      mod.log('module active!')

// posts { test: true } to /m/symfonicat/analytics/main
const result = await mod.json({ test: true })
      mod.log('/m/symfonicat/analytics/main result:', result)
```

Module controllers should extend `Symfonicat\Controller\AbstractModuleController`, which only runs a module when it is attached to the active subdomain, domain, or application context.

## Admin

The `/admin` section is hard-disabled unless `<repo>/symfonicat.lock` exists.

Admin basics:

- create the ignored lock file with `touch symfonicat.lock` to open the admin area
- remove it to close the admin area again
- applications live at `/admin/a`
- application edit/delete routes resolve ids explicitly so missing rows fall back to the index with a flash instead of throwing a Doctrine entity-resolution 404
- the delete route is routed separately from edit so `/admin/a/{id}/delete` does not get swallowed by the edit matcher

CRUD routes:

- bundle CRUD: `/admin/b`
- endpoint CRUD: `/admin/end`
- middleware CRUD: `/admin/m`
- schema action: `/admin/s`
- subdomain delete routes use `/admin/s/{id}/delete` so POST deletes do not collide with edit

Forms and rows:

- domain and subdomain forms can attach one bundle row, repeatable middleware rows, and a `catch` flag
- endpoint rows attach to a bundle, can carry repeatable middleware rows and scoped env rows, and can define repeatable arguments through the shared multifield editor
- the schema action runs the same non-interactive synchronization as `symfonicat:schema:update`, flashes the result, and returns to the referring admin page

## Electron

There is an `electron` Twig variable available in any template if the request is coming from a known Electron app. The variable is the `Electron` entity row from `symfonicat.yaml`:

```twig
{% if electron %}

    {# output Electron-specific code #}

{% endif %}
```

Electron rows have plain ids, a `type` (`domain`, `subdomain`, or `application`), a matching target relation, and scoped env values. The generated Electron start URL includes `?electron={electron.id}` so Symfony can resolve the active Electron row on every request.

Build outputs with:

```bash
docker exec php bin/console symfonicat:electron:build
docker exec php bin/console symfonicat:electron:build <name>
```

The build command renders `templates/electron/{type}/main.twig.js` or `templates/electron/{type}/overrides/{targetId}.twig.js`, writes `electron/{type}/{targetId}/app.js`, writes a local `package.json`, and runs `electron-builder` into `electron/{type}/{targetId}/build`.

## Sync

`symfonicat:schema:update` first synchronizes the Doctrine schema and then synchronizes bundles, middleware, endpoints, modules, applications, domains, and subdomains from package assets.

It does the following:

- removes package-backed bundle rows whose `assets/bundle/{id}` directory disappears
- clears domain/subdomain references to missing bundles
- mirrors tagged middleware services into `symfonicat_middleware`
- removes stale middleware rows when their class is no longer available
- stores domain, subdomain, and endpoint middleware attachments in their own join tables
- uses the shared env collection pattern for domain, subdomain, bundle, application, and endpoint env rows
- exposes named `EnvService` flatten helpers for bundle, domain, subdomain, endpoint, and application layers in that order
- persists domain and subdomain `catch` flags on their own rows
- keeps endpoint rows keyed by string id with bundle reference, `catch`, repeatable middleware rows, scoped env rows, and repeatable arguments

Run it explicitly when you want dev/admin tables:

```bash
docker exec -it php bin/console symfonicat:schema:update
```

Then load the checked-in YAML if you want to edit it through `/admin`:

```bash
docker exec php bin/console symfonicat:load
```

Composer and Docker startup do not run schema update or YAML load automatically.

Removing a stale module that still has referencing rows requires an interactive run so the affected rows can be reviewed before deletion.

## Picture of @dunglas at the Zoo

This repository includes an AI-generated picture of Kévin Dunglas at the zoo:

[dunglas_at_zoo.png](https://github.com/symfonicat/core/blob/main/dunglas_at_zoo.png)
