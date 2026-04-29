# Symfonicat

`symfonicat/core` is the full Symfony application for Symfonicat. Public routing, admin CRUD, Doctrine entities, webpack wiring, Docker/FrankenPHP, module runtime, application shells, and Electron packaging all live in this repository.

## Install

For local development, point the seeded hosts at your Docker host:

```text
127.0.0.1 example.com
127.0.0.1 project1.example.com
```
```bash
# clone repo
git clone https://github.com/symfonicat/core symfonicat

# or

# composer w/php8.4 on host
composer create-project symfonicat/core symfonicat

# init
cd symfonicat
docker compose up -d
```

On startup the `php` container installs Composer dependencies, bootstraps the schema, seeds local defaults, runs `npm install`, and runs `npm run build`. The Docker image also installs `n` globally and runs `n latest`, so the Node/Electron toolchain inside the container is current.

After the stack is up:

```bash
docker exec -it php bin/console symfonicat:admin:create <username>
docker exec -it php bin/console symfonicat:schema:update
```
## Public Runtime

Runtime resolution is layered:

1. `DomainService` resolves the base host.
2. `ProjectService` resolves the project subdomain when present.
3. `RoutingRuleSubscriber` applies redirect, route, domain, project, and application rules.
4. `ApplicationService` loads application shells either from regex path rules or route-bound application rules.
5. The public controllers render the domain, project, or application shell when a Symfony route has not already taken over.

The default public routes are:

- `/` for the domain shell
- `/{path}` for the project shell when a project subdomain is active
- `/application/{id}/{path}` as the internal application entry route used to render an application and redirect client-side history to its public rule-backed path

Symfonicat-owned Symfony route and table names are prefixed with `symfonicat_`.

## Routing Rules

Supported rule types:

- `domain`: render the domain shell for a matching regex path
- `project`: disable the project catch-all for a matching regex path so Symfony routes can handle it
- `application`: either match regex arguments and render an application shell, or bind an application to a named Symfony route
- `redirect`: redirect a whole domain or project to another domain, project host, or `project.domain` pair
- `route`: render a named Symfony route for the root of a domain or project

Application rules use `applicationType`:

- `arguments`: use the regex argument list
- `route`: use `RoutingRule.route` as the Symfony route name

## Env

Env is grouped by `EnvParent`, with leaf keys stored in `Env`.

Runtime precedence is:

1. application env
2. domain env
3. project env
4. electron env, but only for Electron requests

Project values overwrite domain values, domain values overwrite application values, and Electron values overwrite the merged result only when the current request is running in Electron.

The same grouped structure is emitted directly into `window.env`:

```js
window.env = {
    colors: {
        primary: 'blue'
    }
}
```

Scoped env forms on domains, projects, and applications filter the env dropdown by the selected env parent and restore the saved parent when editing existing rows.
Electron rows use the same scoped env collection UI, and Electron env values override all lower layers for Electron requests only.

There is also `EnvService` for env lookups. Use `->get($id, $entity)` or `->all($entity)` and omit `$entity` to let the other retrieval services pull the correct env values for the URL you're using it for.

## Twig

Twig, for the `env` helper, uses dotted lookups such as:

```twig
{{ env('colors.primary') }}
```
There is also a `path_application` helper that works like this:

```twig
{# https://example.com/symfony/*/test #}
{{ path_application(application) }}

{# https://example.com/symfony/*/test/somepath/testpath #}
{{ path_application(application, 'somepath/testpath') }}

{# https://example.com/symfony/PARAM/test/somepath/testpath #}
{{ path_application(application, 'somepath/testpath', ['PARAM']) }}

{# application ID also works #}
{{ path_application('test', 'somepath/testpath', ['PARAM']) }}
```

## Assets

Webpack entry discovery is driven by `symfonicat:data:webpack`, with database-backed rows and filesystem fallback:

