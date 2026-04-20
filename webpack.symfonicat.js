const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');
const CONSOLE_PREFIX = 'docker exec php bin/console';

const listDirNames = (baseDir) => {
    if (!fs.existsSync(baseDir)) {
        return [];
    }

    return fs
        .readdirSync(baseDir, { withFileTypes: true })
        .filter((entry) => entry.isDirectory())
        .map((entry) => entry.name);
};

const loadModuleData = (projectDir) => {
    try {
        const raw = execSync(`${CONSOLE_PREFIX} symfonicat:data:webpack`, {
            cwd: projectDir,
            encoding: 'utf8',
        });

        return JSON.parse(raw);
    } catch (error) {
        console.warn('[webpack] symfonicat:data:webpack failed; falling back to assets directories.');

        return {
            domains: listDirNames(path.join(projectDir, 'assets', 'domains')),
            projects: listDirNames(path.join(projectDir, 'assets', 'projects')),
            modules: listDirNames(path.join(projectDir, 'assets', 'modules')),
        };
    }
};

const toEntryPath = (projectDir, filePath) => {
    let relativePath = path.relative(projectDir, filePath).replace(/\\/g, '/');
    if (!relativePath.startsWith('.')) {
        relativePath = `./${relativePath}`;
    }

    return relativePath;
};

module.exports = function configureSymfonicat(Encore, options = __dirname) {
    const config = typeof options === 'string'
        ? { projectDir: options, packageDir: options }
        : {
            projectDir: options.projectDir || __dirname,
            packageDir: options.packageDir || options.projectDir || __dirname,
        };
    const { projectDir, packageDir } = config;
    const moduleData = loadModuleData(projectDir);

    moduleData.projects.forEach((projectId) => {
        if (!projectId) {
            return;
        }

        const projectPath = path.join(projectDir, 'assets', 'projects', projectId, 'index.js');
        if (!fs.existsSync(projectPath)) {
            return;
        }

        Encore.addEntry('projects/' + projectId, `./assets/projects/${projectId}/index.js`);
    });

    moduleData.domains.forEach((id) => {
        if (!id) {
            return;
        }

        const domainPath = path.join(projectDir, 'assets', 'domains', id, 'index.js');
        if (!fs.existsSync(domainPath)) {
            return;
        }

        Encore.addEntry('domains/' + id, `./assets/domains/${id}/index.js`);
    });

    moduleData.modules.forEach((mod) => {
        if (!mod) {
            return;
        }

        const modulePath = path.join(projectDir, 'assets', 'modules', mod, 'index.js');
        if (!fs.existsSync(modulePath)) {
            return;
        }

        Encore.addEntry(`modules/${mod}`, `./assets/modules/${mod}/index.js`);
    });

    Encore
        .enableStimulusBridge('./assets/controllers.json')
        .addEntry('symfonicat', toEntryPath(projectDir, path.join(packageDir, 'assets', 'symfonicat.js')))
        .addEntry('symfonicat_admin', toEntryPath(projectDir, path.join(packageDir, 'assets', 'symfonicat_admin.js')))
        .splitEntryChunks()
        .enableSingleRuntimeChunk()
        .cleanupOutputBeforeBuild()
        .enableSourceMaps(!Encore.isProduction())
        .enableVersioning(Encore.isProduction())
        .configureDefinePlugin((options) => {
            options['process.env'] = options['process.env'] || {};
            options['process.env'].MERCURE_PUBLIC_URL = JSON.stringify(process.env.MERCURE_PUBLIC_URL);
            options['process.env'].MERCURE_URL = JSON.stringify(process.env.MERCURE_URL);
        })
        .configureBabelPresetEnv((config) => {
            config.useBuiltIns = 'usage';
            config.corejs = '3.38';
        })
        .enableSassLoader((options) => {
            options.api = 'modern';
            options.sassOptions = options.sassOptions || {};
            options.sassOptions.quietDeps = true;
            options.sassOptions.silenceDeprecations = [
                'import',
                'global-builtin',
                'color-functions',
                'if-function',
            ];
        })
    ;
};
