const Encore = require('@symfony/webpack-encore');

// Manually configure the runtime environment if not already configured yet by the "encore" command.
// It's useful when you use tools that rely on webpack.config.js file.
if (!Encore.isRuntimeEnvironmentConfigured()) {
        Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

Encore
    // Directory where compiled assets will be stored.
    .setOutputPath('public/build/')

    // Public URL path used by the web server to access the output path.
    .setPublicPath('/build')

    // this is now needed so that your manifest.json keys are still `build/foo.js`
    // (which is a file that's used by Symfony's `asset()` function)
    .setManifestKeyPrefix('build')

    // Set up global variables.
    .autoProvidejQuery()

    .copyFiles({
        from: './assets/images',
        to: 'images/[path][name].[ext]'
    })

    .copyFiles({
        from: './assets/fonts',
        to: 'fonts/[path][name].[ext]'
    })

    /*
     * ENTRY CONFIG
     *
     * Add 1 entry for each "page" of your app
     * (including one that's included on every page - e.g. "app")
     *
     * Each entry will result in one JavaScript file (e.g. app.js)
     * and one CSS file (e.g. app.css) if you JavaScript imports CSS.
     */
    .addEntry('app', [
        // Scripts
        './node_modules/jquery/dist/jquery.js',
        './node_modules/bootstrap/dist/js/bootstrap.js',
        './node_modules/select2/dist/js/select2.js',
        './node_modules/chart.js/dist/Chart.js',
        './assets/vendor/jquery.i18n/jquery.i18n.dist.js',
        './assets/vendor/bootstrap-typeahead.js',
        './assets/js/common/core_extensions.js',
        './assets/js/common/application.js',
        './assets/js/common/contributions-lists.js',
        './assets/js/adminstats.js',
        './assets/js/articleinfo.js',
        './assets/js/authorship.js',
        './assets/js/autoedits.js',
        './assets/js/blame.js',
        './assets/js/categoryedits.js',
        './assets/js/editcounter.js',
        './assets/js/globalcontribs.js',
        './assets/js/pages.js',
        './assets/js/topedits.js',

        // Stylesheets
        './node_modules/bootstrap/dist/css/bootstrap.css',
        './node_modules/select2/dist/css/select2.css',
        './assets/css/application.scss',
        './assets/css/articleinfo.scss',
        './assets/css/autoedits.scss',
        './assets/css/blame.scss',
        './assets/css/categoryedits.scss',
        './assets/css/editcounter.scss',
        './assets/css/home.scss',
        './assets/css/meta.scss',
        './assets/css/pages.scss',
        './assets/css/topedits.scss',
        './assets/css/responsive.scss'
    ])

    // When enabled, Webpack "splits" your files into smaller pieces for greater optimization.
    .splitEntryChunks()

    // will require an extra script tag for runtime.js
    // but, you probably want this, unless you're building a single-page app
    .enableSingleRuntimeChunk()

    // Other options.
    .enableSassLoader()
    .cleanupOutputBeforeBuild()
    .enableBuildNotifications()
    .enableSourceMaps(!Encore.isProduction())
    .enableVersioning(Encore.isProduction())

    // enables @babel/preset-env polyfills
    .configureBabelPresetEnv((config) => {
        config.useBuiltIns = 'usage';
        config.corejs = 3;
    })
;

module.exports = Encore.getWebpackConfig();
