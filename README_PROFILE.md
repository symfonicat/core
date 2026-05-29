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

Module routes use `#[ModuleRoute]` on the controller class and `#[Module]` on the action method. The module loader scans the root package and installed `extra.symfonicat: true` vendor packages, reads the package name from each `composer.json`, and generates `POST /m/{package}/{method}` with route names in the `symfonicat_module_{packageSlug}_{method}` pattern.

The root Composer autoload maps `App\\Module\\` to `src/Module/`, so controllers under `src/Module/` are discovered as module routes when they use the `App\Module\` namespace. Installed Symfonicat packages can expose their own `Module` subtree through PSR-4 autoloading and will be scanned too, using their own `Symfonicat\Module\` classes.

## Admin

Admin is guarded by the repo-root `symfonicat.lock`.
`config/routes.yaml` keeps the public/admin route resources and imports `config/module_routes.php` once. That PHP route file scans the root package and installed `extra.symfonicat: true` vendor packages, uses `symfonicat_json_decode()` to read their `composer.json` metadata, reads `#[ModuleRoute]` for the base `/m/{package}` path, and generates POST-only route names like `symfonicat_module_symfonicat_core_test` plus the controller target for `App\Module\TestModule::test`. `config/functions.php` provides a Composer autoloaded PHP fallback for `symfonicat_json_decode()` so console route warmup works even when the native extension is not present. `config/services.yaml` registers the local `App\Module\` namespace so everything under `src/Module/` gets a container service without a bulk `src/Module/` service scan.
Compose bind-mounts `./vendor` into `/symfonicat/vendor`, so installed packages are visible on the host and inside the container at the same path.
If `vendor/autoload_runtime.php` is missing at container startup, `docker-entrypoint.sh` runs `composer install --no-interaction --prefer-dist --no-progress --no-scripts` into the bind-mounted `./vendor` directory before FrankenPHP starts.

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

## AWS

The repo integrates with AWS for image pushes, service deployment, DNS, and certificates. `bin/ecr` pushes the container image to private ECR, `bin/ecs` creates or updates the ECS cluster and service, `bin/route53` creates hosted zones and DNS records for the running ECS task, and `bin/cert` requests and exports ACM certificates so the container runtime can serve HTTPS with refreshed DNS.

### `bin/ecr`

Pushes the local Docker image to private ECR. Fill in:

- `AWS_ECR_REGION`: the AWS region that owns the registry, such as `us-west-2`
- `AWS_ECR_ACCOUNT_ID`: the AWS account ID that owns the registry
- `AWS_ECR_REPOSITORY`: the ECR repository name, usually `symfonicat/core`

The helper uses these defaults unless overridden:

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

The helper defaults the first-run ECS shape unless overridden:

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

`bin/ecs` also loads `/.env.certificate.local` when present so `AWS_ECS_TLS_FULLCHAIN_B64` and `AWS_ECS_TLS_PRIVATE_KEY_B64` are preserved in the task definition after `bin/cert` exports them.

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

Requests an exportable ACM certificate for a domain and wildcard, publishes validation records, exports the cert for Caddy, and redeploys ECS. Fill in:

- `AWS_ECS_REGION`: the ECS region to inspect
- `AWS_ECS_CLUSTER_NAME`: the ECS cluster name
- `AWS_ECS_SERVICE_NAME`: the ECS service name
- `AWS_ECS_CONTAINER_NAME`: the container name inside the task
- `AWS_ECS_IMAGE_URI`: the ECR image URI to deploy with the refreshed cert material

Optional cert settings:

- `AWS_CERT_REGION`: the ACM region, defaulting to the ECS region, then the Route 53 region, then `us-east-1`

`bin/cert` writes `/.env.certificate.local` with `AWS_ECS_TLS_FULLCHAIN_B64` and `AWS_ECS_TLS_PRIVATE_KEY_B64`, then runs `bin/ecs` and `bin/route53` so the new cert is applied and DNS is refreshed.

## Scriptling

The Docker container uses `symfonicat:scriptling:copy` and `symfonicat:scriptling:bash` to gather FrankenPHP extensions, initialize them with `frankenphp extension-init`, and compile a custom FrankenPHP binary with `xcaddy`. The final runtime image is based on that builder output so PHP workers and FrankenPHP use the same compiled extension set.

Installed Symfonicat packages can ship FrankenPHP Scriptling extensions under `extensions/{name}`. Docker keeps `vendor/{vendor}/{package}/extensions/**` in the build context, overlays those files after `composer install`, and then includes every discovered extension in the `xcaddy` build. The analytics package includes `extensions/lowercase`, which exports `scriptling_analytics_lowercase(string $value): string`.

The root `extensions/brotli_precompress` module precompresses `public/build/*.{js,json,css,wasm,woff2}` files at startup and serves Brotli responses directly for matching build assets.

## PHPUnit

`docker exec php ./bin/phpunit`

## Picture of @dunglas at the zoo

included.