- `assets/applications/{id}` -> `applications/{id}`
- `assets/domains/{id}` -> `domains/{id}`
- `assets/projects/{id}` -> `projects/{id}`
- `assets/modules/{id}` -> `modules/{id}`

Public assets live on:

- `assets/symfonicat.js`
- `assets/stimulus.js`
- `assets/controllers.json`
- `assets/controllers/`

Admin-only JavaScript belongs on the admin asset stack, `assets/*_admin*`.

## Module Runtime

Backend module controllers live under `/m/{id}` and should extend `Symfonicat\Controller\AbstractModuleController`.

Frontend helpers from `assets/module.js` support:

- `''.json(payload)`
- `''.json(path, payload)`
- `''.html(payload)`
- `''.html(path, payload)`
- `''.log(...args)`

Application shells expose a signed application request context through `application_helper()`, which writes `window.application` plus debug logs into the base layout script block. Application-backed module requests send the application id plus signed headers so `/m/{id}` can execute when the module is attached to that application.

The base layout also writes `window.electron` from the Twig `electron` global. That value is a boolean flag indicating whether the current request is running in Electron mode.

## Admin

Admin is isolated from any host user system and uses its own `Admin` entity plus Symfony security and TOTP MFA at `/admin`.

Modules do not have admin CRUD. Module rows are synchronized from `assets/modules/{id}/package.json`.

`symfonicat:admin:create` prompts for the password with hidden input, so Docker usage should include `-it`.

## Electron

Each `Electron` row has:

- `name`
- `type` (`domain`, `project`, or `application`)
- one matching relation field, except `project` rows which carry both `project` and `domain`
- an optional favicon upload stored at `public/electron/favicon/{type}/{targetId}.png`
- an `env` collection using the same `EnvParent` + `Env` selectors as domains, projects, and applications

For project Electron rows, `targetId` is `projectId.domainId`.
That same `projectId.domainId` target id is used for both override templates and build output paths.

The admin form shows only the relation field that matches the selected type, except project rows which show both the project and domain selectors.

Electron build templates live under:

- `templates/electron/domain/main.twig.js`
- `templates/electron/project/main.twig.js`
- `templates/electron/application/main.twig.js`
- `templates/electron/{type}/overrides/{targetId}.twig.js`

For project Electron rows specifically:

- override templates live at `templates/electron/project/overrides/{projectId}.{domainId}.twig.js`
- generated files live under `electron/project/{projectId}.{domainId}/`

Build Electron outputs with:

```bash
docker exec php bin/console symfonicat:electron:build
docker exec php bin/console symfonicat:electron:build <name>
```

For each Electron row, the command renders the override template if present, otherwise the type-specific main `*.twig.js` template, writes `electron/{type}/{targetId}/app.js`, writes a local `package.json` with a fixed Electron version derived from the root package, and runs `electron-builder` into `electron/{type}/{targetId}/build`. For project Electron rows that means `electron/project/{projectId}.{domainId}/...`. The generated Electron package points at the matching domain host, `project.domain` host, or application path and appends `?electron` to the start URL. Those `build` directories are generated outputs and are ignored by Git.

Electron requests keep using the Twig Electron globals. When a request is flagged as Electron, the extension loads the matching `Electron` row for the active application, project, or domain and exposes its favicon to the base layout.

## Sync and Bootstrap

`symfonicat:bootstrap` seeds local defaults, including:

- `localhost`
- `example.com`
- `project1`
- `test` application
- `analytics` module
- `Example Test` Electron row bound to `example.com`
- `/symfonicat/*/test*` application routing rule
- grouped sample env values under `colors.primary`, including an Electron override of `yellow` for the seeded `Example Test` Electron row

`symfonicat:schema:update` synchronizes:

- modules from `assets/modules/{id}/package.json`
- applications from `assets/applications/{id}`
- projects from `assets/projects/{id}`

Run schema sync with an interactive terminal when confirmations may be needed:

```bash
docker exec -it php bin/console symfonicat:schema:update
```