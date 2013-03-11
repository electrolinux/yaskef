<?php

namespace Oclane;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Finder\Finder;

if (!Installer::checkDatabase($app)) {
    $app['debug'] = true;
    $app->mount('/install', new Installer());
    $app->match('/{_locale}', function() use ($app) {
        return $app->redirect($app['url_generator']->generate('install',array(
            '_locale'=> $app['request']->getLocale()
        )));
    })
    ->value('_locale','en')
    ->bind('homepage');
    /*--------------------------------------------------------------------*
     * login
     *--------------------------------------------------------------------*/
    $app->match('/{_locale}/login', function() use ($app) {
        $request = $app['request'];

        return $app['twig']->render('login.html.twig', array(
            'error' => $app['security.last_error']($request),
            'last_username' => $app['session']->get('_security.last_username'),
        ));
    })
    ->bind('login');

    /*--------------------------------------------------------------------*
     * logout
     *--------------------------------------------------------------------*/
    $app->match('/{_locale}/logout', function() use ($app) {
        $app['session']->clear();

        return $app->redirect($app['url_generator']->generate('homepage'));
    })
    ->bind('logout');
    return $app;
}
//only if not installing
//$app->register(new Silex\Provider\SecurityServiceProvider());

$app->mount('/code', new CodeController());
$app->mount('/profile', new ProfileController());

$app['controllers']
    ->value('_locale','en')
    ->assert('_locale',implode('|',$app['locales']))
    ;

/*--------------------------------------------------------------------*
 * home (redirect to /code)
 *--------------------------------------------------------------------*/
$app->match('/{_locale}', function() use ($app) {
    return $app->redirect($app['url_generator']->generate('php',array(
        '_locale'=> $app['request']->getLocale()
    )));
})
->bind('homepage');

/*--------------------------------------------------------------------*
 * translations
 *--------------------------------------------------------------------*/
