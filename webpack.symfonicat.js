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

const loadConfiguredVendors = (subdomainDir) => {
    const configPath = path.join(subdomainDir, 'config', 'packages', 'symfonicat.yaml');
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

const loadSymfonicatPackages = (subdomainDir) => {
    const packages = new Map();
    const vendors = loadConfiguredVendors(subdomainDir);
    const rootComposer = readJson(path.join(subdomainDir, 'composer.json'));
    const rootPackageName = typeof rootComposer?.name === 'string' ? rootComposer.name.trim() : '';

    if (isConfiguredVendorPackage(rootPackageName, vendors)) {
        packages.set(rootPackageName, {
            installPath: subdomainDir,
            name: rootPackageName,
            package: shortPackageName(rootPackageName),
            vendor: 'core',
        });
    }

    const installed = readJson(path.join(subdomainDir, 'vendor', 'composer', 'installed.json'));
    const installedPackages = Array.isArray(installed?.packages) ? installed.packages : [];

    installedPackages.forEach((pkg) => {
        const packageName = typeof pkg?.name === 'string' ? pkg.name.trim() : '';
        const installPath = typeof pkg?.['install-path'] === 'string' ? pkg['install-path'].trim() : '';

        if (!isConfiguredVendorPackage(packageName, vendors) || installPath === '') {
            return;
        }

        packages.set(packageName, {
            installPath: path.resolve(subdomainDir, 'vendor', 'composer', installPath),
            name: packageName,
            package: shortPackageName(packageName),
            vendor: vendorName(packageName),
        });
    });

    return Array.from(packages.values()).sort((a, b) => a.name.localeCompare(b.name));
};

const discoverPackageEntries = (subdomainDir, type) => {
    const entries = new Map();

    loadSymfonicatPackages(subdomainDir).forEach((pkg) => {
        const baseDir = path.join(pkg.installPath, 'assets', type);

        listDirEntries(baseDir).forEach((entry) => {
            const idPrefix = pkg.vendor === 'core' ? 'core' : `${pkg.vendor}/${pkg.package}`;
            const id = `${idPrefix}/${entry.name}`;

            if (entries.has(id)) {
                const existing = entries.get(id);

                throw new Error(`Duplicate Symfonicat ${type} entry "${id}" found in both "${existing.packageName}" and "${pkg.name}".`);
            }

            entries.set(id, {
                entry: path.join(baseDir, entry.name, 'index.js'),
                id: id,
                package: pkg.package,
                packageName: pkg.name,
                vendor: pkg.vendor,
            });
        });
    });

    return Array.from(entries.values()).sort((a, b) => a.id.localeCompare(b.id));
};

const loadModuleData = (subdomainDir) => {
    try {
        const raw = execSync(`${CONSOLE_PREFIX} symfonicat:data:webpack`, {
            cwd: subdomainDir,
            encoding: 'utf8',
        });

        return JSON.parse(raw);
    } catch (error) {
        console.warn('[webpack] symfonicat:data:webpack failed; falling back to configured package vendors.');

        return {
            applications: discoverPackageEntries(subdomainDir, 'applications'),
            domains: discoverPackageEntries(subdomainDir, 'domains'),
            subdomains: discoverPackageEntries(subdomainDir, 'subdomains'),
            modules: discoverPackageEntries(subdomainDir, 'modules'),
        };
    }
};

const toEntryPath = (subdomainDir, filePath) => {
    let relativePath = path.relative(subdomainDir, filePath).replace(/\\/g, '/');
    if (!relativePath.startsWith('.')) {
        relativePath = `./${relativePath}`;
    }

    return relativePath;
};

module.exports = function configureSymfonicat(Encore, options = __dirname) {
    const config = typeof options === 'string'
        ? { subdomainDir: options, packageDir: options }
        : {
            subdomainDir: options.subdomainDir || __dirname,
            packageDir: options.packageDir || options.subdomainDir || __dirname,
        };
    const { subdomainDir, packageDir } = config;
    const moduleData = loadModuleData(subdomainDir);

    (moduleData.subdomains || []).forEach((subdomain) => {
        if (!subdomain?.id || !subdomain?.entry || !fs.existsSync(subdomain.entry)) {
            return;
        }

        Encore.addEntry(`subdomains/${subdomain.id}`, toEntryPath(subdomainDir, subdomain.entry));
    });

    (moduleData.domains || []).forEach((domain) => {
        if (!domain?.id || !domain?.entry || !fs.existsSync(domain.entry)) {
            return;
        }

        Encore.addEntry(`domains/${domain.id}`, toEntryPath(subdomainDir, domain.entry));
    });

    (moduleData.modules || []).forEach((mod) => {
        if (!mod?.id || !mod?.entry || !fs.existsSync(mod.entry)) {
            return;
        }

        Encore.addEntry(`modules/${mod.id}`, toEntryPath(subdomainDir, mod.entry));
    });

    Encore
        .enableStimulusBridge('./assets/controllers.json')
        .addEntry('app', toEntryPath(subdomainDir, path.join(packageDir, 'assets', 'app.js')))
        .addEntry('admin', toEntryPath(subdomainDir, path.join(packageDir, 'admin', 'assets', 'admin.js')))
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
