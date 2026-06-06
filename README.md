## Symfonicat

Symfonicat is a Symfony 8 multi-tenant frontend runtime. It resolves public requests to domains, subdomains, and endpoints, renders the matching parcel-backed template, and exposes modules, middleware, env data, and build-application context where present.

Symfonicat supports the ability for Composer packages to ship with PHP extensions and Go modules. Relative to the project root, and composer packages, modules/extensions are located here:

- PHP extensions: `native/ext/**`, `core/native/ext/**`, and `vendor/**/**/native/ext/**`
- Go modules: `native/go/**`, `core/native/go/**`, and `vendor/**/**/native/go/**`

When the Docker container builds, it discovers those directories and compiles the PHP extensions and Go modules into the FrankenPHP/Caddy setup so that they are available in your application. The repo root is bind-mounted into `/symfonicat`, so `docker exec php composer require ...` writes back to `vendor/`, `composer.json`, `composer.lock`, and `symfony.lock` in place. Set `SYMFONICAT_NATIVE_WATCH=1` on the `php` container to watch the native source trees and rebuild the affected extensions plus FrankenPHP/Caddy when those files change.
The Brotli Go module lives at `core/native/go/brotli`; its Caddy directives stay `brotli_precompress` for `/build/*` and `brotli_decompress` for `/m*`, and module requests arrive at PHP after the catch-all `handle` runs a nested `route /m* { brotli_decompress php_server }` block before the fallback `php_server`. There is no PHP-exported `brotli_decompress()` function.
The module route scanner now lives in the `core/native/ext/module_routes` PHP extension, which returns the package and scan target arrays used by `config/module_routes.php`.
`config/polyfill.php` provides PHP fallbacks for `module_routes_collect_packages()` and `module_routes_collect_scan_targets()` when the native extension is not loaded. `src/Kernel.php` requires that polyfill before container compilation, and `config/module_routes.php` requires it again before building the route collection. `config/module_routes.php` still consumes the returned arrays directly and skips malformed scan targets defensively before it walks the filesystem.

Edit `/etc/hosts` for local public routing:

```text
127.0.0.1 example.com
127.0.0.1 subdomain1.example.com
```

```bash
git clone https://github.com/symfonicat/core symfonicat
cd symfonicat
docker compose up -d --build
docker exec -it php bin/console symfonicat:schema:update
docker exec php bin/console symfonicat:load
docker exec -it php bin/console symfonicat:admin:create <email>
touch symfonicat.lock # enables /core
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

Domain and affix resolution now match the request host against configured `symfonicat_domain` rows in `config/packages/symfonicat.yaml` and parse the subdomain labels manually from that match. The old public-suffix parser is no longer part of the runtime host path.
The `jeremykendall/php-domain-parser` dependency, `bin/public-suffix`, `public_suffix_list.dat`, and `symfonicat:public-suffix:refresh` command were removed.

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

Application build templates live under `templates/application/main.js.twig`, with optional per-application overrides at `templates/application/overrides/{application-id}.js.twig`. The build command generates a buildable Electron skeleton in `applications/{application.id}/` with `main.js`, `package.json`, `README.md`.

## Middleware

Middleware is selected from the active runtime scope:

- domain middleware always runs when a domain is active
- subdomain middleware always runs when a subdomain is active
- endpoint middleware runs for endpoint renders

Middleware services implement PSR-15 `Psr\Http\Server\MiddlewareInterface` and are tagged automatically as `symfonicat.middleware`.

## Modules

Modules can be attached to domains, subdomains, or endpoints.

Backend module controllers should extend `Symfonicat\Controller\AbstractModuleController`. They only execute when the module is attached to the active domain, subdomain, or endpoint context.

`AbstractModuleController` exposes `json()` for `JsonResponse` and `html()` for `Response`; both run through the same module guard as the lower-level `module()` helper, which remains available for callers that want to pass an existing response object through the guard. `json()` delegates to Symfony's built-in controller JSON handling so serializer context behaves the same way as a normal `AbstractController` response.

Module routes are declared with `#[ModuleRoute]` on the controller class and `#[Module]` on the action method. `ModuleRoute` defines the `/m/<package>` base, `Module` only carries `permission` and defaults it to `PUBLIC_ACCESS`, and every module route is always `POST`. The module loader reads the scanned package's `composer.json` name, turns `symfonicat/core` into `symfonicat_core`, and generates `POST /m/symfonicat/core/{method}` with the route name `symfonicat_module_symfonicat_core_{method}`. If no package name is available, the route base falls back to `symfonicat/core`.

