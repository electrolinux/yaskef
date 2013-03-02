<?php

namespace Oclane;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use Symfony\Component\Validator\Constraints as Assert;

//use Oclane\Interpreter;
//use Oclane\Snippet;

/*--------------------------------------------------------------------*
 * home (php)
 *--------------------------------------------------------------------*/
$app->match('/', function() use ($app) {
    $snippet = new Snippet($app['db']);
    list($options,$snippets) = $snippet->getOptionsList();
    $api_key = $app['pastebin']->getApiUserKey();

    $resultat='';
    $index=0;
    if ( ($name = $app['request']->get('name')) ) {
        $index = array_search($name,array_keys($snippets));
        $index = $index === false ? 0 : $index+1;
    }
    $form = $app['form.factory']->createBuilder('form',array('api_key'=>$api_key))
        ->add('code', 'textarea', array(
                'label'      => 'Code',
                'attr' => array('rows'=>10,'style'=>'width:100%')
        ))
        ->add('pre','checkbox',array(
            'label' => 'Pre-formatted result'
        ))
        ->add('name','text')
        ->add('snippet', 'choice',  array(
            'choices'  => $options,
            'multiple' => false,
            'expanded' => false
        ))
        ->add('api_key','hidden')
        ->getForm()
    ;

    if ('POST' === $app['request']->getMethod()) {
        $form->bindRequest($app['request']);

        if ($form->isValid()) {
            $interp = new Interpreter($app);
            $pre = $form->get('pre')->getData();
            $code = $form->get('code')->getData();
            $key = $form->get('api_key')->getData();
            $save = array_key_exists('save',$_POST);
            $test = array_key_exists('test',$_POST);
            $pastebin = array_key_exists('pastebin',$_POST);
            if ($test) {
                $resultat = $interp->evalPhp($code);
                if (empty($resultat)) {
                    $resultat = '<strong>## Error evaling your code !! (empty result)</strong>';
                }
            } elseif ($save) {
                $name = $form->get('name')->getData();
                if (!empty($name) && !empty($code)) {
                    $snippet->add($name,$code);
                    $resultat="snippet '$name' saved";
                    $app['session']->setFlash('success', $resultat);

                    return $app->redirect($app['url_generator']->generate('homepage',array('name'=>$name)));
                } else {
                    $app['session']->setFlash('error', "Can't save without 'name' and 'code' !!");
                }
            } elseif ($pastebin) {
                $name = $form->get('name')->getData();
                if (!empty($code)) {
                    $pb = $app['pastebin'];
                    //$resultat = $pb->postCode($key,'php',$code,$name);
                    $resultat = $pb->postCode('php',$code,$name);
                    if (preg_match('/^Bad API request/',$resultat)) {
                        $app['session']->setFlash('error', $resultat);
                    } else {
                        $app['session']->setFlash('success', "<a href=\"$resultat\">$resultat</a>");
                    }

                    return $app->redirect($app['url_generator']->generate('homepage'));
                } else {
                    $app['session']->setFlash('error', "Can't paste to pastebin without 'code' !!");
                }
            }
        }
    }
    if (empty($resultat)) {
        $resultat = '<h2>Welcome to yaskef !</h2>';
        $pre = false;
    }
    if ($pre) {
        $resultat = "<pre>\n$resultat\n</pre>";
    }

    $bloc_resultat = "\n<div class=\"result\">$resultat</div>\n";

    return $app['twig']->render('index.html.twig',array(
        'page_title' => 'Versatile interpretor, PHP mode',
        'form' => $form->createView(),
        'snippets' => $snippets,
        'bloc_resultat' => $bloc_resultat,
        'index' => $index,
        'url' => $app['url_generator']->generate('homepage'),
        )
    );
})->bind('homepage');

/*--------------------------------------------------------------------*
 * javascript
 *--------------------------------------------------------------------*/
$app->match('/javascript', function() use ($app) {
    $snippet = new SnippetJs($app['db']);
    list($options,$snippets) = $snippet->getOptionsList();

    $resultat='';
    $index=0;
    if ( ($name = $app['request']->get('name')) ) {
        $index = array_search($name,array_keys($snippets));
        $index = $index === false ? 0 : $index+1;
    }
    $form = $app['form.factory']->createBuilder('form')
        ->add('code', 'textarea', array(
                'label'      => 'Code',
                'attr' => array('rows'=>10,'style'=>'width:100%')
        ))
        ->add('name','text')
        ->add('snippet', 'choice',  array(
            'choices'  => $options,
            'multiple' => false,
            'expanded' => false
        ))
        ->getForm()
    ;

    if ('POST' === $app['request']->getMethod()) {
        $form->bindRequest($app['request']);

        if ($form->isValid()) {
            $interp = new Interpreter($app);
            $code = $form->get('code')->getData();
            $name = $form->get('name')->getData();
            $save = array_key_exists('save',$_POST);
            $del  = array_key_exists('del',$_POST);
            $test = array_key_exists('test',$_POST);
            $pastebin = array_key_exists('pastebin',$_POST);
            if ($test) {
                $resultat = $interp->evalJs($code);
            } elseif ($save) {
                $name = $form->get('name')->getData();
                if (!empty($name) && !empty($code)) {
                    $snippet->add($name,$code);
                    $resultat="snippet '$name' saved";
                    $app['session']->setFlash('success', $resultat);

                    return $app->redirect($app['url_generator']->generate('jscript',
                        array('name'=>$name))
                    );
                } else {
                    $app['session']->setFlash('error', "Can't save without 'name' and 'code' !!");
                }
            } elseif ($pastebin) {
                if (!empty($code)) {
                    $pb = $app['pastebin'];
                    $resultat = $pb->postCode('javascript',$code,$name);
                    if (preg_match('/^Bad API request/',$resultat)) {
                        $app['session']->setFlash('error', $resultat);
                    } else {
                        $app['session']->setFlash('success', "<a href=\"$resultat\">$resultat</a>");
                    }

                    return $app->redirect($app['url_generator']->generate('jscript'));
                } else {
                    $app['session']->setFlash('error', "Can't paste to pastebin without 'code' !!");
                }
            } elseif ($del) {
                return $app->redirect($app['url_generator']->generate('del_snippet',
                    array('name' => $name,'interp' => 'js')));
            }
         }
    }
    if (empty($resultat)) {
        $resultat = '
            <script type="text/javascript">
                document.write("<h2>Welcome to yaskef !</h2>");
            </script>';
    }
    $bloc_resultat = "\n<div class=\"result\">$resultat</div>\n";

    return $app['twig']->render('index.html.twig',array(
        'active' => 'jscript',
        'page_title' => 'Versatile interpretor, Javascript mode',
        'form' => $form->createView(),
        'snippets' => $snippets,
        'bloc_resultat' => $bloc_resultat,
        'index' => $index,
        'url' => $app['url_generator']->generate('jscript'),
        )
    );
})->bind('jscript');

