'use strict';

const path = require('path');
const { app, BrowserWindow, shell } = require('electron');

const config = {
    name: {{ electron_config.name|json_encode|raw }},
    startUrl: {{ start_url|json_encode|raw }},
    iconPath: {{ icon_path|json_encode|raw }},
    width: 1400,
    height: 900,
    backgroundColor: '#212529',
};

const resolvedIconPath = config.iconPath ? path.resolve(__dirname, config.iconPath) : undefined;

app.setName(config.name || 'symfonicat');

async function createWindow() {
    const window = new BrowserWindow({
        width: config.width,
        height: config.height,
        backgroundColor: config.backgroundColor,
        autoHideMenuBar: true,
        title: config.name || 'symfonicat',
        icon: resolvedIconPath,
        webPreferences: {
            contextIsolation: true,
            nodeIntegration: false,
            sandbox: false,
        },
    });

    window.webContents.setWindowOpenHandler(({ url }) => {
        void shell.openExternal(url);
        return { action: 'deny' };
    });

    await window.loadURL(config.startUrl);
}

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
