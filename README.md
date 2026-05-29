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
docker compose up -d --build
docker exec -it php bin/console symfonicat:schema:update
docker exec php bin/console symfonicat:load
docker exec -it php bin/console symfonicat:admin:create <email>
touch symfonicat.lock
```

The admin area is disabled until `symfonicat.lock` exists in the repo root.
The `npm` Compose service runs after `php` is healthy and generates `public/build` with PHP available for webpack data discovery.

## Runtime

The runtime subscriber resolves the active `Domain`, `Subdomain`, and matching `Endpoint` before Symfony routing. Runtime catch-all routes have low priority, so normal Symfony routes still win when they match.

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

Application build templates live under `templates/application/main.js.twig`, with optional per-application overrides at `templates/application/overrides/{application-id}.js.twig`. The build command generates a buildable Electron skeleton in `application/{application.id}/` with `main.js`, `package.json`, `README.md`.

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

Module requests Brotli-compress their JSON body in `assets/app/module.js` with a vendored browser Brotli codec, send the request token back in `X-Symfonicat-Module-Context` plus `X-CSRF-Token` when request context is available, and the server validates that signed token before restoring endpoint scope for backend module checks. On `/m` requests with Brotli JSON bodies, `SymfonicatModuleSubscriber` sets `module_json` from `symfonicat_json_decode()`.

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

- `assets/domain/`
- `assets/subdomain/`
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

Admin CRUD and schema sync actions automatically refresh `config/packages/symfonicat.yaml` after successful writes.

`composer install` runs `symfonicat:purge` so deployments start with a clean `symfonicat_*` schema; runtime still reads `config/packages/symfonicat.yaml`.

## Admin

Admin routes:

- `/admin/a` applications
- `/admin/p` parcels
- `/admin/d` domains
- `/admin/e` endpoints
- `/admin/env` env
- `/admin/m` middleware
- `/admin/s` subdomains and schema sync action
- `/admin/y/*` YAML tools

Forms support parcel attachments, repeatable middleware, modules, scoped env values, and catch flags where the entity supports them.

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

## Scriptling

The Docker container uses `symfonicat:scriptling:copy` and `symfonicat:scriptling:bash` to gather FrankenPHP extensions, initialize them with `frankenphp extension-init`, and compile a custom FrankenPHP binary with `xcaddy`. The separate `npm` Compose service runs `npm ci` and `npm run build` after `php` is healthy so `public/build` is generated with PHP available for webpack discovery. The final runtime image is based on the builder output so PHP workers and FrankenPHP use the same compiled extension set.

Installed Symfonicat packages can ship FrankenPHP Scriptling extensions under `extensions/{name}`. Docker keeps `vendor/{vendor}/{package}/extensions/**` in the build context, overlays those files after `composer install`, and then includes every discovered extension in the `xcaddy` build. The analytics package includes `extensions/lowercase`, an example, which exports `scriptling_analytics_lowercase(string $value): string`.

The root `extensions/brotli_precompress` module precompresses `public/build/*.{js,json,css,wasm,woff2}` files at startup and serves Brotli responses directly for matching build assets.

## PHPUnit

`docker exec php ./bin/phpunit`

## Picture of @dunglas at the zoo

included.
