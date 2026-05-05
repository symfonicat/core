# AGENTS.md

These instructions apply to the main app repo at `/home/t/www/symfonicat`.

## App goal

- This repository is the root of the only Packagist package `symfonicat/core`.
- Treat it as the full install target, not as a reusable bundle plus starter split.
- Keep the sibling directories `../src_legacy` and `../src_strip` in place while iterating. They remain the sources of truth.
- `src_legacy` has priority when `src_legacy` and `src_strip` disagree.
- The implementation lives in this repo, especially `./src`, `./assets`, `./templates`, `./electron`, and `./webpack.symfonicat.js`.

## Functional scope

- Achieve feature parity with the legacy app where practical under modern Symfony 8 conventions.
- Preserve at least the stripped implementation surface.
- Ignore:
  - `manifest.json`
  - `migrations`
  - anything Draft-related
  - anything coming-soon-related
  - broadcast templates/features
  - S3 functionality
  - OAuth and the public user/account system

## Required features

- Preserve and own the public frontend routing layer:
  - `/`
  - `/{path}`
- Preserve the legacy module runtime, not just module CRUD.
- Preserve the admin CRUD URLs as closely as possible:
  - `/admin/d/*`
  - `/admin/p/*`
  - `/admin/e/*`
  - `/admin/m/*`
  - `/admin/r/*`
- Preserve the Electron-related runtime commands and data interfaces needed by the starter app.
- Preserve the webpack data interface driven by `symfonicat:data:webpack`.

## Symfony app shape

