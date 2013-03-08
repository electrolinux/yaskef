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
    $nstr=0;
    $strings=array();
    foreach ($finder as $file) {
        $s = file_get_contents($file);
        // only found in templates
        if ($isTwig($file)) {
            // 'single quote'|trans
            if (preg_match_all("/{{ '([^|}]*)'\|\s*trans }}/s",$s,$matches)) {
                //print_r($matches[1]);
                foreach($matches[1] as $t) {
                    $nstr++;
                    if (!in_array($t,$strings)) {
                        $strings[]=$t;
                    }
                }
            }
            // "double quotes"|trans
            if (preg_match_all('/{{ "([^|}]*)"\|\s*trans }}/s',$s,$matches)) {
                //print_r($matches[1]);
                foreach($matches[1] as $t) {
                    $nstr++;
                    if (!in_array($t,$strings)) {
                        $strings[]=$t;
                    }
                }
            }
            // app.translator.trans('single_quote_demo'...
            if (preg_match_all("/\bapp.translator.trans\('((?U)[^']*)'(?U).*\)/s",$s,$matches)) {
                //print_r($matches[1]);
                foreach($matches[1] as $t) {
                    $nstr++;
                    if (!in_array($t,$strings)) {
                        $strings[]=$t;
                    }
                }
            }
            // app.translator.trans("double_quote_demo"...
            if (preg_match_all('/\bapp.translator.trans\("((?U)[^"]*)"(?U).*\)/s',$s,$matches)) {
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
            if (preg_match_all("/app\[(?:'|\")translator(?:'|\")\]->trans\('((?U)[^']*)'(?U).*\)/s",$s,$matches)) {
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
            if (preg_match_all('/app\[(?:\'|")translator(?:\'|")\]->trans\("((?U)[^"]*)"(?U).*\)/s',$s,$matches)) {
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
        $form->bindRequest($app['request']);

        if ($form->isValid()) {
            $translated = $form->get('translations')->getData();
            // TODO: check there is no ':' in translations either
            // TODO: remove not translated
            // TODO: ask confirmation and/or make a backup before replacing the file !!!
            try {
                $filename = __DIR__."/../resources/locales/$locale.yml";
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
                    $app['session']->setFlash('success', $msg);
                } else {
                    $msg = $app['translator']->trans("Can't open file '%filename%' in write mode.",array(
                        '%filename%' => $filename
                        ));
                    throw new \Exception($msg);
                }
            } catch (\Exception $e) {
                $app['session']->setFlash('error', $e->getMessage());
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
        $form->bindRequest($app['request']);

        if ($form->isValid()) {

            $password = $form->get('password')->getData();
            $encoded = $app['security.encoder.digest']->encodePassword($password, '');
            $app['session']->setFlash('success', 'Encoded: ' . $encoded);
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
 * profile
 *--------------------------------------------------------------------*/
$app->match('/{_locale}/profile', function() use ($app) {
    $pb = $app['pastebin'];
    $user = $app['security']->getToken();
    $data = array(
        'pb_username' => $pb->getUsername(),
        'pb_password' => $pb->getPassword(),
        'pb_api_key'  => $pb->getApiKey(),
        'pb_exposure' => $pb->getExposure(),
        'pb_expiration' => $pb->getExpiration(),
    );
    //0=public 1=unlisted 2=private
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

    $form = $app['form.factory']->createBuilder('form',$data)
        ->add('password', 'password',array('required'=>false))
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
        $form->bindRequest($app['request']);

        if ($form->isValid()) {
            // set and redirect
            $password = $form->get('password')->getData();
            if (!empty($password)) {
                $encoded = $app['security.encoder.digest']->encodePassword($password, '');
                $app['db']->update('users',array('password'=>$encoded),
                    array('username' => $user->getUsername())
                );
                $app['session']->setFlash('success', $app['translator']->trans('Password changed'));
            }
            $changed = false;
            foreach(array('username','password','api_key','exposure','expiration') as $key) {
                $$key = $form->get("pb_$key")->getData();
                if ($data["pb_$key"] != $$key) {
                    $changed = true;
                }
            }
            if ($changed) {
                $app['pastebin']->updateUser($username,$password,$api_key,$exposure,$expiration);
                $app['session']->setFlash('success', $app['translator']->trans('Modifications saved'));
            } else {
                $app['session']->setFlash('info', $app['translator']->trans('No pastebin.com profile infos changed'));
            }
            return $app->redirect($app['url_generator']->generate('homepage'));
        } else {
            $app['session']->setFlash('error', $app['translator']->trans('Error processing your data !!'));
        }
    }


    return $app['twig']->render('profile.html.twig', array(
        'active' => 'profile',
        'page_title' => 'Yaskef profile page',
        'user' => $user,
        'pastebin' => $app['pastebin'],
        'form' => $form->createView()
        )
    );
})
->bind('profile');

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
        $form->bindRequest($app['request']);

        if ($form->isValid()) {
            // set and redirect
            $name = $form->get('name')->getData();
            $lang = $form->get('lang')->getData();
            $snippet->deleteSnippet($name,$lang);
            $msg = $app['translator']->trans("%lang% Snippet named '%name%' deleted",array(
                '%lang%' => $lang,
                '%name%'=>$name
            ));
            $app['session']->setFlash('success', $msg);
            return $app->redirect($app['url_generator']->generate($lang));
        } else {
            $app['session']->setFlash('error', $app['translator']->trans('Error deleting snippet !!'));
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
        $app['session']->setFlash('info',"<b>$msg</b>:<br/>$out");
    }
    if (!empty($err)) {
        $msg1 = $app['translator']->trans('Files not deleted');
        $msg2 = $app['translator']->trans('Cache not completely cleared');
        $app['session']->setFlash('error',"<b>$msg1</b><br/>:$err");
        $app['session']->setFlash('warn', $msg2);
    } else {
        $app['session']->setFlash('success', $app['translator']->trans('Cache have been cleared'));
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
