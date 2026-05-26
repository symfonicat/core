# Symfonicat Profile

`symfonicat/core` is the full Symfony 8 application for the Symfonicat runtime: public routing, admin CRUD, parcel rendering, package module runtime, application Electron templates, webpack wiring, and Docker/FrankenPHP live in this repository.

Redis backs cache, sessions, locks, admin throttling, and Messenger's `async` transport.

## Runtime Shape

Public routes:

- `/`
- `/{path}`

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

- matched domains render `templates/domain/*` on any public path for that host
- matched subdomains render `templates/subdomain/*` on any public path for that host
- endpoint arguments and endpoint catch render `templates/endpoint/*`
- endpoint argument `*` matches one segment
- endpoint catch permits trailing path after the argument match
- admin and module paths are reserved from catch-all rendering

Template overrides:

- `domain/overrides/{domain-id}.html.twig`
- `subdomain/overrides/{subdomain-id}.html.twig`
- `endpoint/overrides/{endpoint-id-basename}.html.twig`

Subdomains use plain ids everywhere. They do not have vendors.

## Applications

`Application` is the app-scaffold target for this branch. It carries the role that the old separate Electron type carried: select a URL/runtime context from the application row's domain, subdomain, and endpoint fields, then generate an Electron skeleton from that selected target.

The application target is derived from the populated relation fields: `endpoint` wins when present, otherwise `subdomain`, otherwise `domain`. `domain` is always required.

Build-application requests can still expose the `application` Twig variable and `window.application` when the request context provides it. There is no public `/application/...` runtime route in this branch, and the build-app path helper has not been implemented.

Application build templates are under:

- `templates/application/main.js.twig`
- `templates/application/overrides/{application-id}.js.twig`

The build command is `symfonicat:application:build` (alias `symfonicat:electron:build`), and it generates a buildable Electron skeleton in `application/{application.id}/` with `main.js`, `package.json`, and a local README.

## Entity Ids

- `Domain`: bare host, for example `example.com`
- `Subdomain`: plain label, for example `subdomain1`
- `Endpoint`: string id, commonly package-scoped, for example `core/test`
- `Application`: string id
- `Module`, `Middleware`, `Parcel`: package-scoped ids where package ownership matters

Legacy subdomain inputs with a slash are normalized to the final segment on load and form submission.

## Package Discovery

Packages participate in discovery when their `composer.json` sets `extra.symfonicat: true`:

```yaml
extra:
    symfonicat: true
```

Webpack and schema sync discover entries in the root package and installed Composer packages whose `composer.json` sets `extra.symfonicat: true`.

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

PSR-15 middleware services are auto-tagged as `symfonicat.middleware`. Runtime rendering always runs middleware attached to the active domain and subdomain, plus endpoint middleware when an endpoint render wins, and passes them through the PSR HTTP bridge.

The old `kafkiansky/symfony-middleware` bundle and vendored package copy are removed.

Module controllers extend `AbstractModuleController` and only run when their module is attached to the active domain, subdomain, or endpoint. Runtime pages expose `application_helper()`, `endpoint_helper()`, and `request_helper()` for `window.application`, `window.endpoint`, and `window.request`. Module requests replay the server-issued request context through `X-Symfonicat-Module-Context` and `X-CSRF-Token` so endpoint scope is restored server-side before module checks run.

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

Runtime reads the `symfonicat` block from `config/packages/symfonicat.yaml`. The database tables are for unlocked admin editing and dumping YAML.

Admin CRUD and schema sync actions automatically refresh `config/packages/symfonicat.yaml` after successful writes.

`composer install` and `composer update` also run `symfonicat:purge` so deployments start by rebuilding `symfonicat_*` tables from `symfonicat.yaml`.

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

`symfonicat:schema:update` synchronizes the Doctrine schema and Symfonicat package rows for parcels, middleware, endpoints, modules, domains, applications, and subdomains.

Local PHPUnit can bypass a root-owned container cache with:

```bash
SYMFONICAT_CACHE_DIR=/tmp/symfonicat_dev_cache php bin/phpunit
```
