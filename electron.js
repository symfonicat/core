#!/usr/bin/env node
'use strict';

const fs = require('fs');
const fsp = require('fs/promises');
const path = require('path');
const { execSync } = require('child_process');

const ROOT_DIR = __dirname;
const ELECTRON_DIR = path.join(ROOT_DIR, 'electron');
const PROJECTS_DIR = path.join(ELECTRON_DIR, 'projects');
const SCAFFOLD_FILE = path.join(ELECTRON_DIR, 'app.js');
const CONSOLE_PREFIX = 'docker exec php bin/console';

const PREPARE_ONLY = process.argv.includes('--prepare-only');
const PROJECT_FILTER = process.argv.find((arg) => arg.startsWith('--project='))?.slice('--project='.length) || '';
const DEFAULT_DOMAIN = process.env.ELECTRON_DEFAULT_DOMAIN;

function listDirNames(baseDir) {
    if (!fs.existsSync(baseDir)) {
        return [];
    }

    return fs
        .readdirSync(baseDir, { withFileTypes: true })
        .filter((entry) => entry.isDirectory())
        .map((entry) => entry.name);
}

function runConsoleJson(command) {
    const parseRaw = (raw) => {
        const text = String(raw || '').trim();
        if (text === '') {
            return null;
        }

        try {
            return JSON.parse(text);
        } catch (error) {
            const start = text.indexOf('{');
            const end = text.lastIndexOf('}');
            if (start === -1 || end === -1 || end <= start) {
                return null;
            }

            try {
                return JSON.parse(text.slice(start, end + 1));
            } catch (innerError) {
                return null;
            }
        }
    };

    try {
        const raw = execSync(command, {
            cwd: ROOT_DIR,
            encoding: 'utf8',
            stdio: ['ignore', 'pipe', 'pipe'],
        });

        const parsed = parseRaw(raw);
        if (parsed !== null) {
            return parsed;
        }

        throw new Error(`Console command did not return JSON: ${command}`);
    } catch (error) {
        const stdout = String(error?.stdout || '');
        const stderr = String(error?.stderr || '');
        const parsed = parseRaw(stdout) || parseRaw(stderr) || parseRaw(`${stdout}\n${stderr}`);
        if (parsed !== null) {
            return parsed;
        }

        throw error;
    }
}

function summarizeError(error) {
    const firstLine = (value) => String(value || '')
        .split('\n')
        .map((line) => line.trim())
        .find((line) => line !== '');

    const candidates = [
        firstLine(error?.stderr),
        firstLine(error?.stdout),
        firstLine(error?.message),
    ].filter(Boolean);

    return candidates[0] || 'unknown error';
}

function projectIdToName(projectId) {
    if (!projectId) {
        return 'project';
    }

    return projectId
        .split(/[-_]+/)
        .filter(Boolean)
        .map((segment) => segment.charAt(0).toUpperCase() + segment.slice(1))
        .join(' ');
}

function normalizeProject(project) {
    const id = typeof project?.id === 'string' ? project.id.trim() : '';
    if (!id) {
        return null;
    }

    const name = typeof project?.name === 'string' && project.name.trim() !== ''
        ? project.name.trim()
        : projectIdToName(id);

    const icon = typeof project?.icon === 'string' && project.icon.trim() !== ''
        ? project.icon.trim()
        : null;

    const domain = typeof project?.domain === 'string' && project.domain.trim() !== ''
        ? project.domain.trim()
        : DEFAULT_DOMAIN;

    return { id, name, icon, domain };
}

function dedupeProjects(projects) {
    const byId = new Map();
    projects.forEach((project) => {
        if (!project) {
            return;
        }

        byId.set(project.id, project);
    });

    return Array.from(byId.values()).sort((left, right) => left.id.localeCompare(right.id));
}

function loadProjects() {
    try {
        const payload = runConsoleJson(`${CONSOLE_PREFIX} symfonicat:data:electron`);
        if (Array.isArray(payload?.projects)) {
            const projects = payload.projects
                .map(normalizeProject)
                .filter(Boolean);
            if (projects.length > 0) {
                return dedupeProjects(projects);
            }
        }
    } catch (error) {
        console.warn(`[electron] symfonicat:data:electron failed (${summarizeError(error)}); falling back.`);
    }

    try {
        const payload = runConsoleJson(`${CONSOLE_PREFIX} symfonicat:data:webpack`);
        if (Array.isArray(payload?.projects)) {
            const projects = payload.projects
                .map((id) => normalizeProject({ id }))
                .filter(Boolean);
            if (projects.length > 0) {
                return dedupeProjects(projects);
            }
        }
    } catch (error) {
        console.warn(`[electron] symfonicat:data:webpack failed (${summarizeError(error)}); falling back to assets/projects directories.`);
    }

    return dedupeProjects(
        listDirNames(path.join(ROOT_DIR, 'assets', 'projects'))
            .map((id) => normalizeProject({ id }))
            .filter(Boolean),
    );
}

function resolveIconSource(iconPath) {
    const fallback = path.join(ROOT_DIR, 'public', 'favicon.png');

    if (!iconPath) {
        return fs.existsSync(fallback) ? fallback : null;
    }

    if (/^https?:\/\//i.test(iconPath)) {
        return fs.existsSync(fallback) ? fallback : null;
    }

    if (path.isAbsolute(iconPath) && fs.existsSync(iconPath)) {
        return iconPath;
    }

    const relative = iconPath.replace(/^\/+/, '');
    const publicCandidate = path.join(ROOT_DIR, 'public', relative);
    if (fs.existsSync(publicCandidate)) {
        return publicCandidate;
    }

    const rootCandidate = path.join(ROOT_DIR, relative);
    if (fs.existsSync(rootCandidate)) {
        return rootCandidate;
    }

    return fs.existsSync(fallback) ? fallback : null;
}

