# AGENTS.md

These instructions apply to the main app repo at `/home/t/www/symfonicat/core`.

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
- `symfonicat:data:webpack` is the canonical webpack data source for domain/project/module entries, with filesystem fallback under `assets/...`
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

## Documentation

- Every change to this repository must include corresponding updates to both `README.md` and `README_PROFILE.md`.
- Keep `README.md` as the full core-repository README.
- Keep `README_PROFILE.md` as the slimmed-down public-profile README.
