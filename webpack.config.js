//const Dotenv = require('dotenv-webpack');
var Encore = require('@symfony/webpack-encore');

// Manually configure the runtime environment if not already configured yet by the "encore" command.
// It's useful when you use tools that rely on webpack.config.js file.
if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

Encore
    .setOutputPath('public/build/')
    .setPublicPath('/build')
    .cleanupOutputBeforeBuild()
    .autoProvidejQuery()
    .autoProvideVariables({
        //    "window.Bloodhound": require.resolve('bloodhound-js'),
        "jQuery.tagsinput": "bootstrap-tagsinput"
    })
    .enableSassLoader()
    .enableVersioning()


    //JavaScripts
    .addEntry('js/app', './assets/js/app.js')
    .addEntry('js/user/admin', './assets/js/user/admin.js')
    .addEntry('js/user/showUser', './assets/js/user/showUser.js')
    .addEntry('js/admin/update', './assets/js/admin/update.js')

    //Common
    //  ->filters
    .addEntry('js/common/filters/dateBikeFilter', './assets/js/common/filters/dateBikeFilter.js')

    //Refueling
    .addEntry('js/refueling/new', './assets/js/refueling/new.js')

    //Maintenance
    .addEntry('js/maintenance/new', './assets/js/maintenance/new.js')

    //Bike
    .addEntry('js/bike/new', './assets/js/bike/new.js')

    //Styles
    .addStyleEntry('css/app', ['./assets/scss/app.scss'])
    .addStyleEntry('css/copyleft', ['./assets/css/copyleft.css'])
    .addStyleEntry('css/dashboard', ['./assets/scss/dashboard.scss'])
    .addStyleEntry('css/admin', ['./assets/scss/admin.scss'])
    .addStyleEntry('css/maintenance', ['./assets/scss/maintenance.scss'])


    //plugins

    //.addPlugin(new Dotenv({path: './.env.local'}))


    // When enabled, Webpack "splits" your files into smaller pieces for greater optimization.
    .splitEntryChunks()

    // will require an extra script tag for runtime.js
    // but, you probably want this, unless you're building a single-page app
    .enableSingleRuntimeChunk()


    /*
     * FEATURE CONFIG
     *
     * Enable & configure other features below. For a full
     * list of features, see:
     * https://symfony.com/doc/current/frontend.html#adding-more-features
     */
    .cleanupOutputBeforeBuild()
    .enableBuildNotifications()
    .enableSourceMaps(!Encore.isProduction())
    // enables hashed filenames (e.g. app.abc123.css)
    .enableVersioning(Encore.isProduction())

    // enables @babel/preset-env polyfills
    .configureBabelPresetEnv((config) => {
        config.useBuiltIns = 'usage';
        config.corejs = 3;
    })

    // enables Sass/SCSS support
    .enableSassLoader()

    // uncomment if you use TypeScript
    //.enableTypeScriptLoader()

    // uncomment to get integrity="..." attributes on your script & link tags
    // requires WebpackEncoreBundle 1.4 or higher
    //.enableIntegrityHashes(Encore.isProduction())

    // uncomment if you're having problems with a jQuery plugin
    //.autoProvidejQuery()

    // uncomment if you use API Platform Admin (composer req api-admin)
    //.enableReactPreset()
    //.addEntry('admin', './assets/js/admin.js')
    ;

module.exports = Encore.getWebpackConfig();