function sanitizePackageName(projectId) {
    const cleaned = projectId
        .toLowerCase()
        .replace(/[^a-z0-9-]+/g, '-')
        .replace(/^-+|-+$/g, '');

    return cleaned === '' ? 'project' : cleaned;
}

function buildProjectUrl(project) {
    const domain = (project.domain || DEFAULT_DOMAIN).trim() || DEFAULT_DOMAIN;
    const encodedProjectId = encodeURIComponent(project.id);
    return `https://${encodedProjectId}.${domain}?electron=1`;
}

async function ensureCleanDirectory(dir) {
    await fsp.mkdir(dir, { recursive: true });

    const generatedPaths = [
        'app.js',
        'main.js',
        'preload.js',
        'electron-app.json',
        'package.json',
        'package-lock.json',
        'dist',
        'node_modules',
    ];

    for (const relative of generatedPaths) {
        await fsp.rm(path.join(dir, relative), { recursive: true, force: true });
    }

    const entries = await fsp.readdir(dir);
    for (const entry of entries) {
        if (entry.startsWith('icon.')) {
            await fsp.rm(path.join(dir, entry), { force: true });
        }
    }
}

async function prepareProjectApp(project, electronVersion) {
    const projectDir = path.join(PROJECTS_DIR, project.id);
    await ensureCleanDirectory(projectDir);

    await fsp.copyFile(SCAFFOLD_FILE, path.join(projectDir, 'app.js'));

    const iconSource = resolveIconSource(project.icon);
    let iconFile = 'favicon.png';
    if (iconSource) {
        const ext = path.extname(iconSource) || '.png';
        iconFile = `icon${ext.toLowerCase()}`;
        await fsp.copyFile(iconSource, path.join(projectDir, iconFile));
    }

    const startUrl = buildProjectUrl(project);
    const metadata = {
        id: project.id,
        name: project.name,
        domain: project.domain,
        startUrl,
        icon: iconFile,
    };

    await fsp.writeFile(
        path.join(projectDir, 'electron-app.json'),
        `${JSON.stringify(metadata, null, 2)}\n`,
        'utf8',
    );

    const preloadFile = `'use strict';

const { contextBridge } = require('electron');
const metadata = require('./electron-app.json');

contextBridge.exposeInMainWorld('electronProject', metadata);
`;
    await fsp.writeFile(path.join(projectDir, 'preload.js'), preloadFile, 'utf8');

    const mainFile = `'use strict';

const path = require('path');
const { bootstrapProjectApp } = require('./app');
const metadata = require('./electron-app.json');

bootstrapProjectApp({
    name: metadata.name,
    startUrl: metadata.startUrl,
    preloadPath: path.join(__dirname, 'preload.js'),
    iconPath: path.join(__dirname, metadata.icon),
});
`;
    await fsp.writeFile(path.join(projectDir, 'main.js'), mainFile, 'utf8');

    const packageName = `symfonicat-${sanitizePackageName(project.id)}-desktop`;
    const buildConfig = {
        appId: `me.symfonicat.${sanitizePackageName(project.id)}`,
        productName: project.name,
        directories: {
            output: 'dist',
            buildResources: '.',
        },
        files: [
            'app.js',
            'main.js',
            'preload.js',
            'electron-app.json',
            `${iconFile}`,
        ],
        asar: true,
        icon: iconFile,
    };

    if (electronVersion) {
        buildConfig.electronVersion = electronVersion;
    }

    const packageJson = {
        name: packageName,
        private: true,
        version: '1.0.0',
        description: `${project.name} desktop application`,
        main: 'main.js',
        productName: project.name,
        license: 'UNLICENSED',
        build: buildConfig,
    };

    await fsp.writeFile(
        path.join(projectDir, 'package.json'),
        `${JSON.stringify(packageJson, null, 2)}\n`,
        'utf8',
    );

    return projectDir;
}

async function packageProject(projectDir) {
    const electronBuilder = require('electron-builder');
    const targets = electronBuilder.Platform.current().createTarget('dir');

    await electronBuilder.build({
        projectDir,
        targets,
        publish: 'never',
    });
}

async function main() {
    if (!fs.existsSync(SCAFFOLD_FILE)) {
        throw new Error(`Missing shared scaffold: ${SCAFFOLD_FILE}`);
    }

    const projects = loadProjects().filter((project) => !PROJECT_FILTER || project.id === PROJECT_FILTER);
    if (projects.length === 0) {
        throw new Error('No projects available for Electron packaging.');
    }

    await fsp.mkdir(PROJECTS_DIR, { recursive: true });

    let electronVersion = process.env.ELECTRON_VERSION || '';
    if (!PREPARE_ONLY && electronVersion === '') {
        try {
            electronVersion = require('electron/package.json').version;
        } catch (error) {
            throw new Error('Missing "electron" dependency. Install it before running npm run electron.');
        }
    }

    for (const project of projects) {
        console.log(`[electron] preparing ${project.id}`);
        const projectDir = await prepareProjectApp(project, electronVersion);

        if (PREPARE_ONLY) {
            continue;
        }

        console.log(`[electron] packaging ${project.id}`);
        await packageProject(projectDir);
    }

    console.log('[electron] done');
}

main().catch((error) => {
    const message = error instanceof Error ? error.stack || error.message : String(error);
    console.error(`[electron] failed: ${message}`);
    process.exit(1);
});