$app->match('/{_locale}/translations', function() use ($app) {

    $isPhp = function($fname) {
        return pathinfo(strtolower($fname), PATHINFO_EXTENSION) == 'php';
    };

    $isTwig = function($fname) {
        return pathinfo(strtolower($fname), PATHINFO_EXTENSION) == 'twig';
    };

    $finder = new Finder();
    $finder->files()
        ->ignoreVCS(true)
        ->name('*.html.twig')
        ->name('*.php')
        ->notName('*~')
        ->in(__DIR__.'/../resources')
        ->in(__DIR__)
    ;
    // regex from: stackoverflow.com/questions/5695240/php-regex-to-ignore-escaped-quotes-within-quotes
    $re_dq = '/"[^"\\\\]*(?:\\\\.[^"\\\\]*)*"/s';
    $re_sq = "/'[^'\\\\]*(?:\\\\.[^'\\\\]*)*'/s";
    $nstr=0;
    $strings=array();
    foreach ($finder as $file) {
        $s = file_get_contents($file);
        // only found in templates
        if ($isTwig($file)) {
            // 'single quote'|trans
            //if (preg_match_all("/{{ '([^|}]*)'\|\s*trans(?U).*}}/s",$s,$matches)) {
            if (preg_match_all("/{{ '([^'\\\\]*(?:\\\\.[^'\\\\]*)*)'\s*\|\s*trans(?U).*}}/s",$s,$matches)) {
                //print_r($matches[1]);
                foreach($matches[1] as $t) {
                    $nstr++;
                    if (!in_array($t,$strings)) {
                        $strings[]=$t;
                    }
                }
            }
            // "double quotes"|trans
            //if (preg_match_all('/{{ "([^|}]*)"\|\s*trans(?U).*}}/s',$s,$matches)) {
            if (preg_match_all('/{{ "([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"\s*\|\s*trans(?U).*}}/s',$s,$matches)) {
                //print_r($matches[1]);
                foreach($matches[1] as $t) {
                    $nstr++;
                    if (!in_array($t,$strings)) {
                        $strings[]=$t;
                    }
                }
            }
            // app.translator.trans('single_quote_demo'...
            if (preg_match_all("/\bapp.translator.trans\(\s*'([^'\\\\]*(?:\\\\.[^'\\\\]*)*)'(?U).*\)/s",$s,$matches)) {
                //print_r($matches[1]);
                foreach($matches[1] as $t) {
                    $nstr++;
                    if (!in_array($t,$strings)) {
                        $strings[]=$t;
                    }
                }
            }
            // app.translator.trans("double_quote_demo"...
            if (preg_match_all('/\bapp.translator.trans\(\s*"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"(?U).*\)/s',$s,$matches)) {
                //print_r($matches[1]);
                foreach($matches[1] as $t) {
                    $nstr++;
                    if (!in_array($t,$strings)) {
                        $strings[]=$t;
                    }
                }
            }
        }
        // only found in php
        if ($isPhp($file)) {
            // strip multi-lines comments
            $s = preg_replace("#(/\*(?U)[^/]*\*/)#",'',$s);
            // strip until-eof comments
            $s = preg_replace("#(//(?U)[^\r\n]*\n)#",'',$s);

            // $app ['translator'] -> trans('single quote form 1...
            // $app ["translator"] -> trans('single quote form 2...
            if (preg_match_all("/app\[(?:'|\")translator(?:'|\")\]->trans\('([^'\\\\]*(?:\\\\.[^'\\\\]*)*)'(?U).*\)/s",$s,$matches)) {
                //print_r($matches[1]);
                foreach($matches[1] as $t) {
                    $nstr++;
                    //$t .= ' (1)';
                    if (!in_array($t,$strings)) {
                        $strings[]=$t;
                    }
                }
            }
            // $app ['translator'] -> trans("double quote...
            // $app ["translator"] -> trans("double quote form 2...
            //if (preg_match_all('/app\[\'translator\'\]->trans\("([^"]*)".*\)/s',$s,$matches)) {
            if (preg_match_all('/app\[(?:\'|")translator(?:\'|")\]->trans\("([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"(?U).*\)/s',$s,$matches)) {
                //print_r($matches[1]);
                foreach($matches[1] as $t) {
                    $nstr++;
                    //$t .= ' (2)';
                    if (!in_array($t,$strings)) {
                        $strings[]=$t;
                    }
                }
            }
        }
    }
    sort($strings);
    $locale = $app['request']->getLocale();
    $translations = "";
    foreach($strings as $idx=>$key) {
        $key = stripslashes($key);
        if (strpos(':',$key) !== false) {
            $translations .= ("# WARNING!! string with column in it can't be translated with yaml -- ($key)\n");
        } elseif( ($trans = $app['translator']->trans($key)) == $key ) {
            $translations .= ("$key: ## $trans **\n");
        } else {
            $translations .= ("$key: " . $app['translator']->trans($key) . "\n");
        }
    }
    //$translations = addslashes($translations);
    $form = $app['form.factory']->createBuilder('form',array('translations' => $translations))
        ->add('translations', 'textarea', array(
                'label'      => $app['translator']->trans('Translations'),
                'attr' => array(
                    'rows'=>10,
                    'style'=>'width:98%',
                    'class'=>'CodeMirror-scroll' )
            ))
        ->getForm();

    if ('POST' === $app['request']->getMethod()) {
        $form->bind($app['request']);

        if ($form->isValid()) {
            $translated = $form->get('translations')->getData();
            // TODO: check there is no ':' in translations either
            // TODO: remove not translated
            // TODO: ask confirmation and/or make a backup before replacing the file !!!
            try {
                $filename = realpath(__DIR__.'/../resources/locales') . "/$locale.yml";
                if (!is_writable($filename)) {
                    $msg = $app['translator']->trans("Can't open file '%filename%' in write mode.",array(
                        '%filename%' => $filename
                        ));
                    throw new \Exception($msg);
                }
                $fp = fopen($filename,'w');
                if ($fp) {
                    fwrite($fp,$translated);
                    fclose($fp);
                    $msg=$app['translator']->trans("Translation '%locale%' saved in %filename%.",array(
                        '%locale%'=>$locale,'%filename%',$filename
                    ));
                    $app['session']->getFlashBag()->add('success', $msg);
                } else {
                    $msg = $app['translator']->trans("Can't open file '%filename%' in write mode.",array(
                        '%filename%' => $filename
                        ));
                    throw new \Exception($msg);
                }
            } catch (\Exception $e) {
                $app['session']->getFlashBag()->add('error', $e->getMessage());
                $msg = $app['translator']->trans("Please copy/paste the content below to the file '%filename%', or make it writable by the web server user.",array(
                    '%filename%' => $filename
                    ));
                $app['session']->getFlashBag()->add('warning', $msg);
            }
        }
    }

    return $app['twig']->render('translations.html.twig', array(
        'nstr' => $nstr,
        'form' => $form->createView(),
    ));
})
->bind('translations')
;

/*--------------------------------------------------------------------*
 * encode
 *--------------------------------------------------------------------*/