- Root PHP namespace: `Symfonicat\`
- Bundle class: `SymfonicatBundle`
- Configuration alias: `symfonicat`
- Keep the internal bundle structure, but this repo is the app root.
- Do not maintain a separate starter repo or a custom Flex recipe.
- Follow normal Symfony application conventions at the repo root.

## Data model

- Bundle-owned entities/tables:
  - `Domain` -> `symfonicat_domain`
  - `Project` -> `symfonicat_project`
  - `Env` -> `symfonicat_env`
  - `DomainEnv` -> `symfonicat_domain_env`
  - `ProjectEnv` -> `symfonicat_project_env`
  - `Module` -> `symfonicat_module`
  - `RoutingRule` -> `symfonicat_routing_rule`
  - `Admin` -> `symfonicat_admin`
- Prefix join tables as well.
- Assume a fresh schema. Ship Doctrine entities and mappings, not migrations.
- `Env.id` is a string primary key.
- `Domain` and `Project` each own an `env` collection of value rows that reference `Env`.
- Runtime env lookup is keyed by `Env.id`.
- Runtime precedence is:
  - Domain env value as the base layer
  - Project env value overrides the domain value when both exist for the same `Env.id`
- `EnvService` is the canonical runtime accessor.
- Twig `env()` should resolve through `EnvService`.

## Admin auth

- `/admin` is protected by a Symfonicat-owned auth system.
- Use a separate `Admin` entity and separate admin tables.
- Keep it isolated from the host app user system.
- Use HTTP basic for admin auth.
- Preserve `/admin/login` and `/admin/logout` URLs if needed by routing layout, but HTTP basic is the real auth flow.
- Provide `bin/console` commands to create and delete admins.

## Assets and templates

- Asset source lives at `assets`
- Build target remains `public/build`
- `webpack.symfonicat.js` defines the package entrypoints and is the source of truth for how frontend bundles are wired.
- `symfonicat:data:webpack` is the canonical webpack data source for domain/project/module entries, discovering package-owned entries from the root `symfonicat/core` package plus installed `symfonicat/*` packages.
- Public runtime/frontend work should use the public asset stack:
  - `assets/symfonicat.js`
  - `assets/stimulus.js`
  - `assets/controllers.json`
  - `assets/controllers/`
- Admin runtime/frontend work must use the admin asset stack instead of the public one:
  - `assets/symfonicat_admin.js`
  - `assets/stimulus_admin.js`
  - `assets/controllers_admin.json`
  - `assets/controllers_admin/`
- If you generate JavaScript or Stimulus functionality for `/admin`, put it on the admin asset stack and do not add it to the public `symfonicat`/`controllers` pipeline.
- Keep admin templates package-owned under this repo.
- Keep the app-level layout in `templates/base.html.twig` in this repo.
- `templates/admin/base.html.twig` uses the admin entrypoint, so admin-specific assets should target `symfonicat_admin`.
- Mandatory template areas:
  - `env`
  - `domain`
  - `module`
  - `project`
  - `routing_rule`

## Electron

- Keep Electron talking to the live Symfony server over HTTP.
- Keep `symfonicat:*` and `electron:*` console command coverage where needed for the starter app.

## public_suffix_list.dat

- Provide a refresh command for `public_suffix_list.dat`.

## Docker

- Docker/FrankenPHP files belong in this repo.

## System overview

1. Symfonicat is a full Symfony 8 application distributed as the `symfonicat/core` package. Treat this repository as the install target and runtime application, not as a reusable bundle with a separate starter project.

2. The public application surface is owned by this repo. The canonical public routes are `/`, `/{path}`, and the internal `/application/{id}/{path}` application entry route, with runtime resolution deciding whether the request renders a domain, project, or application shell.

3. Public runtime resolution is layered. `DomainService` resolves the base host, `ProjectService` resolves the first subdomain when one is present, `RoutingRuleSubscriber` applies configured routing rules, and `ApplicationService` loads the final application shell when a rule or route points at one.

4. Routing rules are database-owned runtime behavior, not just admin metadata. The supported rule types are `domain`, `project`, `application`, `redirect`, and `route`; changes to these rules can alter which shell renders, whether a request redirects, or whether a named Symfony route takes over.

5. Symfonicat ids are vendor-scoped in storage and clean in most runtime presentation. `Domain`, `Project`, `Application`, `Module`, and `Electron` rows can expose `project1` while storing or looking up `core/project1`; use full ids for persistence and admin route parameters, and clean ids for public URLs and templates when that is the established pattern.

6. The root package is treated as the special `core` vendor. Installed packages under configured vendors, such as `symfonicat/analytics`, are discovered with their Composer vendor and are represented by ids like `symfonicat/analytics/main`.

7. Package discovery is driven by `config/packages/symfonicat.yaml`. The configured vendor list feeds package service imports, package controller route imports, schema sync, webpack fallback discovery, and package-owned asset discovery.

8. Admin YAML lives in the `symfonicat.admin` section of `config/packages/symfonicat.yaml`. `symfonicat:dump` writes Symfonicat-owned database rows there while excluding `symfonicat_admin`, and `symfonicat:load` restores those rows without touching administrator accounts.

9. Schema synchronization is handled by `symfonicat:schema:update`. That command first synchronizes the Doctrine schema and then synchronizes package-provided modules, domains, applications, and projects; non-interactive runs create missing package rows automatically, while stale module removal with references remains interactive.

10. `symfonicat:bootstrap` is stale and must not be reintroduced. Docker entrypoints, Composer scripts, documentation, tests, and operational notes should use `symfonicat:schema:update` plus `symfonicat:load` for fresh installs and boot-time synchronization.

11. The admin area is isolated from any host app user system. It uses Symfonicat-owned `Admin` rows, separate tables, admin create/delete console commands, and the `/admin` route family for CRUD surfaces.

12. Admin URLs should stay close to the legacy shape. Applications use `/admin/a*`, domains use `/admin/d*`, Electron rows use `/admin/e*`, env uses `/admin/env*`, projects use `/admin/p*`, routing rules use `/admin/r*`, and YAML dump/load uses `/admin/y/*`.

13. Env resolution is a runtime feature and should go through `EnvService`. Twig `env()` lookups use dotted keys, and frontend runtime data is emitted into `window.env`; domain values form the base layer and project values override domain values for the same `Env.id`.

14. Electron is part of the runtime surface. Electron rows have vendor-scoped ids, a target type of domain, project, or application, optional favicon handling, scoped env values, and build commands that render Symfony/Twig-backed Electron entry files while the app keeps talking to the live Symfony server over HTTP.

15. Backend module controllers live in installed packages and are exposed through full module routes such as `/m/symfonicat/analytics/main`. Module controllers should extend `Symfonicat\Controller\AbstractModuleController` so requests only run when the active domain, project, or application has that module attached.

16. Webpack entry discovery is driven by `symfonicat:data:webpack` and `webpack.symfonicat.js`. It scans the root package and configured vendor packages for `assets/applications/{id}`, `assets/domains/{id}`, `assets/projects/{id}`, and `assets/modules/{id}`.

17. Keep public and admin frontend stacks separate. Public runtime work belongs in `assets/symfonicat.js`, `assets/stimulus.js`, `assets/controllers.json`, and `assets/controllers/`; admin runtime work belongs in `assets/symfonicat_admin.js`, `assets/stimulus_admin.js`, `assets/controllers_admin.json`, and `assets/controllers_admin/`.

18. Docker is the canonical runtime. The PHP image mounts the repo at `/symfonicat`, uses `/symfonicat` as `WORKDIR`, serves Caddy from `/symfonicat/public`, and should be kept aligned with Compose and entrypoint paths.

19. Redis is the shared infrastructure service for Symfony application cache, sessions, locks, admin login throttling, and Messenger. The app requires native `ext-redis`; do not add Predis unless there is a deliberate fallback requirement that is documented and configured.

20. Symfony Messenger defaults to Redis-backed async routing. The `async` and `failed` transports use `symfony/redis-messenger`, default routing sends `*` to `async`, and the Compose `messenger-worker` service consumes the `async` transport with multiple replicas, process limits, and `pcntl`/POSIX signal handling for clean shutdown.

## Documentation

- Every change to this repository must include corresponding rewrites of both `README.md` and `README_PROFILE.md`.
- Exception: template-only changes do not require README rewrites unless they change documented behavior, routing, commands, or another user-facing contract that the README already describes.
- Rewrite the README files as clean current-state snapshots rather than appending to or layering on top of prior wording.
- Prefer concise, practical README writing over exhaustive internal inventory. Favor setup, runtime behavior, and the commands/features a user actually needs; avoid duplicating minor implementation details, meta references, long table/entity inventories, and other low-signal material unless it is necessary to use or operate the project correctly.
- Do not preserve stale wording, historical notes, or old code references unless they are still true in the current tree.
- Keep `README.md` as the full core-repository README and `README_PROFILE.md` as the slimmed-down public-profile README.
