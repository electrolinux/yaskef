<?php

namespace Oclane;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Finder\Finder;

//use Oclane\Interpreter;
//use Oclane\Snippet;

$app->mount('/code', new CodeController());

/*--------------------------------------------------------------------*
 * home (redirect to /code)
 *--------------------------------------------------------------------*/
$app->match('/', function() use ($app) {
    return $app->redirect($app['url_generator']->generate('php'));
})->bind('homepage');

/*--------------------------------------------------------------------*
 * prep-translations
 *--------------------------------------------------------------------*/
$app->get('/prep-translations', function() use ($app) {

    $twig_finder = new Finder();
    $twig_finder->files()
        ->ignoreVCS(true)
        ->name('*.html.twig')
        ->name('*.php')
        ->notName('*~')
        ->in(__DIR__.'/../resources')
        ->in(__DIR__)
    ;
    $nstr=0;
    $strings=array();
    foreach ($twig_finder as $file) {
        //$content .= '# ' . basename($file) . "\n";
        $s = file_get_contents($file);
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
        // app.translator.trans('single quote'...
        if (preg_match_all("/\bapp.translator.trans\('((?U)[^']*)'(?U).*\)/s",$s,$matches)) {
            //print_r($matches[1]);
            foreach($matches[1] as $t) {
                $nstr++;
                if (!in_array($t,$strings)) {
                    $strings[]=$t;
                }
            }
        }
        // app.translator.trans("double quote"...
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
    $php_finder = new Finder();
    $php_finder->files()
        ->ignoreVCS(true)
        ->name('*.php')
        ->notName('*~')
        ->in(__DIR__)
    ;
    $php_strings=array();
    foreach ($php_finder as $file) {
        $s = file_get_contents($file);
        // $app ['translator'] -> trans('single quote form 1...
        // $app ["translator"] -> trans('single quote form 2...
        if (preg_match_all("/app\[(?:'|\")translator(?:'|\")\]->trans\('((?U)[^']*)'(?U).*\)/s",$s,$matches)) {
            //print_r($matches[1]);
            foreach($matches[1] as $t) {
                $nstr++;
                //$t .= ' (1)';
                if (!in_array($t,$php_strings)) {
                    $php_strings[]=$t;
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
                if (!in_array($t,$php_strings)) {
                    $php_strings[]=$t;
                }
            }
        }
    }
    sort($strings);
    sort($php_strings);
    return $app['twig']->render('prep-translations.html.twig', array(
        'nstr' => $nstr,
        'strings' => $strings,
        'php_strings' => $php_strings,
    ));
})
->bind('prep-translations')
;

//=====================
/*--------------------------------------------------------------------*
 * encode
 *--------------------------------------------------------------------*/
$app->match('/encode', function() use ($app) {
    $form = $app['form.factory']->createBuilder('form')
        ->add('password', 'text', array(
            'label'       => 'Password',
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
$app->match('/login', function() use ($app) {
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
$app->match('/logout', function() use ($app) {
    $app['session']->clear();

    return $app->redirect($app['url_generator']->generate('homepage'));
})->bind('logout');

/*--------------------------------------------------------------------*
 * phpinfo
 *--------------------------------------------------------------------*/
$app->get('/phpinfo', function() use ($app) {

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
 * TODO: doc
 *--------------------------------------------------------------------*/
$app->get('/doc', function() use ($app) {
    return $app['twig']->render('doc.html.twig');

})
->bind('doc');

/*--------------------------------------------------------------------*
 * profile
 *--------------------------------------------------------------------*/
$app->match('/profile', function() use ($app) {
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
            'label' => 'Paste exposure'
        ))
        ->add('pb_expiration','choice',  array(
            'choices'  => $expiration_choices,
            'label' => 'Paste expiration'
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
                $app['session']->setFlash('success', "Password changed");
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
                $app['session']->setFlash('success', "Modifications saved");
            } else {
                $app['session']->setFlash('info', "No pastebin.com profile infos changed");
            }
            return $app->redirect($app['url_generator']->generate('homepage'));
        } else {
            $app['session']->setFlash('error', "Error processing your data !!");
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
$app->match('/delete_snippet/{lang}/{name}', function($lang,$name) use ($app) {
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
            $app['session']->setFlash('success', "$lang Snippet named $name deleted");
            return $app->redirect($app['url_generator']->generate($lang));
        } else {
            $app['session']->setFlash('error', "Error deleting snippet !!");
        }
    }


    return $app['twig']->render('del_snippet.html.twig', array(
        'active' => $lang,
        'page_title' => 'Confirm deleting a snippet',
        'name' => $name,
        'lang' => $lang,
        'form' => $form->createView()
        )
    );


})
->bind('del_snippet');

/*--------------------------------------------------------------------*
 * clear the cache
 *--------------------------------------------------------------------*/
$app->get('/clearcache', function() use ($app) {
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
        $app['session']->setFlash('info',"<b>Files deleted</b><br/>:$out");
    }
    if (!empty($err)) {
        $app['session']->setFlash('error',"<b>Files not deleted</b><br/>:$err");
        $app['session']->setFlash('warn', "Cache not completely cleared");
    } else {
        $app['session']->setFlash('success', "Cache have been cleared");
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
