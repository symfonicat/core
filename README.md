## Symfonicat

Symfonicat is a Symfony 8 multi-tenant frontend runtime. It resolves public requests to domains, subdomains, and endpoints, renders the matching parcel-backed template, and exposes modules, middleware, env data, and build-application context where present.

Edit `/etc/hosts` for local public routing:

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
docker exec -it php bin/console symfonicat:admin:create <email>
touch symfonicat.lock
```

The admin area is disabled until `symfonicat.lock` exists in the repo root.

## Runtime

Public entry routes:

- `/`
- `/{path}`

The runtime subscriber resolves the active `Domain`, `Subdomain`, and matching `Endpoint` before Symfony routing. Runtime catch-all routes have low priority, so normal Symfony routes still win when they match.

Resolution rules:

- domain root renders the domain shell
- domain `catch` renders the domain shell for unmatched paths on the bare domain
- subdomain root renders the subdomain shell
- subdomain `catch` renders the subdomain shell for unmatched paths on that subdomain
- endpoints match their repeatable `arguments`; `*` matches one path segment
- endpoint `catch` allows extra path after the matched arguments
- `/admin/*` and `/m/*` are reserved from the public catch-all

Templates resolve in this order:

- `templates/domain/overrides/{domain-id}.html.twig`
- `templates/subdomain/overrides/{subdomain-id}.html.twig`
- `templates/endpoint/overrides/{endpoint-id-basename}.html.twig`
- fallback to `templates/{domain,subdomain,endpoint}/main.html.twig`

Override template ids use the entity's appropriate id. Subdomains are plain ids, not vendor-scoped ids.

## Ids

Id rules:

- `Domain` ids are bare hostnames, for example `example.com`
- `Subdomain` ids are plain labels, for example `subdomain1`
- `Application`, `Module`, `Middleware`, and `Parcel` ids remain package-scoped where applicable, for example `core/test`
- `Endpoint` ids are string ids and may be package-scoped, for example `core/test`

```twig
{{ domain.id }}      {# example.com #}
{{ subdomain.id }}   {# subdomain1 #}
{{ endpoint.id }}    {# core/test #}
{{ application.id }} {# example-test #}
```

Legacy YAML or form input such as `core/subdomain1` is normalized to `subdomain1` for subdomains.

## Applications

`Application` is the application-packaging target in this branch. It replaces the old separate Electron row concept: an application selects a URL context, and that selected target is what Electron packaging launches.

Application target types:

- `domain`
- `subdomain`
- `endpoint`

There is no public `/application/...` runtime route in this branch, and the application path helper is not available yet. Build-application requests can still expose `application` through Twig and `window.application` when the request context provides it.

Electron main-process templates for applications live under `templates/application/{domain,subdomain,endpoint}/`.

## Middleware

Middleware is selected from the active runtime scope:

- domain middleware always runs when a domain is active
- subdomain middleware also runs for subdomain and endpoint renders under that subdomain
- endpoint middleware runs for endpoint renders

Middleware services implement PSR-15 `Psr\Http\Server\MiddlewareInterface` and are tagged automatically as `symfonicat.middleware`. The old vendored `kafkiansky/symfony-middleware` package and local `./packages` copy are no longer used.

## Modules

Modules can be attached to domains, subdomains, or endpoints.

Backend module controllers should extend `Symfonicat\Controller\AbstractModuleController`. They only execute when the module is attached to the active domain, subdomain, or endpoint context.

Frontend module code posts to full package routes:

```javascript
const mod = 'symfonicat/analytics/main'
      mod.log('module active!')

// posts { test: true } to /m/symfonicat/analytics/main
const result = await mod.json({ test: true })
      mod.log('/m/symfonicat/analytics/main result:', result)
```

Endpoint-rendered pages send `X-Symfonicat-Endpoint` with module requests so backend module checks can keep the endpoint context.

## Env

Env resolution order:

1. parcel
2. domain
3. subdomain
4. endpoint where present
5. application

Application values override endpoint values when the request is in application context.

The same grouped structure is exposed through `window.env` and Twig:

```twig
{{ env('colors.primary') }}
```

## Assets

Private webpack data comes from `symfonicat:data:webpack`. It scans the root package and installed Composer packages from configured vendors under:

- `assets/domain/`
- `assets/subdomain/`
- `assets/application/`
- `assets/module/`
- `assets/parcel/`
- `assets/bundle/`

Package discovery uses `vendor/composer/installed.json`; the old local `./packages` tree is not a runtime source.

The public `symfonicat_asset(path)` helper resolves shell-specific assets by checking:

1. subdomain
2. domain
3. default

It can also target an entity directly:

```twig
<link rel="icon" href="{{ symfonicat_asset('favicon.svg', domain) }}" />
<link rel="icon" href="{{ symfonicat_asset('favicon.svg', subdomain) }}" />
```

## Configuration

Package vendors are configured in `config/packages/symfonicat.yaml`:

```yaml
symfonicat:
    vendors:
        - symfonicat
```

Admin YAML commands:

```bash
docker exec php bin/console symfonicat:dump
docker exec php bin/console symfonicat:load
docker exec php bin/console symfonicat:purge
```

Runtime reads `symfonicat.admin` from YAML. Database tables are for unlocked admin editing and regenerating YAML; production runtime should not need the tables after deployment.

## Admin

Admin routes:

- `/admin/a` applications
- `/admin/b` bundles/parcels
- `/admin/d/list` domains
- `/admin/end` endpoints
- `/admin/env` env
- `/admin/m` middleware
- `/admin/s` subdomains and schema sync action
- `/admin/y/*` YAML tools

Forms support parcel attachments, repeatable middleware, modules, scoped env values, and catch flags where the entity supports them.

## Sync

`symfonicat:schema:update` synchronizes the Doctrine schema and configured-vendor package rows:

- bundles/parcels
- domains
- subdomains
- endpoints
- modules
- middleware
- applications

It removes stale package-backed parcels, clears affected parcel references, mirrors tagged middleware services into rows, and stores domain/subdomain/endpoint middleware in dedicated join tables.

For local tests, `var/cache/test` may be owned by the container. Use an alternate cache directory:

```bash
SYMFONICAT_CACHE_DIR=/tmp/symfonicat_dev_cache php bin/phpunit
```
