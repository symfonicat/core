# Symfonicat Profile

`symfonicat/core` is the full Symfony 8 application for the Symfonicat runtime: public routing, admin CRUD, parcel rendering, package module runtime, application Electron templates, webpack wiring, and Docker/FrankenPHP live in this repository.

The Docker image runs `composer install` during image build, then uses `symfonicat:scriptling:copy` and `symfonicat:scriptling:bash` to gather FrankenPHP extensions, initialize them with `frankenphp extension-init`, and compile a custom FrankenPHP binary with `xcaddy`. The separate `npm` Compose service runs `npm ci` and `npm run build` after `php` is healthy so `public/build` is generated with PHP available for webpack discovery. The final runtime image is based on that builder output so PHP workers and FrankenPHP use the same compiled extension set.

Installed Symfonicat packages can ship FrankenPHP Scriptling extensions under `extensions/{name}`. Docker keeps `vendor/{vendor}/{package}/extensions/**` in the build context, overlays those files after `composer install`, and then includes every discovered extension in the `xcaddy` build. The analytics package includes `extensions/lowercase`, which exports `scriptling_analytics_lowercase(string $value): string`.

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

Build-application requests expose `application` through Twig and `window.application` when the request context provides it.

Application build templates live under `templates/application/main.js.twig`, with optional per-application overrides at `templates/application/overrides/{application-id}.js.twig`. The build command generates a buildable Electron skeleton in `application/{application.id}/` with `main.js`, `package.json`, `README.md`.

## Entity Ids

- `Domain`: bare host, for example `example.com`
- `Subdomain`: plain label, for example `subdomain1`
- `Endpoint`: string id, commonly package-scoped, for example `core/test`
- `Application`: string id
- `Module`, `Middleware`, `Parcel`: package-scoped ids where package ownership matters

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

## Env

resolution order:

1. parcel
2. domain
3. subdomain
4. endpoint when active
5. application

Twig uses dotted lookups:

```twig
{{ env('colors.primary') }}
```

The grouped env structure is also emitted as `window.env`.

## Middleware And Modules

PSR-15 middleware services are auto-tagged as `symfonicat.middleware`. Runtime rendering always runs middleware attached to the active domain and subdomain, plus endpoint middleware when an endpoint render wins, and passes them through the PSR HTTP bridge.

Module controllers extend `AbstractModuleController` and only run when their module is attached to the active domain, subdomain, or endpoint. Module requests Brotli-compress their JSON body in `assets/app/module.js` with a vendored browser Brotli codec, send the request token back in `X-Symfonicat-Module-Context` plus `X-CSRF-Token` when request context is available, and the server validates that signed token before restoring endpoint scope for backend module checks. On `/m` requests with Brotli JSON bodies, `SymfonicatModuleSubscriber` sets `module_json` from `symfonicat_json_decode()`.

## Admin

Admin is guarded by the repo-root `symfonicat.lock`.

Main admin areas:

- `/admin/a` applications
- `/admin/p` parcels
- `/admin/d` domains
- `/admin/e` endpoints
- `/admin/env` env
- `/admin/m` middleware
- `/admin/s` subdomains and schema sync
- `/admin/y/*` YAML dump/load

Forms support parcel attachments, repeatable middleware, modules, scoped env values, and catch flags. Subdomain forms expose only the plain id.

## YAML And Commands

Runtime reads the `symfonicat` block from `config/packages/symfonicat.yaml`. The database tables are for unlocked admin editing and dumping YAML.

Admin CRUD and schema sync actions automatically refresh `config/packages/symfonicat.yaml` after successful writes.

Commands:

- `symfonicat:schema:update`
- `symfonicat:scriptling:copy`
- `symfonicat:scriptling:bash`
- `symfonicat:load`
- `symfonicat:dump`
- `symfonicat:purge`
- `symfonicat:admin:create`
- `symfonicat:admin:delete`
- `symfonicat:data:webpack`
- `symfonicat:data:dns`
- `symfonicat:public-suffix:refresh`

`symfonicat:schema:update` synchronizes the Doctrine schema and Symfonicat package rows for parcels, middleware, endpoints, modules, domains, applications, and subdomains.

## Scriptling

The Docker container uses `symfonicat:scriptling:copy` and `symfonicat:scriptling:bash` to gather FrankenPHP extensions, initialize them with `frankenphp extension-init`, and compile a custom FrankenPHP binary with `xcaddy`. The final runtime image is based on that builder output so PHP workers and FrankenPHP use the same compiled extension set.

Installed Symfonicat packages can ship FrankenPHP Scriptling extensions under `extensions/{name}`. Docker keeps `vendor/{vendor}/{package}/extensions/**` in the build context, overlays those files after `composer install`, and then includes every discovered extension in the `xcaddy` build. The analytics package includes `extensions/lowercase`, which exports `scriptling_analytics_lowercase(string $value): string`.

The root `extensions/brotli_precompress` module precompresses `public/build/*.{js,json,css,wasm,woff2}` files at startup and serves Brotli responses directly for matching build assets.

## PHPUnit

`docker exec php ./bin/phpunit`

## Picture of @dunglas at the zoo

included.
