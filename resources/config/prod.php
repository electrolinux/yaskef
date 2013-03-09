<?php

// You can define your prefered default locale here
$app['locale'] = 'fr';
$app['session.default_locale'] = $app['locale'];
$app['locales']=array('en','fr','es','de');
$app['translator.messages'] = array(
    'fr' => __DIR__.'/../resources/locales/fr.yml',
);

// Cache
$app['cache.path'] = __DIR__ . '/../cache';

// Http cache
$app['http_cache.cache_dir'] = $app['cache.path'] . '/http';

// Twig cache
$app['twig.options.cache'] = $app['cache.path'] . '/twig';

// Assetic
$app['assetic.enabled'] = true;
$app['assetic.path_to_cache'] = $app['cache.path'] . '/assetic' ;
$app['assetic.path_to_web'] = __DIR__ . '/../../web/assets';
$app['assetic.input.path_to_assets'] = __DIR__ . '/../assets';

$app['assetic.input.path_to_css'] = $app['assetic.input.path_to_assets'] . '/less/style.less';
$app['assetic.output.path_to_css'] = 'css/styles.css';
$app['assetic.input.path_to_js'] = array(
    $app['assetic.input.path_to_assets'] . '/js/bootstrap.min.js',
    $app['assetic.input.path_to_assets'] . '/js/script.js',
);
$app['assetic.output.path_to_js'] = 'js/scripts.js';

// Doctrine (db)
$dbconf = __DIR__.'/db_config.php';
if (file_exists($dbconf)) {
    require_once($dbconf);
} else {
    require_once("$dbconf.dist");
}
$app['db.options'] = array(
    'driver'    => $db_driver,
    'path'      => $db_path,
    'host'      => $db_host,
    'dbname'    => $db_name,
    'user'      => $db_user,
    'password'  => $db_password,
);

// Security
$reglocales=implode('|',$app['locales']);

// Installation firewall: we only check the client Ip
$ReqInstallMatch = new \Symfony\Component\HttpFoundation\RequestMatcher();
$ReqInstallMatch->matchIp('127.0.0.0/8');
$app['install_firewalls'] = array(
    'default'   => array('pattern' => $ReqInstallMatch,'anonymous'=>true),
    );
$app['install_access_rules'] = array(
    array('^/$', ''),
    array('^/('.$reglocales.')/install/.*$',''),
);
// prod firewall, need login
$app['prod_firewalls'] = array(
    'login' => array('pattern' => '^/(fr|en)/login$','anonymous'=>true), // Example of an url available as anonymous user
    'default' => array(
        'pattern' => '^.*$',
        'form' => array(
            'login_path' => 'login',
            'check_path' => 'login_check',
            'default_target_path' => '/{_locale}',
        ),
        'logout' => array('logout_path' => 'logout'), // url to call for logging out

        'users' => $app->share(function() use ($app) {
            return new Oclane\UserProvider($app['db']);
        }),
    ),
);

$app['prod_access_rules'] = array(
    array('^/login$', ''), // This url is available as anonymous user
    array('^/.+$', 'ROLE_USER'),
);

$app['security.role_hierarchy'] = array(
    'ROLE_ADMIN' => array('ROLE_USER', 'ROLE_ALLOWED_TO_SWITCH'),
);

$app['pastebin'] = $app->share(function() use ($app) {
    return new Oclane\PasteBin($app);
});

// needed somewhere in stolen code don't remember where
define('CR',"\r");
define('LF',"\n");
define('CRLF',CR . LF);
