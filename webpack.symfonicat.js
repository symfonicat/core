const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');
const CONSOLE_PREFIX = 'bin/console';

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

const vendorName = (packageName) => packageName.split('/')[0] || '';

const loadConfiguredVendors = (projectDir) => {
    const configPath = path.join(projectDir, 'config', 'packages', 'symfonicat.yaml');
    const fallback = ['symfonicat'];

    if (!fs.existsSync(configPath)) {
        return fallback;
    }

    const lines = fs.readFileSync(configPath, 'utf8').split(/\r?\n/);
    const vendors = [];
    let inVendors = false;

    lines.forEach((line) => {
        if (/^\s*vendors\s*:/.test(line)) {
            inVendors = true;
            return;
        }

        if (!inVendors) {
            return;
        }

        const item = line.match(/^\s*-\s*([A-Za-z0-9_.-]+)\s*$/);
        if (item) {
            vendors.push(item[1]);
            return;
        }

        if (/^\S/.test(line)) {
            inVendors = false;
        }
    });

    return vendors.length > 0 ? Array.from(new Set(vendors)) : fallback;
};

const isConfiguredVendorPackage = (packageName, vendors) => vendors.includes(vendorName(packageName));

const resolveEntryFile = (basePath) => {
    if (!basePath) {
        return null;
    }

    if (!fs.existsSync(basePath)) {
        return null;
    }

    if (fs.statSync(basePath).isDirectory()) {
        const indexPath = path.join(basePath, 'index.js');

        return fs.existsSync(indexPath) ? indexPath : null;
    }

    return basePath;
};

const loadSymfonicatPackages = (projectDir) => {
    const packages = new Map();
    const vendors = loadConfiguredVendors(projectDir);
    const rootComposer = readJson(path.join(projectDir, 'composer.json'));
    const rootPackageName = typeof rootComposer?.name === 'string' ? rootComposer.name.trim() : '';

    if (isConfiguredVendorPackage(rootPackageName, vendors)) {
        packages.set(rootPackageName, {
            installPath: projectDir,
            name: rootPackageName,
            package: shortPackageName(rootPackageName),
            vendor: 'core',
        });
    }

    const installed = readJson(path.join(projectDir, 'vendor', 'composer', 'installed.json'));
    const installedPackages = Array.isArray(installed?.packages) ? installed.packages : [];

    installedPackages.forEach((pkg) => {
        const packageName = typeof pkg?.name === 'string' ? pkg.name.trim() : '';
        const installPath = typeof pkg?.['install-path'] === 'string' ? pkg['install-path'].trim() : '';

        if (!isConfiguredVendorPackage(packageName, vendors) || installPath === '') {
            return;
        }

        packages.set(packageName, {
            installPath: path.resolve(projectDir, 'vendor', 'composer', installPath),
            name: packageName,
            package: shortPackageName(packageName),
            vendor: vendorName(packageName),
        });
    });

    return Array.from(packages.values()).sort((a, b) => a.name.localeCompare(b.name));
};

const discoverPackageEntries = (projectDir, type) => {
    const entries = new Map();

    loadSymfonicatPackages(projectDir).forEach((pkg) => {
        const baseDir = path.join(pkg.installPath, 'assets', type);

        listDirEntries(baseDir).forEach((entry) => {
            const id = type === 'domain'
                ? entry.name
                : `${pkg.vendor === 'core' ? 'core' : `${pkg.vendor}/${pkg.package}`}/${entry.name}`;

            if (entries.has(id)) {
                const existing = entries.get(id);

                throw new Error(`Duplicate Symfonicat ${type} entry "${id}" found in both "${existing.packageName}" and "${pkg.name}".`);
            }

            const entryPath = resolveEntryFile(path.join(baseDir, entry.name));
            if (!entryPath) {
                return;
            }

            entries.set(id, {
                entry: entryPath,
                id: id,
                package: pkg.package,
                packageName: pkg.name,
                vendor: pkg.vendor,
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
        console.warn('[webpack] symfonicat:data:webpack failed; falling back to configured package vendors.');

        return {
            bundles: discoverPackageEntries(projectDir, 'bundle'),
            domains: discoverPackageEntries(projectDir, 'domain'),
            subdomains: discoverPackageEntries(projectDir, 'subdomain'),
            modules: discoverPackageEntries(projectDir, 'module'),
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

    (moduleData.subdomains || []).forEach((subdomain) => {
        if (!subdomain?.id || !subdomain?.entry || !fs.existsSync(subdomain.entry)) {
            return;
        }

        Encore.addEntry(`subdomain/${subdomain.id}`, toEntryPath(projectDir, subdomain.entry));
    });

    (moduleData.domains || []).forEach((domain) => {
        if (!domain?.id || !domain?.entry || !fs.existsSync(domain.entry)) {
            return;
        }

        Encore.addEntry(`domain/${domain.id}`, toEntryPath(projectDir, domain.entry));
    });

    (moduleData.modules || []).forEach((mod) => {
        if (!mod?.id || !mod?.entry || !fs.existsSync(mod.entry)) {
            return;
        }

        Encore.addEntry(`module/${mod.id}`, toEntryPath(projectDir, mod.entry));
    });

    (moduleData.bundles || []).forEach((bundle) => {
        if (!bundle?.id || !bundle?.entry || !fs.existsSync(bundle.entry)) {
            return;
        }

        Encore.addEntry(`bundle/${bundle.id}`, toEntryPath(projectDir, bundle.entry));
    });

    Encore
        .enableStimulusBridge('./assets/controller.json')
        .addEntry('app', toEntryPath(projectDir, path.join(packageDir, 'assets', 'app.js')))
        .addEntry('admin', toEntryPath(projectDir, path.join(packageDir, 'admin', 'assets', 'admin.js')))
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
