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

The runtime subscriber resolves the active `Domain`, `Subdomain`, and matching `Endpoint` before Symfony routing. Runtime catch-all routes have low priority, so normal Symfony routes still win when they match.

Resolution rules:

- a matched domain renders the domain shell on any public path for that host
- a matched subdomain renders the subdomain shell on any public path for that host
- endpoints match their repeatable `arguments`; `*` matches one path segment
- endpoint `catch` allows extra path after the matched arguments
- `/admin/*` and `/m/*` are reserved from the public catch-all

Templates resolve in this order:

- `templates/{domain,subdomain,endpoint}/overrides/{id}.html.twig`
- fallback to `templates/{domain,subdomain,endpoint}/main.html.twig`

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

## Applications

`Application` is the application-scaffold target in this branch. It replaces the old separate Electron row concept: an application selects a URL context, and that selected target is what the generated Electron skeleton will launch once it is built later.

The application target is inferred from the populated relation fields: `endpoint` wins when present, otherwise `subdomain`, otherwise `domain`. `domain` is always required.

Build-application requests expose `application` through Twig and `window.application` when the request context provides it.

Application build templates live under `templates/application/main.js.twig`, with optional per-application overrides at `templates/application/overrides/{application-id}.js.twig`. The build command generates a buildable Electron skeleton in `application/{application.id}/` with `main.js`, `package.json`, and a local README.

## Middleware

Middleware is selected from the active runtime scope:

- domain middleware always runs when a domain is active
- subdomain middleware always runs when a subdomain is active
- endpoint middleware runs for endpoint renders

Middleware services implement PSR-15 `Psr\Http\Server\MiddlewareInterface` and are tagged automatically as `symfonicat.middleware`.

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

Runtime pages expose `application_helper()`, `endpoint_helper()`, and `request_helper()` to populate `window.application`, `window.endpoint`, and `window.request`. Module requests send the request token back in `X-Symfonicat-Module-Context` plus `X-CSRF-Token`, and the server uses the stored context to restore endpoint scope before backend module checks run.

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

Packages opt into Symfonicat discovery by setting `extra.symfonicat: true` in their `composer.json`:

```yaml
extra:
    symfonicat: true
```

Admin YAML commands:

```bash
docker exec php bin/console symfonicat:application:build
docker exec php bin/console symfonicat:scriptling:copy
docker exec php bin/console symfonicat:scriptling:bash
docker exec php bin/console symfonicat:dump
docker exec php bin/console symfonicat:load
docker exec php bin/console symfonicat:purge
```

Runtime reads the `symfonicat` block from YAML. Database tables are for unlocked admin editing and regenerating YAML; production runtime should not need the tables after deployment.

Admin CRUD and schema sync actions automatically refresh `config/packages/symfonicat.yaml` after successful writes.

`composer install` runs `symfonicat:purge` so deployments start with a clean `symfonicat_*` schema; runtime still reads `config/packages/symfonicat.yaml`.

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

`symfonicat:schema:update` synchronizes the Doctrine schema and Symfonicat package rows:

- bundles/parcels
- domains
- subdomains
- endpoints
- modules
- middleware
- applications

It removes stale package-backed parcels, clears affected parcel references, mirrors tagged middleware services into rows, and stores domain/subdomain/endpoint middleware in dedicated join tables.

## Scriptling

The Docker container  uses `symfonicat:scriptling:copy` and `symfonicat:scriptling:bash` to gather FrankenPHP extensions, initialize them with `frankenphp extension-init`, and compile a custom FrankenPHP binary with `xcaddy`. The final runtime image is based on that builder output so PHP workers and FrankenPHP use the same compiled extension set.

Installed Symfonicat packages can ship FrankenPHP Scriptling extensions under `extensions/{name}`. Docker keeps `vendor/{vendor}/{package}/extensions/**` in the build context, overlays those files after `composer install`, and then includes every discovered extension in the `xcaddy` build. The analytics package includes `extensions/lowercase`, which exports `scriptling_analytics_lowercase(string $value): string`.


For local tests, `var/cache/test` may be owned by the container. Use an alternate cache directory:

```bash
SYMFONICAT_CACHE_DIR=/tmp/symfonicat_dev_cache php bin/phpunit
```
