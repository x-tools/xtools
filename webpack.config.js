var Encore = require('@symfony/webpack-encore');

Encore

    // Directory where compiled assets will be stored.
    .setOutputPath('./web/assets/')

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
        './app/Resources/assets/vendor/jquery.i18n/jquery.i18n.dist.js',
        './app/Resources/assets/vendor/Chart.min.js',
        './app/Resources/assets/vendor/bootstrap-typeahead.js',
        './app/Resources/assets/js/core_extensions.js',
        './app/Resources/assets/js/application.js',
        './app/Resources/assets/js/articleinfo.js',
        './app/Resources/assets/js/autoedits.js',
        './app/Resources/assets/js/categoryedits.js',
        './app/Resources/assets/js/editcounter.js',
        './app/Resources/assets/js/pages.js',
        './app/Resources/assets/js/topedits.js',
        './app/Resources/assets/css/_mixins.scss',
        './app/Resources/assets/css/_rtl.scss',
        './app/Resources/assets/css/application.scss',
        './app/Resources/assets/css/about.scss',
        './app/Resources/assets/css/articleinfo.scss',
        './app/Resources/assets/css/autoedits.scss',
        './app/Resources/assets/css/categoryedits.scss',
        './app/Resources/assets/css/editcounter.scss',
        './app/Resources/assets/css/home.scss',
        './app/Resources/assets/css/meta.scss',
        './app/Resources/assets/css/pages.scss',
        './app/Resources/assets/css/topedits.scss'
    ])

    // Other options.
    .enableSassLoader()
    .cleanupOutputBeforeBuild()
    .enableSourceMaps(!Encore.isProduction())
    .enableVersioning(Encore.isProduction())
;

module.exports = Encore.getWebpackConfig();
