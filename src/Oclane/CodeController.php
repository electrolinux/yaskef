<?php
/*
 * CodeController.php
 *
 * Copyright 2013 didier Belot <dib@oclane.net>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-1301, USA.
 *
 *
 */


namespace Oclane;

use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CodeController implements ControllerProviderInterface
{
    private $app;

    protected function getSnippet($lang='php')
    {
        if ($lang == 'php') {
            $snippet = new Snippet($this->app['db']);
        } elseif ($lang == 'js') {
            $snippet = new SnippetJs($this->app['db']);
        } elseif ($lang == 'sql') {
            $snippet = new SnippetSql($this->app['db']);
        } else {
            throw new \Exception('getSnippet() Bad language name: ' . $lang);
        }
        return $snippet;
    }

    protected function evalCode($lang,$code,$html='')
    {
        $interp = new Interpreter($this->app);
        $error='';
        if ($lang == 'php') {
            $result = $interp->evalPhp($code,$error);
            if (empty($result)) {
                $result = '(empty result)';
            }
            if (!empty($error)) {
                $this->app['session']->getFlashBag()->add('error', "<b>$error</b>");
            }
        } elseif ($lang == 'js') {
            $result = $interp->evalJs($code,$html);
        } elseif ($lang == 'sql') {
            $result = $interp->evalSql($code);
        } else {
            throw new \Exception('evalCode() Bad language name: ' . $lang);
        }
        return $result;
    }

    protected function createForm($choices,$lang='php')
    {
        if ($lang=='php') {
            $data=array('code' => "<?php\n");
        } else {
            $data = null;
        }
        $form = $this->app['form.factory']->createBuilder('form',$data)
            ->add('code', 'textarea', array(
                    'label'      => $this->app['translator']->trans('Code'),
                    'attr' => array(
                        'rows'=>10,
                        'style'=>'width:98%',
                        'class'=>'CodeMirror-scroll' )
            ));

        if ($lang=='php') {
            $form->add('pre','checkbox',array(
                'label' => $this->app['translator']->trans('Pre-formatted result')
            ));
        }
        if ($lang=='js') {
            $form->add('html','textarea', array(
                'label' => 'html',
                'attr'  => array('rows'=>6,'style'=>'width:98%')
            ));
        }
        $form->add('name','text', array(
                'label' => $this->app['translator']->trans('Save as'),
            ))
            ->add('snippet', 'choice',  array(
                'choices'  => $choices,
                'multiple' => false,
                'expanded' => false,
                'label' => $this->app['translator']->trans('Name'),
            ))
            ->add('comment','textarea', array(
                'label' => $this->app['translator']->trans('Comments'),
            ))
            ;
        return $form->getForm();
    }

    protected function getRedirect($lang,$args=null)
    {
        if (is_array($args) && count($args)){
            return $this->app['url_generator']->generate($lang,$args);
        } else {
            return $this->app['url_generator']->generate($lang);
        }

    }

    protected function handleRequest($app,$lang='php')
    {
        $snippet = $this->getSnippet($lang);
        list($options,$snippets) = $snippet->getOptionsList($app);

        $resultat='';
        $index=0;
        if ( ($name = $app['request']->get('name')) ) {
            $index = array_search($name,array_keys($snippets));
            $index = $index === false ? 0 : $index+1;
        }
        $form = $this->createForm($options,$lang);
        if ('POST' === $app['request']->getMethod()) {
            $form->bind($app['request']);

            if ($form->isValid()) {
                if ($lang == 'php') {
                    $pre = $form->get('pre')->getData();
                } else {
                    $pre = false;
                }
                if ($lang == 'js') {
                    $html = $form->get('html')->getData();
                } else {
                    $html = null;
                }
                $code = $form->get('code')->getData();
                $name = $form->get('name')->getData();
                $comment = $form->get('comment')->getData();
                $save = array_key_exists('save',$_POST);
                $del  = array_key_exists('del',$_POST);
                $test = array_key_exists('test',$_POST);
                $pastebin = array_key_exists('pastebin',$_POST);
                if ($test) {
                    $resultat = $this->evalCode($lang,$code,$html);
                } elseif ($save) {
                    if (!empty($name) && !empty($code)) {
                        $snippet->add($name,$code,$comment,$html);
                        $resultat=$app['translator']->trans("snippet '%name%' saved.",array('%name%'=>$name));
                        $app['session']->getFlashBag()->add('success', $resultat);

                        return $app->redirect($this->getRedirect($lang,array('name'=>$name)));
                    } else {
                        $msg = $app['translator']->trans("Can't save without 'name' and 'code' !!");
                        $app['session']->getFlashBag()->add('error', $msg);
                    }
                } elseif ($pastebin) {
                    if (!empty($code)) {
                        $pb = $app['pastebin'];
                        $resultat = $pb->postCode($lang,$code,$name,$html);
                        if (preg_match('/^Bad API request/',$resultat)) {
                            $app['session']->getFlashBag()->add('error', $resultat);
                        } else {
                            $app['session']->getFlashBag()->add('success', "<a href=\"$resultat\">$resultat</a>");
                        }

                        return $app->redirect($this->getRedirect($lang,array('name'=>$name)));
                    } else {
                        $msg = $app['translator']->trans("Can't paste to pastebin without 'code' !!");
                        $app['session']->getFlashBag()->add('error', $msg);
                    }
                } elseif ($del) {
                    if (empty($name)) {
                        $msg = $app['translator']->trans("Can't delete without 'name' !!");
                        $app['session']->getFlashBag()->add('error', $msg);
                    } else {
                        $url = $app['url_generator']->generate('del_snippet',
                            array(
                                //'_locale' => $app['request']->getLocale(),
                                'lang' => $lang,
                                'name' => $name,
                            ));
                        return $app->redirect($url);
                    }
                }
            }
        }
        if (empty($resultat)) {
            $resultat = $app['translator']->trans('<h2>Welcome to yaskef !</h2>');
            $pre = false;
        }
        if ($pre) {
            $resultat = "<pre>\n$resultat\n</pre>";
        }

        $bloc_resultat = "\n<div class=\"result\">$resultat</div>\n";
        $mode = strtoupper($lang);
        return $app['twig']->render('index.html.twig',array(
            'active' => $lang,
            'page_title' => $app['translator']->trans('Yaskef versatile interpretor, %mode% mode',array('%mode%'=>$mode)),
            'form' => $form->createView(),
            'snippets' => $snippets,
            'bloc_resultat' => $bloc_resultat,
            'index' => $index,
            'url' => $app['url_generator']->generate($lang),
            )
        );
    }

    public function connect(Application $app)
    {
        $this->app = $app;
        $ctrl =  $app['controllers_factory']
            ->value('_locale','en')
            ->assert('_locale',implode('|',$app['locales']))
            ;


        /*------------------------------------------------------------*
         * index (php)
         *------------------------------------------------------------*/
        $ctrl->match('/{_locale}', function (Application $app) { //use($cfg) {
            return $this->handleRequest($app,'php');
        })
        ->bind('php')
        ;

        /*------------------------------------------------------------*
         * js
         *------------------------------------------------------------*/
        $ctrl->match('/{_locale}/js', function (Application $app) { //use($cfg) {
            return $this->handleRequest($app,'js');
        })
        ->bind('js')
        ;

        /*------------------------------------------------------------*
         * sql
         *------------------------------------------------------------*/
        $ctrl->match('/{_locale}/sql', function (Application $app) { //use($cfg) {
            return $this->handleRequest($app,'sql');
        })
        ->bind('sql')
        ;


        return $ctrl;
    }
}