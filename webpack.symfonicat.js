const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');
const CONSOLE_PREFIX = 'docker exec php bin/console';

const readJson = (filePath) => {
    try {
        return JSON.parse(fs.readFileSync(filePath, 'utf8'));
    } catch (error) {
        return null;
    }
};

const listDirEntries = (baseDir) => {
    if (!fs.existsSync(baseDir)) {
        return [];
    }

    return fs.readdirSync(baseDir, { withFileTypes: true }).filter((entry) => entry.isDirectory());
};

const shortPackageName = (packageName) => {
    const parts = packageName.split('/');

    return parts[parts.length - 1];
};

const loadSymfonicatPackages = (projectDir) => {
    const packages = new Map();
    const rootComposer = readJson(path.join(projectDir, 'composer.json'));
    const rootPackageName = typeof rootComposer?.name === 'string' ? rootComposer.name.trim() : '';

    if (rootPackageName.startsWith('symfonicat/')) {
        packages.set(rootPackageName, {
            installPath: projectDir,
            name: rootPackageName,
            package: shortPackageName(rootPackageName),
        });
    }

    const installed = readJson(path.join(projectDir, 'vendor', 'composer', 'installed.json'));
    const installedPackages = Array.isArray(installed?.packages) ? installed.packages : [];

    installedPackages.forEach((pkg) => {
        const packageName = typeof pkg?.name === 'string' ? pkg.name.trim() : '';
        const installPath = typeof pkg?.['install-path'] === 'string' ? pkg['install-path'].trim() : '';

        if (!packageName.startsWith('symfonicat/') || installPath === '') {
            return;
        }

        packages.set(packageName, {
            installPath: path.resolve(projectDir, 'vendor', 'composer', installPath),
            name: packageName,
            package: shortPackageName(packageName),
        });
    });

    return Array.from(packages.values()).sort((a, b) => a.name.localeCompare(b.name));
};

const discoverPackageEntries = (projectDir, type) => {
    const entries = new Map();

    loadSymfonicatPackages(projectDir).forEach((pkg) => {
        const baseDir = path.join(pkg.installPath, 'assets', type);

        listDirEntries(baseDir).forEach((entry) => {
            const id = `${pkg.package}/${entry.name}`;

            if (entries.has(id)) {
                const existing = entries.get(id);

                throw new Error(`Duplicate Symfonicat ${type} entry "${id}" found in both "${existing.packageName}" and "${pkg.name}".`);
            }

            entries.set(id, {
                entry: path.join(baseDir, entry.name, 'index.js'),
                id: id,
                package: pkg.package,
                packageName: pkg.name,
            });
        });
    });

    return Array.from(entries.values()).sort((a, b) => a.id.localeCompare(b.id));
};

const loadModuleData = (projectDir) => {
    try {
        const raw = execSync(`${CONSOLE_PREFIX} symfonicat:data:webpack`, {
            cwd: projectDir,
            encoding: 'utf8',
        });

        return JSON.parse(raw);
    } catch (error) {
        console.warn('[webpack] symfonicat:data:webpack failed; falling back to installed symfonicat/* packages.');

        return {
            applications: discoverPackageEntries(projectDir, 'applications'),
            domains: discoverPackageEntries(projectDir, 'domains'),
            projects: discoverPackageEntries(projectDir, 'projects'),
            modules: discoverPackageEntries(projectDir, 'modules'),
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

    (moduleData.applications || []).forEach((application) => {
        if (!application?.id || !application?.entry || !fs.existsSync(application.entry)) {
            return;
        }

        Encore.addEntry(`applications/${application.id}`, toEntryPath(projectDir, application.entry));
    });

    (moduleData.projects || []).forEach((project) => {
        if (!project?.id || !project?.entry || !fs.existsSync(project.entry)) {
            return;
        }

        Encore.addEntry(`projects/${project.id}`, toEntryPath(projectDir, project.entry));
    });

    (moduleData.domains || []).forEach((domain) => {
        if (!domain?.id || !domain?.entry || !fs.existsSync(domain.entry)) {
            return;
        }

        Encore.addEntry(`domains/${domain.id}`, toEntryPath(projectDir, domain.entry));
    });

    (moduleData.modules || []).forEach((mod) => {
        if (!mod?.id || !mod?.entry || !fs.existsSync(mod.entry)) {
            return;
        }

        Encore.addEntry(`modules/${mod.id}`, toEntryPath(projectDir, mod.entry));
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
