# Symfonicat Profile

`symfonicat/core` is the Symfony 8 multi-tenant frontend runtime in this repo. Public requests resolve to domains, subdomains, and endpoints, then render the matching parcel-backed template while exposing modules, middleware, env data, and application build context where present.

Symfonicat supports Composer packages that ship PHP extensions and Go modules. PHP extensions live under `<package root>/ext/**/*.c`, Go modules live under `<package root>/extensions/**/*.go`, and the Docker build compiles both into the FrankenPHP/Caddy setup. The Docker build also logs the extension staging step to `/tmp/ext-build.log`.

The admin area is disabled until `symfonicat.lock` exists in the repo root.

## Runtime

Public routes:

- `/`
- `/{path}`

The runtime subscriber resolves the active `Domain`, `Subdomain`, and matching `Endpoint` before Symfony routing. Catch-all routes have low priority, so normal Symfony routes still win when they match.

Template resolution order:

- `templates/{domain,subdomain,endpoint}/overrides/{id}.html.twig`
- `templates/{domain,subdomain,endpoint}/main.html.twig`

## Applications

`Application` is the app-scaffold target for this branch. It carries the role that the old separate Electron type carried: select a URL/runtime context from the application row's domain, subdomain, and endpoint fields, then generate an Electron skeleton from that selected target.

Application targets resolve in this order:

1. endpoint
2. subdomain
3. domain

## Middleware And Modules

PSR-15 middleware services are auto-tagged as `symfonicat.middleware`. Runtime rendering runs middleware attached to the active domain and subdomain, plus endpoint middleware when an endpoint render wins.

Module controllers extend `AbstractModuleController` and only run when their module is attached to the active domain, subdomain, or endpoint. Module requests Brotli-compress their JSON body, send the request token back in `X-Symfonicat-Module-Context` plus `X-CSRF-Token` when request context is available, and the server validates that signed token before restoring endpoint scope for backend module checks.

## Env

Environment resolution order:

1. parcel
2. domain
3. subdomain
4. endpoint when active
5. application

The grouped env structure is also exposed through Twig and `window.env`.

## Assets

Webpack and schema sync discover entries in the root package and installed Composer packages that opt in with `extra.symfonicat: true`.

Entry families:

- `assets/domain/`
- `assets/subdomain/`
- `assets/application/`
- `assets/module/`
- `assets/parcel/`

The public `symfonicat_asset(path)` helper resolves shell-specific assets by checking subdomain, then domain, then default.

## Configuration

Packages opt into Symfonicat discovery by setting `extra.symfonicat: true` in their `composer.json`.

Admin CRUD and schema sync actions refresh `config/packages/symfonicat.yaml` after successful writes.

`config/packages/messenger.yaml` uses numeric `stop_worker_on_signals` values for SIGTERM and SIGINT so Symfony console boot does not require `pcntl` signal constants during the Docker build.

The Dockerfile keeps the manifest-only `composer install --no-interaction --prefer-dist --no-progress --no-scripts` and `composer dump-autoload --no-interaction` lines commented out, so the build relies on the committed `vendor/` tree when the ext build shell block runs.

The Docker build copies `native/ext` into `/tmp/native/ext`, tees the ext layer to `/tmp/ext-build.log`, discovers package extension roots with `find vendor -path 'vendor/*/*/ext/*/config.m4' -printf '%h '`, creates `/usr/src/php/ext` before mirroring the staging tree into it, and runs `docker-php-ext-install` on the combined staged extension names in the same layer while logging the manifest to `/tmp/ext-build.log`.

The analytics package native extension under `vendor/symfonicat/analytics/ext/symfonicat_analytics` builds as `symfonicat_analytics`, so the directory name returned by `symfonicat:ext:list` now matches the PHP extension name.

The bundled PHP native extension at `native/ext/native_remove_string/native_remove_string.c` declares arginfo for `native_remove_string(string $value, string $needle): string`, so loading the module no longer emits `Missing arginfo for native_remove_string()`.

`NativeProxyCompilerPass` registers `Native\NativeProxy\Service\TextToolsNativeProxy` as the implementation for `App\Service\TextToolsInterface` and injects the original `App\Service\TextTools` as the fallback service.

## Scriptling

The Docker build compiles FrankenPHP extensions and the custom FrankenPHP binary, and the final runtime image reuses that builder output.