The root Composer autoload maps `App\\Module\\` to `src/Module/`, so controllers under `src/Module/` are discovered as module routes when they use the `App\Module\` namespace. Installed Symfonicat packages can expose their own `Module` subtree through PSR-4 autoloading, and those classes continue to use the `Symfonicat\Module\` namespace.

Frontend module code posts to full package routes:

```javascript
const mod = 'symfonicat/analytics/main'
      mod.log('module active!')

// posts { test: true } to /m/symfonicat/analytics/main
const result = await mod.json({ test: true })
      mod.log('/m/symfonicat/analytics/main result:', result)
```

Module requests Brotli-compress their JSON body in `assets/app/module.js` with a vendored browser Brotli codec, send the request token back in `X-Symfonicat-Module-Context` plus `X-CSRF-Token` when request context is available, and `Symfonicat\Service\ModuleRequestContextStore` signs and verifies that token payload in PHP before restoring endpoint scope for backend module checks. Caddy matches `/m` module requests with a dedicated regex matcher and runs `brotli_decompress` before the fallback `php_server`, so module bodies are decompressed before `SymfonicatModuleSubscriber` decodes JSON. Invalid module JSON still fails with `JsonException` instead of silently coercing to an empty array.
Module action parameter services discovered from `#[ModuleRoute]` and `#[Module]` classes are kept public during container compilation, so vendor package module controllers can autowire their action arguments without hand-editing service definitions.

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

- `assets/application/`
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

## AWS

The repo has direct AWS integrations for image distribution, deployment, DNS, and certificate issuance. `bin/ecr` pushes the container image to private ECR, `bin/ecs` creates or updates the ECS service and task definition, `bin/route53` creates the hosted zone and DNS records for the active ECS task, and `bin/cert` requests an ACM certificate, exports it for the container runtime, and refreshes ECS and Route 53 so HTTPS can come up end to end.

### `bin/ecr`

Pushes the local Docker build to private ECR. Fill in:

- `AWS_ECR_REGION`: the AWS region that owns the registry, such as `us-west-2`
- `AWS_ECR_ACCOUNT_ID`: the AWS account ID that owns the registry
- `AWS_ECR_REPOSITORY`: the ECR repository name, usually `symfonicat/core`

The helper uses these defaults unless you override them:

- `AWS_ECR_TAG=latest`
- `AWS_ECR_CONTEXT=.`
- `AWS_ECR_DOCKERFILE=Dockerfile`
- `AWS_ECR_BUILD_TARGET=runtime`
- `AWS_ECR_LOCAL_IMAGE=symfonicat-core-php:local`

### `bin/ecs`

Creates or updates the ECS cluster, task definition, and service. Fill in:

- `AWS_ECS_REGION`: the AWS region for ECS, such as `us-west-2`
- `AWS_ECS_CLUSTER_NAME`: the ECS cluster name, usually `symfonicat`
- `AWS_ECS_SERVICE_NAME`: the ECS service name, usually `symfonicat`
- `AWS_ECS_CONTAINER_NAME`: the container name inside the task, usually `symfonicat`
- `AWS_ECS_IMAGE_URI`: the full ECR image URI, such as `764416828667.dkr.ecr.us-west-2.amazonaws.com/symfonicat/core:latest`

The helper defaults the first-run ECS shape unless you override it:

