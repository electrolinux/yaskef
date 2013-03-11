<?php
/*
 * ProfileController.php
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
//use Symfony\Component\HttpFoundation\Request;
//use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;

class ProfileController implements ControllerProviderInterface
{
    private $app;
    private $prefs = array (
        'start_page'        => 'homepage',
        'editor_style'      => 'default',
        );

    private function getUserPreferences($app,$username)
    {
        $stmt = $app['db']->executeQuery('SELECT preferences FROM users WHERE username = ?', array(strtolower($username)));
        if (!$user = $stmt->fetch()) {
            throw new UsernameNotFoundException(sprintf('Username "%s" does not exist.', $username));
        }
        return unserialize($user['preferences']);
    }

    private function getForm($app,$username)
    {
        $pb = $app['pastebin'];
        $prefs = $this->getUserPreferences($app,$username);

        $data = array_merge(
            array(
                'pb_username' => $pb->getUsername(),
                'pb_password' => $pb->getPassword(),
                'pb_api_key'  => $pb->getApiKey(),
                'pb_exposure' => $pb->getExposure(),
                'pb_expiration' => $pb->getExpiration(),
            ),
            $prefs
        );
        // pastebin options ------------------------------------------
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
        // users options ---------------------------------------------
        $page_choices = array(
            'homepage'  => $app['translator']->trans('Home page'),
            'js'        => $app['translator']->trans('Javascript'),
            'sql'       => $app['translator']->trans('SQL'),
            'profile'   => $app['translator']->trans('Profile page')
        );
        $style_choices = array(
            'ambiance'      => 'ambiance',
            'blackboard'    => 'blackboard',
            'cobalt'        => 'cobalt',
            'eclipse'       => 'eclipse',
            'elegant'       => 'elegant',
            'erlang-dark'   => 'erlang-dark',
            'lesser-dark'   => 'lesser-dark',
            'monokai'       => 'monokai',
            'neat'          => 'neat',
            'night'         => 'night',
            'rubyblue'      => 'rubyblue',
            'solarized light'=> 'solarized light',
            'solarized dark' => 'solarized dark',
            'twilight'      => 'twilight',
            'vibrant-ink'   => 'vibrant-ink',
            'xq-dark'       => 'xq-dark'
        );

        $form = $app['form.factory']->createBuilder('form',$data)
            ->add('password', 'password',array('required'=>false))

            ->add('start_page','choice',  array(
                'choices'  => $page_choices,
                'label' => $app['translator']->trans('Startup page')
            ))
            ->add('editor_style','choice',  array(
                'choices'  => $style_choices,
                'label' => $app['translator']->trans('Editor style')
            ))
            // pastebin
            ->add('pb_username','text', array(
                'label' => $app['translator']->trans('Username')
            ))
            ->add('pb_password','text', array(
                'label' => $app['translator']->trans('Password')
            ))
            ->add('pb_api_key','text', array(
                'label' => $app['translator']->trans('Api Key')
            ))
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
        return $form;
    }

    public function connect(Application $app)
    {
        $this->app = $app;
        $ctrl =  $app['controllers_factory']
            ->value('_locale','en')
            ->assert('_locale',implode('|',$app['locales']))
            ;


        /*------------------------------------------------------------*
         * preferences
         *------------------------------------------------------------*/
        $ctrl->match('/{_locale}', function (Application $app) {

            $user = $app['security']->getToken();
            $form = $this->getForm($app,$user->getUsername());
            if ('POST' === $app['request']->getMethod()) {
                $form->bind($app['request']);

                if ($form->isValid()) {
                    $password = $form->get('password')->getData();
                    $start_page = $form->get('start_page')->getData();
                    $editor_style= $form->get('editor_style')->getData();
                    $prefs=array(
                        'start_page'=>$start_page,
                        'editor_style' => $editor_style
                    );
                    $user_data = array(
                        'preferences' => serialize($prefs),
                    );
                    if (!empty($password)) {
                        $user_data['password'] = $app['security.encoder.digest']->encodePassword($password, '');
                    }
                    $app['db']->update('users',
                        $user_data,
                        array('username' => $user->getUsername())
                    );
                    $app['session']->getFlashBag()->add('success', $app['translator']->trans('General options saved'));
                    foreach(array('username','password','api_key','exposure','expiration') as $key) {
                        $$key = $form->get("pb_$key")->getData();
                    }
                    $app['pastebin']->updateUser($username,$password,$api_key,$exposure,$expiration);
                    $app['session']->getFlashBag()->add('success', $app['translator']->trans('Pastebin option saved'));
                    return $app->redirect($app['url_generator']->generate($start_page));
                } else {
                    $app['session']->getFlashBag()->add('error', $app['translator']->trans('Error processing your data !!'));
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
        ->bind('profile')
        ;

        return $ctrl;
    }
}