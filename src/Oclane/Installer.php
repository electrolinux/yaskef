<?php
/*
 * installer.php : install/configure helper
 */

namespace Oclane;

use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\DBALException;

class Installer implements ControllerProviderInterface
{
    private $app;
    private $tables = array(
        'users',
        'snippets',
        'pastebin',
    );

    public function connect(Application $app)
    {
        $this->app = $app;
        $ctrl =  $app['controllers_factory']
            ->value('_locale','en')
            ->assert('_locale',implode('|',$app['locales']))
            ;


        /*------------------------------------------------------------*
         * index
         *------------------------------------------------------------*/
        $ctrl->match('/{_locale}', function (Application $app, Request $request) {
            return $this->handleRequest($app,$request);
        })
        ->bind('install')
        ;

        return $ctrl;
    }

    public function handleRequest($app,$request)
    {
        $step = $app['request']->query->get('step');
        if (!$step) {
            return $app->redirect($app['url_generator']->generate('install',array('step'=>'dbconfig')));
        }

        switch($step) {
            case 'dbconfig': return $this->stepDbConfig($app);
            case 'dbcreate': return $this->stepCreateDb($app);
            case 'schemaload': return $this->stepLoadSchema($app);
            case 'snippetsload': return $this->stepLoadSnippets($app);
            case 'userconfig': return $this->stepUserConfig($app);
            default:
                die("unknow step: '$step'");
        }
    }

    private function stepDbConfig($app)
    {
        $stepTitle = $app['translator']->trans('Configure Database');
        $step = 'dbconfig';
        $dbparams = $app['db']->getParams();
        $option = function($key,$val=null) use ($dbparams) {
            return isset($dbparams[$key]) ? $dbparams[$key] : $val;
        };
        $data = array(
            'driver'    => $option('driver'),
            'path'      => $option('path'),
            'host'      => $option('host'),
            'dbname'    => $option('dbname'),
            'user'      => $option('user'),
            'password'  => $option('password'),
        );
        $form = $app['form.factory']->createBuilder('form',$data)
            ->add('driver', 'choice', array(
                'choices'   => array(
                    ''=>'Choose a driver',
                    'pdo_sqlite' => 'SQLite3',
                    'pdo_mysql' => 'MySQL',
                    'pdo_mysqli' => 'MySqli'),
                'label'     => $this->app['translator']->trans('Database Driver'),
            ))
            ->add('path','text')
            ->add('host','text')
            ->add('dbname','text')
            ->add('user','text')
            ->add('password','text')
        ->getForm();
        if ('POST' === $app['request']->getMethod()) {
            $form->bind($app['request']);

            if ($form->isValid()) {
                $driver = $form->get('driver')->getData();
                $path   = $form->get('path')->getData();
                $host = $form->get('host')->getData();
                $dbname =  $form->get('dbname')->getData();
                $user = $form->get('user')->getData();
                $password = $form->get('password')->getData();
                if ($this->createDbConfig($app,$driver,$path,$host,$dbname,$user,$password)) {
                    return $app->redirect($app['url_generator']->generate('install',array('step'=>'dbcreate')));
                }
            }
        }
        return $app['twig']->render('install.html.twig',array(
            'page_title' => $app['translator']->trans('Yaskef versatile interpretor installation'),
            'step' => $step,
            'stepTitle' => $stepTitle,
            'message' => '',
            'form' => $form->createView(),
            'url' => $app['url_generator']->generate('install',array('step'=>'dbconfig'))
            )
        );
    }

    private function stepCreateDb($app)
    {
        $form = $app['form.factory']->createBuilder('form')
            ->getForm();
        if('POST' === $app['request']->getMethod()) {
            $form->bind($app['request']);
            if ($form->isValid()) {
                if (!$this->haveDb($app)) {
                    die('no db');
                    if ($this->createDb($app)) {
                        return $app->redirect($app['url_generator']->generate('install',array('step' =>'schemaload')));
                    }
                } else {
                    $msg = $app['translator']->trans("Database already created.");
                    $app['session']->getFlashBag()->add('success',$msg);
                    return $app->redirect($app['url_generator']->generate('install',array('step' => 'schemaload')));
                }
            }
        }
        return $app['twig']->render('install.html.twig',array(
            'page_title' => $app['translator']->trans('Yaskef versatile interpretor installation'),
            'step' => 'databaseok',
            'stepTitle' => $app['translator']->trans('Database configuration created'),
            'message' => 'Click next to create the database',
            'form' => $form->createView(),
            'url' => $app['url_generator']->generate('install',array('step'=>'dbcreate'))
            )
        );
    }