- `AWS_ECS_TASK_FAMILY=symfonicat`
- `AWS_ECS_LAUNCH_TYPE=FARGATE`
- `AWS_ECS_CPU=512`
- `AWS_ECS_MEMORY=1024`
- `AWS_ECS_CONTAINER_PORT=443`
- `AWS_ECS_DESIRED_COUNT=1`
- `AWS_ECS_ASSIGN_PUBLIC_IP=ENABLED`
- `AWS_ECS_SUBNETS_JSON` can be left blank to auto-discover default VPC subnets
- `AWS_ECS_SECURITY_GROUPS_JSON` can be left blank to auto-create the service security group
- `AWS_ECS_TARGET_GROUP_ARN` can be left blank unless you already have a load balancer
- `AWS_ECS_EXECUTION_ROLE_ARN` can be left blank to create or reuse `ecsTaskExecutionRole`
- `AWS_ECS_TASK_ROLE_ARN` can be left blank unless the app code needs AWS API access
- `AWS_ECS_WAIT_FOR_STABLE=1`

`bin/ecs` also loads `/.env.certificate.local` when present, so `AWS_ECS_TLS_FULLCHAIN_B64` and `AWS_ECS_TLS_PRIVATE_KEY_B64` can be carried into the task definition after `bin/cert` exports them.

### `bin/route53`

Creates the hosted zone for a domain and writes apex plus wildcard DNS records to the current ECS task IP. Fill in:

- `AWS_ECS_REGION`: the ECS region to inspect
- `AWS_ECS_CLUSTER_NAME`: the ECS cluster name
- `AWS_ECS_SERVICE_NAME`: the ECS service name
- `AWS_ECS_CONTAINER_NAME`: the container name to inspect

Optional DNS settings:

- `AWS_ROUTE53_REGION`: the Route 53 region, defaulting to the ECS region
- `AWS_ROUTE53_RECORDS_JSON`: extra DNS records to publish in addition to the apex and wildcard `A` records

The script takes one domain argument, creates or reuses the hosted zone, prints the nameservers to delegate, and upserts `@` and `*` `A` records to the public IPv4 of the running ECS task.

### `bin/cert`

Requests an exportable ACM certificate for a domain and its wildcard, publishes the validation records, exports the cert for Caddy, and then redeploys ECS. Fill in:

- `AWS_ECS_REGION`: the ECS region to inspect
- `AWS_ECS_CLUSTER_NAME`: the ECS cluster name
- `AWS_ECS_SERVICE_NAME`: the ECS service name
- `AWS_ECS_CONTAINER_NAME`: the container name inside the task
- `AWS_ECS_IMAGE_URI`: the ECR image URI to deploy with the refreshed cert material

Optional cert settings:

- `AWS_CERT_REGION`: the ACM region, defaulting to the ECS region, then the Route 53 region, then `us-east-1`

`bin/cert` writes `/.env.certificate.local` with `AWS_ECS_TLS_FULLCHAIN_B64` and `AWS_ECS_TLS_PRIVATE_KEY_B64`, then runs `bin/ecs` and `bin/route53` so the new cert is applied and DNS is refreshed.

## Native Build

Use `bin/native-build` inside the container to configure PHP extensions from `native/ext/**`, `core/native/ext/**`, and `vendor/**/**/native/ext/**`, then rebuild FrankenPHP from `native/go/**`, `core/native/go/**`, and `vendor/**/**/native/go/**`. Use `SYMFONICAT_NATIVE_WATCH=1` on the `php` container to restart FrankenPHP after watched native source changes. The watch loop writes `/run/symfonicat/native-watch.sentinel`, prefers `inotifywait`, and also verifies file-content snapshots so it still catches edits if inotify misses them.

Native extension work uses `native/ext/<ext>/<ext>.c` plus `config.m4`, and `./glossary/*.json` captures function-level metadata for the current PHP source tree, including `php_function`, `zend_function`, `source_file`, `extension`, `parameters`, `includes`, `body`, `semantic`, `control_flow`, `dependencies`, `specialization_candidates`, `strategies`, `memory_model`, `avoid`, `complexity`, `purpose`, `php_version`, and `related`.

## PHPUnit

`docker exec -it php ./bin/phpunit`

## Picture of @dunglas at the zoo

included.
