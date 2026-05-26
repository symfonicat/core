# AGENTS.md

This repository is the full `symfonicat/core` Symfony 8 application.

## Overview

Symfonicat is a multi-tenant frontend platform. It resolves incoming requests to domains, subdomains, endpoints, or applications and renders the appropriate shell while providing module runtime, environment configuration, and admin CRUD.

Public routes:
- `/`
- `/{path}`
- `/application/{vendor}/{id}/{path}` (internal application entry)

Admin area is disabled unless `symfonicat.lock` exists in the repo root. All `/admin/*` paths are guarded.

## Core Model

Entities (tables prefixed `symfonicat_`):
- `Domain`, `Subdomain`, `Application`, `Endpoint`, `Parcel`, `Module`, `Middleware`, `Env`, `EnvParent`
- Join tables for module attachments, middleware attachments, and env values per scope (domain, subdomain, application, endpoint, parcel)

Env resolution order (later layers override earlier):
bundle/parcel → domain → subdomain → application → endpoint (Electron last when present)

Runtime services: `DomainService`, `SubdomainService`, `ApplicationService`, `EnvService`, `ParcelService`, `ModuleService`.

## Admin

- `/admin/a` — Applications
- `/admin/b` — Bundles (parcels)
- `/admin/end` — Endpoints
- `/admin/m` — Middleware
- `/admin/s` — Subdomains
- `/admin/y/*` — YAML dump/load
- `/admin/s` — Schema sync action

Forms support attaching parcels, repeatable middleware, modules, scoped env values, and catch flags.

## Commands

- `symfonicat:schema:update` — Doctrine schema + package row synchronization
- `symfonicat:load` / `symfonicat:dump` / `symfonicat:purge`
- `symfonicat:admin:create` / `symfonicat:admin:delete`
- `symfonicat:data:webpack` (WebpackModulesDataCommand)
- `symfonicat:electron:build` (documented, renders Electron entry files)

## Package Discovery & Assets

Configured in `config/packages/symfonicat.yaml` under `vendors`.

Webpack entry discovery and schema sync scan:
- `assets/{domain,subdomain,application,module,parcel,bundle}/` in root and installed vendor packages

Public asset helper: `symfonicat_asset(path)` with optional entity target (prefers subdomain → domain → default, or explicit Electron path).

Public JS entry: `assets/app.js`

Admin asset stack lives under `admin/assets/`.

## Runtime

- `env()` Twig function and `window.env` expose layered configuration.
- `path_application()` helper for application shell URLs.
- Module controllers extend `AbstractModuleController` and only execute when the module is attached to the active context.
- Electron rows (plain ids) add `?electron={id}` to start URLs and expose an `electron` Twig variable.

## Docker

Canonical runtime uses Docker/FrankenPHP. Redis backs cache, sessions, locks, admin throttling, and Messenger `async` transport.

## Documentation

Every change must update both `README.md` and `README_PROFILE.md` as clean current-state snapshots.