$app->match('/{_locale}/encode', function() use ($app) {
    $form = $app['form.factory']->createBuilder('form')
        ->add('password', 'text', array(
            'label'       => $app['translator']->trans('Password'),
            'constraints' => array(
                new Assert\NotBlank(),
            ),
        ))
        ->getForm()
    ;

    if ('POST' === $app['request']->getMethod()) {
        $form->bind($app['request']);

        if ($form->isValid()) {

            $password = $form->get('password')->getData();
            $encoded = $app['security.encoder.digest']->encodePassword($password, '');
            $app['session']->getFlashBag()->add('success', 'Encoded: ' . $encoded);
        }
    }

    return $app['twig']->render('encode.html.twig', array('form' => $form->createView()));
})
->bind('encode');

/*--------------------------------------------------------------------*
 * login
 *--------------------------------------------------------------------*/
$app->match('/{_locale}/login', function() use ($app) {
    $request = $app['request'];

    return $app['twig']->render('login.html.twig', array(
        'error' => $app['security.last_error']($request),
        'last_username' => $app['session']->get('_security.last_username'),
    ));
})
->bind('login');

/*--------------------------------------------------------------------*
 * logout
 *--------------------------------------------------------------------*/
$app->match('/{_locale}/logout', function() use ($app) {
    $app['session']->clear();

    return $app->redirect($app['url_generator']->generate('homepage'));
})
->bind('logout');

/*--------------------------------------------------------------------*
 * phpinfo
 *--------------------------------------------------------------------*/
$app->get('/{_locale}/phpinfo', function() use ($app) {

    ob_start();
    phpinfo();
    $info = ob_get_contents();
    ob_end_clean();
    $url = $app['url_generator']->generate('homepage');
    $start='<body><div style="padding:4px">' .
    '<a href="' . $url . '">Back</a></div>';
    //$info = str_replace('<body>',$start,$info);
    $parts = explode('<body>',$info);
    $parts = explode('</body>',$parts[1]);
    $info = preg_replace('#,\b#',', ',$parts[0]);

    return $app['twig']->render('phpinfo.html.twig', array(
        'info' => $info,
        )
    );

})
->bind('phpinfo');

/*--------------------------------------------------------------------*
 * doc
 *--------------------------------------------------------------------*/
$app->get('/{_locale}/doc', function() use ($app) {
    $locale = $app['translator']->getLocale();
    if ($locale == 'fr') {
        return $app['twig']->render('doc_fr.html.twig');
    }
    return $app['twig']->render('doc.html.twig');

})
->bind('doc');

/*--------------------------------------------------------------------*
 * delete_snippet
 *--------------------------------------------------------------------*/
//$app->match('/{_locale}/delete_snippet/{lang}/{name}', function($lang,$name) use ($app) {
$app->match('/{_locale}/delete_snippet', function() use ($app) {
    if ( ($name = $app['request']->get('name')) === null) {
        throw new \Exception("delete_snippet() missing arg 'name'");
    }
    if ( ($lang = $app['request']->get('lang')) === null) {
        throw new \Exception("delete_snippet() missing arg 'lang'");
    }

    if ($lang == 'php') {
        $snippet = new Snippet($app['db']);
    } elseif ($lang == 'js') {
        $snippet = new SnippetJs($app['db']);
    } elseif ($lang == 'sql') {
        $snippet = new SnippetSql($app['db']);
    } else {
        throw new \Exception("delete() Unknow language: $lang");
    }
    $data = array(
        'lang' => $lang,
        'name' => $name,
    );
    $form = $app['form.factory']->createBuilder('form',$data)
        ->add('name', 'hidden')
        ->add('lang','hidden')
        ->getForm()
    ;
    if ('POST' === $app['request']->getMethod()) {
        $form->bind($app['request']);

        if ($form->isValid()) {
            // set and redirect
            $name = $form->get('name')->getData();
            $lang = $form->get('lang')->getData();
            $snippet->deleteSnippet($name,$lang);
            $msg = $app['translator']->trans("%lang% Snippet named '%name%' deleted",array(
                '%lang%' => $lang,
                '%name%'=>$name
            ));
            $app['session']->getFlashBag()->add('success', $msg);
            return $app->redirect($app['url_generator']->generate($lang));
        } else {
            $app['session']->getFlashBag()->add('error', $app['translator']->trans('Error deleting snippet !!'));
        }
    }


    return $app['twig']->render('del_snippet.html.twig', array(
        'active' => $lang,
        'page_title' => $app['translator']->trans('Confirm deleting a snippet'),
        'name' => $name,
        'lang' => $lang,
        'form' => $form->createView(),
        'DEBUG_CALL' => true
        )
    );


})
->bind('del_snippet');