/*--------------------------------------------------------------------*
 * SQL
 *--------------------------------------------------------------------*/
$app->match('/sql', function() use ($app) {
    $snippet = new SnippetSql($app['db']);
    list($options,$snippets) = $snippet->getOptionsList();

    $resultat='';
    $index=0;
    if ( ($name = $app['request']->get('name')) ) {
        $index = array_search($name,array_keys($snippets));
        $index = $index === false ? 0 : $index+1;
    }
    $form = $app['form.factory']->createBuilder('form')
        ->add('code', 'textarea', array(
                'label'      => 'Code',
                'attr' => array('rows'=>10,'style'=>'width:100%')
        ))
        ->add('name','text')
        ->add('snippet', 'choice',  array(
            'choices'  => $options,
            'multiple' => false,
            'expanded' => false
        ))
        ->getForm()
    ;

    if ('POST' === $app['request']->getMethod()) {
        $form->bindRequest($app['request']);

        if ($form->isValid()) {
            $interp = new Interpreter($app);
            $code = $form->get('code')->getData();
            $save = array_key_exists('save',$_POST);
            $test = array_key_exists('test',$_POST);
            $pastebin = array_key_exists('pastebin',$_POST);
            if ($test) {
                $resultat = $interp->evalSql($code);
            } elseif ($save) {
                $name = $form->get('name')->getData();
                if (!empty($name) && !empty($code)) {
                    $snippet->add($name,$code);
                    $resultat="snippet '$name' saved";
                    $app['session']->setFlash('success', $resultat);

                    return $app->redirect($app['url_generator']->generate('sql',array('name'=>$name)));
                } else {
                    $app['session']->setFlash('error', "Can't save without 'name' and 'code' !!");
                }
            } elseif ($pastebin) {
                $name = $form->get('name')->getData();
                if (!empty($code)) {
                    $pb = $app['pastebin'];
                    $resultat = $pb->postCode('sql',$code,$name);
                    if (preg_match('/^Bad API request/',$resultat)) {
                        $app['session']->setFlash('error', $resultat);
                    } else {
                        $app['session']->setFlash('success', "<a href=\"$resultat\">$resultat</a>");
                    }

                    return $app->redirect($app['url_generator']->generate('sql'));
                } else {
                    $app['session']->setFlash('error', "Can't paste to pastebin without 'code' !!");
                }
            }
         }
    }
    if (empty($resultat)) {
        $resultat = 'No default result for SQL...';
    }
    $bloc_resultat = "\n<div class=\"result\">$resultat</div>\n";

    return $app['twig']->render('index.html.twig',array(
        'active' => 'sql',
        'page_title' => 'Versatile interpretor, SQL mode',
        'form' => $form->createView(),
        'snippets' => $snippets,
        'bloc_resultat' => $bloc_resultat,
        'index' => $index,
        'url' => $app['url_generator']->generate('sql'),
        )
    );
})->bind('sql');

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
$app->match('/delete_snippet/{interp}/{name}', function($interp,$name) use ($app) {
    if ($interp == 'php') {
        $snippet = new Snippet($app['db']);
        $redirect = $app['url_generator']->generate('homepage');
    } elseif ($interp == 'js') {
        $snippet = new SnippetJs($app['db']);
        $redirect = $app['url_generator']->generate('jscript');
    } elseif ($interp == 'sql') {
        $snippet = new SnippetSql($app['db']);
        $redirect = $app['url_generator']->generate('sql');
    } else {
        throw new \Exception("Unknow interpreter: $interp");
    }
    $data = array(
        'interp' => $interp,
        'name' => $name,
    );
    $form = $app['form.factory']->createBuilder('form',$data)
        ->add('name', 'hidden')
        ->add('interp','hidden')
        ->getForm()
    ;
    if ('POST' === $app['request']->getMethod()) {
        $form->bindRequest($app['request']);

        if ($form->isValid()) {
            // set and redirect
            $name = $form->get('name')->getData();
            $interp = $form->get('interp')->getData();
            $snippet->deleteSnippet($name,$interp);
            $app['session']->setFlash('success', "Snippet $interp named $name deleted");
            return $app->redirect($redirect);
        } else {
            $app['session']->setFlash('error', "Error deleting snippet !!");
        }
    }


    return $app['twig']->render('del_snippet.html.twig', array(
        'active' => $interp,
        'page_title' => 'Confirm deleting a snippet',
        'name' => $name,
        'interp' => $interp,
        'form' => $form->createView()
        )
    );


})
->bind('del_snippet');

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
