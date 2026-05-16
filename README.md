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

Package discovery is configured in `config/packages/symfonicat.yaml`. The vendors list determines what composer package vendors are searched for Symfonicat modules:

```yaml
symfonicat:
    vendors:
        - symfonicat
```

```bash
docker exec php bin/console symfonicat:dump # writes database to symfonicat.yaml
docker exec php bin/console symfonicat:load # imports symfonicat.yaml into database
docker exec php bin/console symfonicat:purge # drops all symfonicat_* tables
```

Runtime reads `symfonicat.admin` from this YAML file. The `symfonicat_*` tables are for unlocked admin editing and regenerating YAML; production runtime should not need them after deployment.

## Ids

`Project`, `Application`, and `Module` store package-scoped ids. `Domain` ids are always bare domain names, and `Electron` ids are plain row ids:

```twig
{{ domain.id }}        {# example.com #}
{{ electron.id }}      {# example-test #}
{{ subdomain.id }}        {# core/subdomain1 #}
{{ subdomain.id(false) }} {# subdomain1 #}
```

## Public Runtime

Runtime resolution is layered:

1. `DomainService` resolves the base host.
2. `ProjectService` resolves the first affix when present.
3. `RoutingRuleSubscriber` applies redirect, route, domain, subdomain, and application rules.
4. `ApplicationService` loads application shells from argument rules, route-bound rules, or domain/subdomain application bindings.

Public routes:

- `/` renders the domain shell.
- `/{path}` renders the subdomain shell when a subdomain affix is active.
- `/application/{vendor}/{id}/{path}` is the internal application entry route and uses the full vendor-prefixed application id in the URL.

## Assets

### Private

Webpack entry discovery is driven by `symfonicat:data:webpack`. It scans the root package plus installed Composer packages from configured vendors and resolves:

- core entries from `assets/{domain,subdomain,module,bundle}/{id}`
- installed package entries from `{composer-package-dir}/assets/{domain,subdomain,module,bundle}/{id}`

Bundle rows live in `symfonicat_bundle`, use vendor-scoped ids like `core/shared` or `symfonicat/analytics/shared`, and point at a `path` that is either a directory containing `index.js` or a direct entry file. Domains and subdomains can reference a bundle; the public shell renders the referenced bundle entry before the shell-specific entry.

Bootstrap is available at `assets/bootstrap` with some overrides at `assets/scss`

### Public

The `symfonicat_asset(path)` Twig helper resolves shell-specific public assets. Without a second argument, it automatically searches the public folder for the file, prioritizing subdomain, then domain, then the default folder.

Notice how the favicons work on each url:

- `example.com`: purple favicon, `public/domains/example.com/favicon.svg`
- `subdomain1.example.com`: green favicon, `public/subdomains/subdomain1/favicon.svg`
- `example.com/admin`: blue favicon, `public/default/favicon.svg`

But notice that in `admin/templates/base.html.twig` and `templates/base.html.twig` the only `symfonicat_asset()` call is this:

```twig
<link rel="icon" href="{{ symfonicat_asset('favicon.svg') }}" />
```

but it can be used like this:

```twig
<link rel="icon" href="{{ symfonicat_asset('favicon.svg', application) }}" />
<link rel="icon" href="{{ symfonicat_asset('favicon.svg', electron) }}" />
```

Passing an Electron row resolves assets under `public/electron/{electron.id}/`.

## Env

Env resolution is application, then domain, then subdomain, then Electron for Electron requests only. The same grouped structure is emitted into `window.env`. Twig uses the `env()` helper for dotted lookups:

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

For domain-bound and subdomain-bound application rules, `path_application()` returns the bound path on the current host. Use the matching domain or subdomain host when linking across hosts.

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

Root-level `route` rules are evaluated before domain/subdomain application bindings, so a domain or subdomain can still hand its root request to a Symfony-only route.

## Modules

Backend module controllers live in installed packages and are exposed under full package routes such as `/m/symfonicat/analytics/main`. Frontend module code should use the same full qualifier:

```javascript
const mod = 'symfonicat/analytics/main'
      mod.log('module active!')

// posts { test: true } to /m/symfonicat/analytics/main
const result = await mod.json({ test: true })
      mod.log('/m/symfonicat/analytics/main result:', result)
```

Module controllers should extend `Symfonicat\Controller\AbstractModuleController`, which only runs a module when it is attached to the active subdomain, domain, or application context.

## Admin

The magic is in the `/admin` section. The entire `/admin` section is hard-disabled unless `<repo>/symfonicat.lock` exists. Create the ignored lock file with `touch symfonicat.lock` to open the admin area, and remove it to close the admin area again.

Bundle CRUD is under `/admin/b`; domain and subdomain forms can attach one bundle row.
The admin schema action is `/admin/s`; it runs the same non-interactive synchronization as `symfonicat:schema:update`, flashes the result, and returns to the referring admin page.

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

`symfonicat:schema:update` first synchronizes the Doctrine schema and then synchronizes bundles, modules, applications, and subdomains from package assets. Package-backed bundle rows whose `assets/bundle/{id}` directory disappears are removed, and domain/subdomain references to those bundles are cleared. Run it explicitly when you want dev/admin tables:

```bash
docker exec -it php bin/console symfonicat:schema:update
```

Then load the checked-in YAML if you want to edit it through `/admin`:

```bash
docker exec php bin/console symfonicat:load
```

Composer and Docker startup do not run schema update or YAML load automatically. Removing a stale module that still has referencing rows requires an interactive run so the affected rows can be reviewed before deletion.

## Picture of @dunglas at the Zoo

This repository includes an AI-generated picture of Kévin Dunglas at the zoo:

[dunglas_at_zoo.png](https://github.com/symfonicat/core/blob/main/dunglas_at_zoo.png)