/*--------------------------------------------------------------------*
 * reload_snippets
 *--------------------------------------------------------------------*/
$app->match('/{_locale}/reload_snippets', function() use ($app) {
    $schema = $app['db']->getSchemaManager();
    if (!$schema->tablesExist('snippets')) {
        throw new \Exception("table snippet don't exists !!");
    }
    $db = $app['db'];
    $db->executeQuery('DELETE FROM snippets');

    $finder = new Finder();
    $finder->files()
        ->ignoreVCS(true)
        ->name('*.txt')
        ->notName('*~')
        ->in(__DIR__.'/../resources/snippets')
    ;
    $php = new Snippet($db);
    $sql = new SnippetSql($db);
    $js = new SnippetJs($db);

    foreach ($finder as $file) {
        $name = str_replace('_',' ',str_replace('.txt','',basename($file)));
        $lang = basename(dirname($file));
        $code = file_get_contents($file);
        $html='';
        $comment = '';
        if (preg_match('/^(.*)BEGIN_HTML(.*)END_HTML(.*)$/is',$code,$matches)) {
            $html = $matches[2];
            $code = $matches[1] . $matches[3];
        }
        if (preg_match('/^(.*)BEGIN_COMMENT(.*)END_COMMENT(.*)$/is',$code,$matches)) {
            $comment = $matches[2];
            $code = $matches[1] . $matches[3];
        }
        if ($lang == 'php') {
            $php->add($name,$code,$comment,$html);
        } elseif ($lang == 'sql') {
            $sql->add($name,$code,$comment,$html);
        } elseif ($lang = 'js') {
            $js->add($name,$code,$comment,$html);
        }
    }
    $app['session']->getFlashBag()->add('success', $app['translator']->trans(
        'All snippets have been reloaded'));
    return $app->redirect($app['url_generator']->generate('homepage'));
})
->bind('reload_snippets');

/*--------------------------------------------------------------------*
 * clear the cache
 *--------------------------------------------------------------------*/
$app->get('/{_locale}/clearcache', function() use ($app) {
    $out = '';
    $err = '';
    $finder = new Finder();
    $finder->files()
        ->ignoreVCS(true)
        ->name('*')
        ->notName('*~')
        ->in(__DIR__.'/../resources/cache')
    ;
    foreach ($finder as $file) {
        if (unlink($file)) {
            $out .= "$file<br/>";
        } else {
            $err .= "$file</br>";
        }
    }
    if (!empty($out)) {
        $msg = $app['translator']->trans('Files deleted');
        $app['session']->getFlashBag()->add('info',"<b>$msg</b>:<br/>$out");
    }
    if (!empty($err)) {
        $msg1 = $app['translator']->trans('Files not deleted');
        $msg2 = $app['translator']->trans('Cache not completely cleared');
        $app['session']->getFlashBag()->add('error',"<b>$msg1</b><br/>:$err");
        $app['session']->getFlashBag()->add('warn', $msg2);
    } else {
        $app['session']->getFlashBag()->add('success', $app['translator']->trans('Cache have been cleared'));
    }
    return($app->redirect($app['url_generator']->generate('homepage')));
})
->bind('clearcache');

/*--------------------------------------------------------------------*
 * debug pastebin
 *--------------------------------------------------------------------*/
$app->get('/pb', function() use ($app) {
    $tpl="
    <h2>PasteBin debug</h2>
    <ul>
        <li>Username: {{ pb.getUsername() }}</li>
        <li>Password: {{ pb.getPassword() }}</li>
        <li>Api Key : {{ pb.getApiKey() }}</li>
        <li>Api User Key : {{ pb.getApiUserKey() }}
        {% if pb.key_from_session %}<strong>(From session){% endif %}</strong>
        </li>
    </ul>
    <div><a href=\"{{ home }}\">Return to Yaskef</a></div>
    ";
    $env = new \Twig_Environment(new \Twig_Loader_String());
    return $env->render(
        $tpl,
        array(
            "pb" => $app['pastebin'],
            "home" => $app['url_generator']->generate('homepage')
    ));
})
->bind('pb');

/*--------------------------------------------------------------------*
 * error
 *--------------------------------------------------------------------*/
$app->error(function (\Exception $e, $code) use ($app) {
    if ($app['debug']) {
        return;
    }

    switch ($code) {
        case 404:
            $message = 'The requested page could not be found.';
            break;
        default:
            $message = 'We are sorry, but something went terribly wrong.';
    }

    return new Response($message, $code);
});

return $app;
