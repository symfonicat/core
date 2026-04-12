'use strict';

const path = require('path');
const { app, BrowserWindow, shell } = require('electron');

function bootstrapProjectApp(config = {}) {
    const {
        name = 'symfonicat',
        startUrl,
        preloadPath,
        iconPath = '',
        width = 1400,
        height = 900,
        backgroundColor = '#212529',
    } = config;

    if (!startUrl) {
        throw new Error('Missing required "startUrl" for Electron app bootstrap.');
    }

    const resolvedPreloadPath = preloadPath ? path.resolve(preloadPath) : undefined;
    const resolvedIconPath = iconPath ? path.resolve(iconPath) : undefined;

    app.setName(name);

    const createWindow = async () => {
        const browserWindow = new BrowserWindow({
            width,
            height,
            backgroundColor,
            autoHideMenuBar: true,
            title: name,
            icon: resolvedIconPath,
            webPreferences: {
                preload: resolvedPreloadPath,
                contextIsolation: true,
                nodeIntegration: false,
                sandbox: false,
            },
        });

        browserWindow.webContents.setWindowOpenHandler(({ url }) => {
            void shell.openExternal(url);
            return { action: 'deny' };
        });

        await browserWindow.loadURL(startUrl);
    };

    app.whenReady().then(() => {
        void createWindow();

        app.on('activate', () => {
            if (BrowserWindow.getAllWindows().length === 0) {
                void createWindow();
            }
        });
    });

    app.on('window-all-closed', () => {
        if (process.platform !== 'darwin') {
            app.quit();
        }
    });
}

module.exports = {
    bootstrapProjectApp,
};
