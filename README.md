## Symfonicat

Symfonicat is a Symfony 8 multi-tenant frontend runtime. It resolves public requests to domains, subdomains, and endpoints, renders the matching parcel-backed template, and exposes modules, middleware, env data, and build-application context where present.

```bash
git clone https://github.com/symfonicat/core symfonicat
cd symfonicat
docker compose up -d --build
docker exec -it php bin/console symfonicat:schema:update
docker exec php bin/console symfonicat:load
docker exec -it php bin/console symfonicat:admin:create <email>
touch symfonicat.lock # enables /core
```

Edit `/etc/hosts` for local public routing:

```text
127.0.0.1 example.com
127.0.0.1 subdomain1.example.com
```

The core area is disabled until `symfonicat.lock` exists in the repo root.

## Runtime

The runtime subscriber resolves the active `Domain`, `Subdomain`, and matching `Endpoint` before Symfony routing. Runtime catch-all routes have low priority, so normal Symfony routes still win when they match.

- a matched domain renders the domain shell on any public path for that host
- a matched subdomain renders the subdomain shell on any public path for that host
- endpoints match their repeatable `arguments`; `*` matches one path segment
- endpoint `catch` allows extra path after the matched arguments; with no arguments configured it acts as a wildcard for the current path
- `/core/*` and `/m/*` are reserved from the public catch-all
- public runtime reads from `config/packages/symfonicat.yaml` only; database-backed lookups are reserved for `/core/*`

Templates resolve in this order:

- `templates/{domain,subdomain,endpoint}/overrides/{id}.html.twig`
- `templates/{domain,subdomain,endpoint}/main.html.twig`

## Ids

- `Domain` ids are bare hostnames, for example `example.com`
- `Subdomain` ids are internal auto-increment integers; the public label is `subdomain.affix`, for example `subdomain1`
- `Application`, `Module`, `Middleware`, and `Parcel` ids remain package-scoped where applicable, for example `core/test`
- `Endpoint` ids are string ids and may be package-scoped, for example `core/test`

```twig
{{ domain.tld }}     {# example.com #}
{{ subdomain.affix }} {# subdomain1 #}
{{ endpoint.id }}    {# core/test #}
{{ application.id }} {# example-test #}
```

## Applications

`Application` is the application-scaffold target in this branch. It replaces the old separate Electron row concept: an application selects a URL context, and that selected target is what the generated Electron skeleton will launch once it is built later.

Build-application requests expose `application` through Twig and `window.application` when the request context provides it.

Application build templates:

- `templates/application/main.js.twig`
- `templates/application/overrides/{application-id}.js.twig`

The build command generates a buildable Electron skeleton in `applications/{application.id}/` with `main.js`, `package.json`, `README.md`.

## Middleware

Middleware is selected from the active runtime scope:

- domain middleware always runs when a domain is active
- subdomain middleware always runs when a subdomain is active
- endpoint middleware runs for endpoint renders

Middleware services implement PSR-15 `Psr\Http\Server\MiddlewareInterface` and are tagged automatically as `symfonicat.middleware`.

## Modules

Modules can be attached to domains, subdomains, or endpoints.

Backend module controllers should extend `Symfonicat\Controller\AbstractModuleController`. They only execute when the module is attached to the active domain, subdomain, or endpoint context. `AbstractModuleController` exposes `json()` for `JsonResponse` and `html()`. Module routes are declared with `#[ModuleRoute]` on the controller class and `#[Module]` on the action method.

**Front end module code example:**

```javascript
const mod = 'symfonicat/analytics/main'
      mod.log('module active!')

// posts { test: true } to /m/symfonicat/analytics/main
const result = await mod.json({ test: true })
      mod.log('/m/symfonicat/analytics/main result:', result)
```

## Env

resolution order:

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

- `assets/module/`
- `assets/parcel/`

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

`symfonicat_subdomain` rows store an internal integer `id`, a public `affix`, and an optional `domain_id`; the UI and runtime use `affix`, not the internal id. Multiple rows may share the same affix when they belong to different domains.

Packages opt into Symfonicat discovery by setting `extra.symfonicat: true` in their `composer.json`:

```yaml
extra:
    symfonicat: true
```

`composer install` runs `symfonicat:purge` so deployments start with a clean `symfonicat_*` schema; public runtime still reads `config/packages/symfonicat.yaml`, and only `/core/*` routes use the database-backed CRUD/sync flow.

## Sync

`symfonicat:schema:update` synchronizes the Doctrine schema and Symfonicat package rows:

- parcels
- domains
- subdomains
- endpoints
- modules
- middleware
- applications

It removes stale package-backed parcels, clears affected parcel references, mirrors tagged middleware services into rows, and stores domain/subdomain/endpoint middleware in dedicated join tables.

## Native

Symfonicat supports the ability for Composer packages to ship with PHP extensions and Go modules. Relative to the project root, and composer packages, modules/extensions are located here:

**PHP extensions:**

- `native/ext/**`
- `core/native/ext/**`
- `vendor/**/**/native/ext/**`

**Go modules:**

- `native/go/**`
- `core/native/go/**`
- `vendor/**/**/native/go/**`

## AWS

Read [AWS.md](AWS.md) for AWS deployment **bin/\*** Bash scripts that make it easy to set up a full HTTPS-enabled container deployment.

## PHPUnit

`docker exec -it php ./bin/phpunit`

## Picture of @dunglas at the zoo

included.
