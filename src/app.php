<?php

use Oclane\Installer;

use Silex\Provider\FormServiceProvider;
use Silex\Provider\HttpCacheServiceProvider;
use Silex\Provider\MonologServiceProvider;
use Silex\Provider\SessionServiceProvider;
use Silex\Provider\TranslationServiceProvider;
use Silex\Provider\TwigServiceProvider;
use Silex\Provider\UrlGeneratorServiceProvider;
use Silex\Provider\ValidatorServiceProvider;

use Symfony\Component\Translation\Loader\YamlFileLoader;

use SilexAssetic\AsseticExtension;

$app->register(new HttpCacheServiceProvider());

$app->register(new SessionServiceProvider());
$app->register(new ValidatorServiceProvider());
$app->register(new FormServiceProvider());
$app->register(new UrlGeneratorServiceProvider());

$app->register(new TranslationServiceProvider());
$app['translator'] = $app->share($app->extend('translator', function($translator, $app) {
    $translator->addLoader('yaml', new YamlFileLoader());

    $translator->addResource('yaml', __DIR__.'/../resources/locales/fr.yml', 'fr');

    return $translator;
}));

$app->register(new MonologServiceProvider(), array(
    'monolog.logfile' => __DIR__.'/../resources/log/app.log',
    'monolog.name'    => 'app',
    'monolog.level'   => 300 // = Logger::WARNING
));

$app->register(new TwigServiceProvider(), array(
    'twig.options'        => array(
        'cache'            => isset($app['twig.options.cache']) ? $app['twig.options.cache'] : false,
        'strict_variables' => true
    ),
    'twig.form.templates' => array('form_div_layout.html.twig', 'common/form_div_layout.html.twig'),
    'twig.path'           => array(__DIR__ . '/../resources/views')
));

if (isset($app['assetic.enabled']) && $app['assetic.enabled']) {
    $app->register(new AsseticExtension(), array(
        'assetic.options' => array(
            'debug'            => $app['debug'],
            'auto_dump_assets' => $app['debug'],
        ),
        'assetic.filters' => $app->protect(function($fm) use ($app) {
            $fm->set('lessphp', new Assetic\Filter\LessphpFilter());
//            $fm->set('yui_css', new Assetic\Filter\Yui\CssCompressorFilter(
//                $app['assetic.filter.yui_compressor.path']
//            ));
//            $fm->set('cssembed', new Assetic\Filter\CssEmbedFilter(
//                $app['assetic.filter.cssembed.path']
//            ));
//            $fm->set('yui_js', new Assetic\Filter\Yui\JsCompressorFilter(
//                $app['assetic.filter.yui_compressor.path']
//            ));
        }),
        'assetic.assets' => $app->protect(function($am, $fm) use ($app) {
            $am->set('styles', new Assetic\Asset\AssetCache(
                new Assetic\Asset\GlobAsset(
                    $app['assetic.input.path_to_css'],
                    array(
                        $fm->get('lessphp'),
//                        $fm->get('cssembed'),
//                        $fm->get('yui_css')
                    )
                ),
                new Assetic\Cache\FilesystemCache($app['assetic.path_to_cache'])
            ));
            $am->get('styles')->setTargetPath($app['assetic.output.path_to_css']);

            $am->set('scripts', new Assetic\Asset\AssetCache(
                new Assetic\Asset\GlobAsset(
                    $app['assetic.input.path_to_js'],
//                    array($fm->get('yui_js'))
                    array()
                ),
                new Assetic\Cache\FilesystemCache($app['assetic.path_to_cache'])
            ));
            $am->get('scripts')->setTargetPath($app['assetic.output.path_to_js']);
        })
    ));
}

$app->register(new Silex\Provider\DoctrineServiceProvider());

// check db
if (!Installer::checkDatabase($app)) {
    // install
    $app['security.firewalls'] = $app['install_firewalls'];
    $app['security.access_rules'] = $app['install_access_rules'];
} else {
    $app['security.firewalls'] = $app['prod_firewalls'];
    $app['security.access_rules'] = $app['prod_access_rules'];
}
$app->register(new Silex\Provider\SecurityServiceProvider());

return $app;
