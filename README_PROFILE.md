# Symfonicat Profile

`symfonicat/core` is the full Symfony 8 application for the Symfonicat runtime: public routing, admin CRUD, parcel rendering, package module runtime, application Electron templates, webpack wiring, and Docker/FrankenPHP live in this repository.

Redis backs cache, sessions, locks, admin throttling, and Messenger's `async` transport.

## Runtime Shape

Public routes:

- `/`
- `/{path}`
- `/application/{vendor}/{id}/{path}`

Core runtime services:

- `DomainService`
- `SubdomainService`
- `ApplicationService`
- `EnvService`
- `ParcelService`
- `ModuleService`
- `RuntimeRenderer`

`PublicRuntimeSubscriber` resolves domain, subdomain, and endpoint context before routing. The public catch-all routes are low priority, so Symfony routes that match directly still handle the request.

Runtime route behavior:

- domain root and domain catch render `templates/domain/*`
- subdomain root and subdomain catch render `templates/subdomain/*`
- endpoint arguments and endpoint catch render `templates/endpoint/*`
- endpoint argument `*` matches one segment
- endpoint catch permits trailing path after the argument match
- admin, application, and module paths are reserved from catch-all rendering

Template overrides:

- `domain/overrides/{domain-id}.html.twig`
- `subdomain/overrides/{subdomain-id}.html.twig`
- `endpoint/overrides/{endpoint-id-basename}.html.twig`

Subdomains use plain ids everywhere. They do not have vendors.

## Applications

`Application` is the app-packaging target for this branch. It carries the role that the old separate Electron type carried: select a URL/runtime context, then generate an Electron app from that selected target.

Application target types:

- `domain`
- `subdomain`
- `endpoint`

The internal application entry loads the application row, sets the `application` request attribute, then renders the selected target through `RuntimeRenderer`.

Endpoint-backed applications use endpoint ids in admin forms and runtime resolution. `path_application()` builds paths from endpoint arguments, substitutes `*` from wildcard arrays, and appends an optional extra path.

Electron main-process templates are under:

- `templates/application/domain/main.twig.js`
- `templates/application/subdomain/main.twig.js`
- `templates/application/endpoint/main.twig.js`

## Entity Ids

- `Domain`: bare host, for example `example.com`
- `Subdomain`: plain label, for example `subdomain1`
- `Endpoint`: string id, commonly package-scoped, for example `core/test`
- `Application`: string id
- `Module`, `Middleware`, `Parcel`: package-scoped ids where package ownership matters

Legacy subdomain inputs with a slash are normalized to the final segment on load and form submission.

## Package Discovery

Configured package vendors live in `config/packages/symfonicat.yaml`:

```yaml
symfonicat:
    vendors:
        - symfonicat
```

Webpack and schema sync discover entries in the root package and installed Composer packages from `vendor/composer/installed.json`.

Entry families:

- `assets/domain/`
- `assets/subdomain/`
- `assets/application/`
- `assets/module/`
- `assets/parcel/`
- `assets/bundle/`

The local `./packages` tree is not part of runtime discovery.

## Env

Env is layered in runtime order:

1. parcel
2. domain
3. subdomain
4. endpoint when active
5. application

Application env is the final override layer, including endpoint-backed application renders.

Twig uses dotted lookups:

```twig
{{ env('colors.primary') }}
```

The grouped env structure is also emitted as `window.env`.

## Middleware And Modules

PSR-15 middleware services are auto-tagged as `symfonicat.middleware`. Runtime rendering selects middleware attached to the active domain, subdomain, or endpoint and runs it directly through the PSR HTTP bridge.

The old `kafkiansky/symfony-middleware` bundle and vendored package copy are removed.

Module controllers extend `AbstractModuleController` and only run when their module is attached to the active domain, subdomain, endpoint, or application. Endpoint module requests preserve endpoint context through `X-Symfonicat-Endpoint`.

## Admin

Admin is guarded by the repo-root `symfonicat.lock`.

Main admin areas:

- `/admin/a` applications
- `/admin/b` bundles/parcels
- `/admin/d/list` domains
- `/admin/end` endpoints
- `/admin/env` env
- `/admin/m` middleware
- `/admin/s` subdomains and schema sync
- `/admin/y/*` YAML dump/load

Forms support parcel attachments, repeatable middleware, modules, scoped env values, and catch flags. Subdomain forms expose only the plain id.

## YAML And Commands

Runtime reads `symfonicat.admin` from `config/packages/symfonicat.yaml`. The database tables are for unlocked admin editing and dumping YAML.

Commands:

- `symfonicat:schema:update`
- `symfonicat:load`
- `symfonicat:dump`
- `symfonicat:purge`
- `symfonicat:admin:create`
- `symfonicat:admin:delete`
- `symfonicat:data:webpack`
- `symfonicat:data:dns`
- `symfonicat:public-suffix:refresh`

`symfonicat:schema:update` synchronizes the Doctrine schema and configured-vendor rows for parcels, middleware, endpoints, modules, domains, applications, and subdomains.

Local PHPUnit can bypass a root-owned container cache with:

```bash
SYMFONICAT_CACHE_DIR=/tmp/symfonicat_dev_cache php bin/phpunit
```
