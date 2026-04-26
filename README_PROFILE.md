# Symfonicat

`symfonicat/core` is the full Symfonicat Symfony application. Public routing, admin CRUD, module runtime, application shells, Electron packaging, and Docker/FrankenPHP all live in this repository.

The Docker `php` service installs Composer dependencies, bootstraps the schema, runs `npm install`, and runs `npm run build` automatically. The image also installs `n` and runs `n latest`, so the container uses a current Node runtime for webpack and Electron packaging. Bootstrap seeds the default domains, `project1`, the `test` application, grouped `colors.primary` env values, and an `Example Test` Electron row for `example.com`.

## Runtime

- `Domain` resolves the base host.
- `Project` resolves the project subdomain.
- `Application` resolves from routing rules.
- `/` renders the domain shell.
- `/{path}` renders the project shell when a project subdomain is active.
- application shells can also bind directly to Symfony routes through `RoutingRule.applicationType=route`.

`path_application('test')`, `path_application(application)`, and `path('symfonicat_application', {id: 'test'})` all generate the public application URL from the matching routing rule.

## Env

Env is grouped by `EnvParent` and read through `EnvService`.

Twig lookups use dotted keys such as:

```twig
{{ env('colors.primary') }}
```

The same grouped structure is emitted into `window.env`.

## Assets

Webpack entry discovery comes from `symfonicat:data:webpack`, with database-backed rows and filesystem fallback under:

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

Each row points at one domain, project, or application and can upload a favicon to:

```text
public/electron/favicon/{type}/{targetId}.png
```

The Electron admin list trims the leading `electron/favicon/` prefix when it displays that stored path.

Build outputs with:

```bash
docker exec php bin/console symfonicat:electron:build
docker exec php bin/console symfonicat:electron:build <name>
```

The command renders `templates/electron/{type}/main.twig.js` or `templates/electron/{type}/overrides/{targetId}.twig.js`, writes `electron/{type}/{targetId}/app.js`, writes a local package manifest with a fixed Electron version derived from the root package, and runs `electron-builder` into `electron/{type}/{targetId}/build`. Those `build` directories are generated outputs and are ignored by Git.

## Sync

`symfonicat:schema:update` synchronizes:

- modules from `assets/modules/{id}/package.json`
- applications from `assets/applications/{id}`
- projects from `assets/projects/{id}`

Run it interactively when confirmation may be required:

```bash
docker exec -it php bin/console symfonicat:schema:update
```

For full install, Docker, routing, module runtime, and admin details, see [README.md](https://github.com/symfonicat/core/blob/main/README.md).
