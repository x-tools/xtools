var Encore = require('@symfony/webpack-encore');

Encore

    // Directory where compiled assets will be stored.
    .setOutputPath('./public/assets/')

    // Public URL path used by the web server to access the output path.
    .setPublicPath('/assets/')

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
        './assets/vendor/jquery.i18n/jquery.i18n.dist.js',
        './assets/vendor/Chart.min.js',
        './assets/vendor/bootstrap-typeahead.js',
        './assets/js/core_extensions.js',
        './assets/js/application.js',
        './assets/js/adminstats.js',
        './assets/js/articleinfo.js',
        './assets/js/authorship.js',
        './assets/js/autoedits.js',
        './assets/js/categoryedits.js',
        './assets/js/editcounter.js',
        './assets/js/pages.js',
        './assets/js/topedits.js',
        './assets/css/_mixins.scss',
        './assets/css/_rtl.scss',
        './assets/css/application.scss',
        './assets/css/about.scss',
        './assets/css/articleinfo.scss',
        './assets/css/autoedits.scss',
        './assets/css/categoryedits.scss',
        './assets/css/editcounter.scss',
        './assets/css/home.scss',
        './assets/css/meta.scss',
        './assets/css/pages.scss',
        './assets/css/topedits.scss'
    ])

    // Other options.
    .enableSassLoader()
    .cleanupOutputBeforeBuild()
    .disableSingleRuntimeChunk()
    .enableSourceMaps(!Encore.isProduction())
    .enableVersioning(Encore.isProduction())

    // enables @babel/preset-env polyfills
    .configureBabel(() => {}, {
        useBuiltIns: 'usage',
        corejs: 3
    })
;

module.exports = Encore.getWebpackConfig();