    private function stepLoadSchema($app)
    {
        $form = $app['form.factory']->createBuilder('form')
            ->getForm();
        if('POST' === $app['request']->getMethod()) {
            $form->bind($app['request']);
            if ($form->isValid()) {
                if ($this->loadSchema($app)) {
                    return $app->redirect($app['url_generator']->generate('install',array('step'=>'userconfig')));
                }
            }
        }
        return $app['twig']->render('install.html.twig',array(
            'page_title' => $app['translator']->trans('Yaskef versatile interpretor installation'),
            'step' => 'schemaload',
            'stepTitle' => $app['translator']->trans('Database ready'),
            'message' => 'Click next to create database tables',
            'form' => $form->createView(),
            'url' => $app['url_generator']->generate('install',array('step'=>'schemaload'))
            )
        );
    }

    private function stepUserConfig($app)
    {
        $exposure_choices=array(
            'public', 'unlisted', 'private'
        );
        $expiration_choices = array(
            'N'   => 'Never',
            '10M' => '10 Minutes',
            '1H'  => '1 Hour',
            '1D'  => '1 Day',
            '1M'  => '1 Month'
        );

        $form = $app['form.factory']->createBuilder('form')
            ->add('username', 'text',array('required' => true))
            ->add('password', 'password',array('required'=>true))
            ->add('admin','checkbox')
            ->add('pb_username','text')
            ->add('pb_password','text')
            ->add('pb_api_key','text')
            ->add('pb_exposure','choice',  array(
                'choices'  => $exposure_choices,
                'label' => $app['translator']->trans('Paste exposure')
            ))
            ->add('pb_expiration','choice',  array(
                'choices'  => $expiration_choices,
                'label' => $app['translator']->trans('Paste expiration')
            ))
            ->getForm()
        ;

        if ('POST' === $app['request']->getMethod()) {
            $form->bind($app['request']);

            if ($form->isValid()) {
                // set and redirect
                $username = $form->get('username')->getData();
                $password = $form->get('password')->getData();
                $admin = $form->get('admin')->getData();

                $encoded = $app['security.encoder.digest']->encodePassword($password, '');

                $db = $app['db'];
                $db->insert('users', array(
                    'username' => $username,
                    'password' => $encoded,
                    'roles' => $admin ? 'ROLE_ADMIN' :  'ROLE_USER'
                ));
                $id = $db->lastInsertId();
                /*
                $stmt = $db->executeQuery('SELECT id FROM users WHERE username = ?',array(
                    $username
                ));
                if (!$user = $stmt->fetch()) {
                    throw new UsernameNotFoundException(sprintf('Username "%s" does not exist.', $username));
                }
                $id = $user['id'];
                */

                // pastebin (optional)
                $pb_data = array();
                foreach(array('username','password','api_key','exposure','expiration') as $key) {
                    $$key = $form->get("pb_$key")->getData();
                }

                if ($id and $pb_username and $pb_password and $pb_api_key) {
                    $this->app['db']->insert('pastebin', array(
                        'user_id'  => $id,
                        'username' => $pb_username,
                        'password' => $pb_password,
                        'api_key'  => $pb_api_key,
                        'exposure' => $pb_exposure,
                        'expiration' => $pb_expiration,
                    ));
                }

                $app['session']->getFlashBag()->add('success', $app['translator']->trans('First User configured ok.'));
                return $app->redirect($app['url_generator']->generate('homepage'));
            } else {
                $app['session']->getFlashBag()->add('error', $app['translator']->trans('Error processing your data !!'));
            }
        }

        return $app['twig']->render('install.html.twig',array(
            'page_title' => $app['translator']->trans('Yaskef versatile interpretor installation'),
            'step' => 'userconfig',
            'stepTitle' => $app['translator']->trans('Create the first user'),
            'message' => 'Click next to create the first user',
            'form' => $form->createView(),
            'url' => $app['url_generator']->generate('install',array('step'=>'userconfig'))
            )
        );
    }

