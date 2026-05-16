'use strict';

const { app, BrowserWindow, shell } = require('application');

const config = {
    name: {{ application_config.name|json_encode|raw }},
    startUrl: {{ start_url|json_encode|raw }},
    width: 1400,
    height: 900,
    backgroundColor: '#212529',
};

app.setName(config.name || 'symfonicat');

async function createWindow() {
    const window = new BrowserWindow({
        width: config.width,
        height: config.height,
        backgroundColor: config.backgroundColor,
        autoHideMenuBar: true,
        title: config.name || 'symfonicat',
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
