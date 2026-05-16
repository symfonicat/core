const path = require('path');
const Encore = require('@symfony/webpack-encore');
const configureSymfonicat = require('./webpack.symfonicat');

if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

Encore
    .setOutputPath('public/build/')
    .setPublicPath('/build/')
    .setManifestKeyPrefix('build/')
;

configureSymfonicat(Encore, {
    subdomainDir: __dirname,
    packageDir: path.join(__dirname),
});

module.exports = Encore.getWebpackConfig();