    private function createDbConfig($app,$driver,$path,$host,$dbname,$user,$password)
    {
        $errors=array();
        if ($driver == 'pdo_sqlite') {
            // need path
            if (empty($path)) {
                $errors[] = $app['translator']->trans('SQLite driver need a path for the db');
            }
            $dir = dirname($path);
            if (!file_exists($dir) || !is_writable($dir) || (
                file_exists($path) && !is_writable($path))) {
                $errors[] = $app['translator']->trans(
                    "SQLite driver need <b>write access</b> to the db file <b>and</b> his folder '<b>$path</b>'");
            }
        } else {
            // need host,dbname,user at least
            if (empty($host) || empty($dbname) || empty($user)) {
                $errors[] = $app['translator']->trans('MySQL need a least a hostname, a database name and a username');
            }
        }
        // config dir writable ?
        $dir = realpath(__DIR__.'/../../resources/config');
        $cfgname = "$dir/db_config.php";
        if (!file_exists($dir) || !is_writable($dir) || (
            file_exists($cfgname) && !is_writable($cfgname))) {
            $errors[] = "Can't create the database config file <b>'$cfgname'</b>";
        }
        if (empty($errors)) {
            $fp = fopen($cfgname,'w');
            if ($fp) {
                $code = <<< EOM
<?php
\$db_driver      = '$driver';
\$db_path        = '$path';
\$db_host        = '$host';
\$db_user        = '$user';
\$db_name        = '$dbname';
\$db_password    = '$password';
EOM;
                fwrite($fp,$code);
                fclose();
                $app['session']->getFlashBag()->add('success','Database configuration file written');
                return true;
            }
        } else {
            $msg = $app['translator']->transChoice('Error found|%count% Errors found',
                count($errors),array('%count%' => count($errors)));
            $msg .= '<ul>';
            foreach($errors as $error) {
                $msg .= "<li>$error</li>";
            }
            $msg .= '</ul>';
            $app['session']->getFlashBag()->add('error',$msg);
            return  false;
        }
    }

    private function haveDb($app)
    {
        try {
            $schema = $app['db']->getSchemaManager();
            $tables = $schema->listTableNames();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function haveSchema($app)
    {
        try {
            $schema = $app['db']->getSchemaManager();
            if ($schema->tablesExist($this->tables)) {
                return true;
            }
        } catch (\PDOException $e) {
            ;
        } catch (DBALException $e) {
            ;
        }
        return false;
    }

    static public function checkDatabase($app)
    {
        $db = $app['db'];
        $have_user = false;
        $have_schema = false;
        try {
            $res = $db->fetchAll('SELECT * FROM users');
            if (count($res)) {
                $have_user = true;
            }
        } catch (\Exception $e) {
            return false;
        }
        try {
            $res = $db->fetchAll('SELECT * FROM snippets');
        } catch (DBALException $e) {
            //$this->error =  $e->getMessage();
            return false;
        }
        try {
            $res = $db->fetchAll('SELECT * FROM pastebin');
        } catch (DBALException $e) {
            //$this->error =  $e->getMessage();
            return false;
        }
        // seem to have schema
        $have_schema = true;
        return $have_schema && $have_user;
    }

    public function createDb($app)
    {
        $db = $app['db'];

        $params = $db->getParams();
        $name = isset($params['path']) ? $params['path'] : $params['dbname'];

        unset($params['dbname']);

        $tmpConnection = DriverManager::getConnection($params);

        // Only quote if we don't have a path
        if (!isset($params['path'])) {
            $name = $tmpConnection->getDatabasePlatform()->quoteSingleIdentifier($name);
        }

        try {
            $tmpConnection->getSchemaManager()->createDatabase($name);
        } catch (\Exception $e) {
            $msg = $app['translator']->trans(
                "DB Error while creating database '<b>%error%</b>'",
                array('%error%' => $e->getMessage())
                );
            $app['session']->getFlashBag()->add('error',$msg);
            return false;
        }
        $msg = $app['translator']->trans("Database '%name%' created.", array('%name%'=>$name));
        $app['session']->getFlashBag()->add('success',$msg);
        return true;
    }

    private function loadSchema($app)
    {
        $db = $app['db'];
        $schema = require __DIR__.'/../../resources/db/schema.php';

        try {
            foreach ($schema->toSql($app['db']->getDatabasePlatform()) as $sql) {
                $app['db']->exec($sql.';');
            }
        } catch ( \Exception $e) {
            $msg = $app['translator']->trans(
                "DB Error while loading schema '<b>%error%</b>'",
                array('%error%' => $e->getMessage())
                );
            $app['session']->getFlashBag()->add('error',$msg);
            return false;
        }
        $app['session']->getFlashBag()->add('success','Database schema loaded.');
        return true;
    }
}
