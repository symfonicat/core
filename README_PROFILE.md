# Symfonicat

`symfonicat/core` is the full Symfonicat Symfony application. It ships the public runtime, the separate admin runtime, webpack integration, Electron-facing commands, and the Docker/FrankenPHP starter shell in one repository.

## What It Does

- resolves a base `Domain` and optional subdomain `Project`
- renders domain and project shells from one Symfony app
- mounts database-backed modules onto domains or projects
- keeps admin auth and admin CRUD inside Symfonicat
- exposes Electron/runtime data from the same install target

## Routing Model

Symfonicat has two public routing defaults:

- projects are default client-side routed
- domains are default Symfony-side routed

`RoutingRule.argument` only applies to the legacy `domain` and `project` rule types.
- legacy `domain` and `project` rules require a non-empty argument through entity validation; redirect and route rules omit it and normalize it to an empty string
- the routing rule argument `admin` is reserved through entity validation, cannot be used by domain or project routing rules, and is ignored by runtime matching
- the runtime ignores any routing rule with argument `admin`, even if invalid data exists in the database
- legacy `domain` rules still force the matching argument into the domain shell
- legacy `project` rules still bypass the project catch-all so Symfony handles the request
- redirect rules apply to a whole domain or project and redirect to a domain or project
- route rules apply to the root of a domain or project and render a named Symfony route
- the routing rule form groups rule, match, redirect, and route fields into separate cards, keeps `type`, `redirectType`, and `routeType` together in the rule card, hides `argument` for redirect and route rules, hides unused match-field columns cleanly so the full-width project selector does not leave an empty slot above it, and keeps redirect target on the left with the selected redirect domain or project field on the right

- a domain route rule applies only when no project is active and the request is for `/`
- a project route rule applies when a project is active and the request is for `/`

## Runtime Shape

- `/` renders the domain shell when no project is resolved
- `/{path}` renders the project shell when a project is resolved and the project catch-all is still enabled for that argument
- top-level public controllers are imported with a guard so project subdomains keep the project catch-all unless a legacy project routing rule disables it for the current argument
- project rendering checks `project/overrides/{project.id}.html.twig` before falling back to `project/main.html.twig`
- domain rendering checks `domain/overrides/{domain.id}.html.twig` before falling back to `domain/main.html.twig`
- modules attach to either domains or projects and expose backend endpoints under `/m/{id}`
- webpack entries are discovered from `symfonicat:data:webpack` with filesystem fallback under `assets/domains`, `assets/projects`, and `assets/modules`
- `Project.id` is the canonical, immutable project identifier for subdomains, asset entry names, and Electron/runtime data
- `Module.id` is the canonical, immutable module identifier for backend routes and module asset entry names
- `symfonicat:schema:update` synchronizes `Module` rows from `assets/modules/{id}/package.json` and asks before deleting referenced modules removed from disk

## Admin

- admin lives under `/admin`
- admin auth is isolated from any host app user system
- the Docker image includes `ext-tidy`, and the shared base layouts use it to format the rendered `body` block for readable HTML source while keeping the first emitted line flush with the Twig print site
- admins are managed with `symfonicat:admin:create` and `symfonicat:admin:delete`

## Install

```bash
composer create-project symfonicat/core symfonicat
cd symfonicat
docker compose up -d
npm install
npm run dev
```

For the full development README, runtime details, and local bootstrap notes, see [README.md](https://github.com/symfonicat/core/blob/main/README.md).
