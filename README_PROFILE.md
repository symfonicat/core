# Symfonicat

`symfonicat/core` is the full Symfonicat Symfony application. Public routing, admin CRUD, module runtime, application shells, Electron packaging, and Docker/FrankenPHP all live in this repository.

The Docker `php` service installs Composer dependencies, bootstraps the schema, runs `npm install`, and runs `npm run build` automatically. The image also installs `n` and runs `n latest`, so the container uses a current Node runtime for webpack and Electron packaging. Bootstrap now seeds package-prefixed defaults (for example `core/example.com`, `core/localhost`, `core/project1`, `core/test`) and synchronizes installed `symfonicat/*` package entries such as the `analytics/*` module before applying local seeding.

## Runtime

- `Domain` resolves the base host.
- `Project` resolves the project subdomain.
- `Application` resolves from routing rules.
- `/` renders the domain shell.
- `/{path}` renders the project shell when a project subdomain is active.
- application shells can also bind directly to Symfony routes through `RoutingRule.applicationType=route`.

Package-owned Symfony route names are prefixed with `symfonicat_`.

`path_application('test')`, `path_application(application)`, and `path('symfonicat_application', {id: 'test'})` all generate the public application URL from the matching routing rule.

## Env

Env is grouped by `EnvParent` and read through `EnvService`.

Twig lookups use dotted keys such as:

```twig
{{ env('colors.primary') }}
```

The same grouped structure is emitted into `window.env`.

Runtime precedence is application, then domain, then project, then Electron for Electron requests only.

## Assets

Webpack entry discovery comes from `symfonicat:data:webpack`.
It scans the root `symfonicat/core` package plus installed `symfonicat/*` packages and resolves entry files from `assets/{type}/{id}` inside each package. Discovery uses package-prefixed ids when appropriate (for example `analytics/main` or `core/example.com`) so database rows and webpack entries align.

- `assets/applications/{id}`
- `assets/domains/{id}`
- `assets/projects/{id}`
- `assets/modules/{id}`

Public and admin assets use separate Stimulus stacks.

## Admin

Admin is isolated from any host user system and uses Symfony security plus TOTP MFA.

Admin surfaces:

- `/admin/a*` applications
- `/admin/d*` domains
- `/admin/e*` Electron rows
- `/admin/env*` env parents and env keys
- `/admin/p*` projects
- `/admin/r*` routing rules

Create or update an admin with:

```bash
docker exec -it php bin/console symfonicat:admin:create <username>
```

The command prompts for a hidden password.

## Electron

Electron rows are managed from `/admin/e`.
Electron deletes use a dedicated `/admin/e/{id}/delete` POST endpoint so row deletion does not conflict with edit submits.

Each row points at one domain, one `project + domain` pair, or one application and can upload a favicon to:

```text
public/electron/favicon/{type}/{targetId}.png
```

For project Electron rows, `targetId` is `projectId.domainId`.
That same `projectId.domainId` target id is used for both project override templates and generated `electron/project/{projectId}.{domainId}/` output folders.

Electron rows also own an `env` collection. Electron env values override the merged application/domain/project env stack, but only while the current request is running in Electron mode.

The Electron admin list trims the leading `electron/favicon/` prefix when it displays that stored path.

The Twig `electron` global is a boolean flag for Electron requests, and the base layout exposes that same state as `window.electron`.

Build outputs with:

```bash
docker exec php bin/console symfonicat:electron:build
docker exec php bin/console symfonicat:electron:build <name>
```

The command renders `templates/electron/{type}/main.twig.js` or `templates/electron/{type}/overrides/{targetId}.twig.js`, writes `electron/{type}/{targetId}/app.js`, writes a local package manifest with a fixed Electron version derived from the root package, and runs `electron-builder` into `electron/{type}/{targetId}/build`. For project Electron rows that means `templates/electron/project/overrides/{projectId}.{domainId}.twig.js` and `electron/project/{projectId}.{domainId}/...`. The generated Electron package points at the matching domain host, `project.domain` host, or application path and appends `?electron` to the start URL. Those `build` directories are generated outputs and are ignored by Git.

## Sync

`symfonicat:schema:update` synchronizes discovered package entries into database rows (modules, domains, applications, projects). Discovery emits package-prefixed ids when appropriate (for example `analytics/main` or `core/example.com`) and `symfonicat:schema:update` will create, update, or delete rows to match installed `symfonicat/*` package assets.

Run it interactively when confirmation may be required:

```bash
docker exec -it php bin/console symfonicat:schema:update
```

For full install, Docker, routing, module runtime, and admin details, see [README.md](https://github.com/symfonicat/core/blob/main/README.md).
