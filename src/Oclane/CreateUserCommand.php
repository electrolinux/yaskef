<?php

/*
 * This file is part of the FOSUserBundle package.
 *
 * (c) FriendsOfSymfony <http://friendsofsymfony.github.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Oclane;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;


//use UserProvider;

/**
 * @author Matthieu Bontemps <matthieu@knplabs.com>
 * @author Thibault Duplessis <thibault.duplessis@gmail.com>
 * @author Luis Cordova <cordoval@gmail.com>
 */
class CreateUserCommand extends Command
{
    private $app;
    private $username;
    private $password;

    public function __construct($name = null, $app = null)
    {
        parent::__construct($name);
        $this->app = $app;
    }

    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setName('user:create')
            ->setDescription('Create a user.')
            ->setDefinition(array(
                new InputArgument('username', InputArgument::REQUIRED, 'The username'),
                new InputArgument('password', InputArgument::REQUIRED, 'The password'),
                new InputOption('admin', null, InputOption::VALUE_NONE, 'Give the user the ROLE_ADMIN'),
                new InputOption('pastebin', null, InputOption::VALUE_NONE, "Add the user's pastebin credentials"),
                new InputArgument('pb_username', InputArgument::OPTIONAL, "User's pastebin.com Username"),
                new InputArgument('pb_password', InputArgument::OPTIONAL, "User's pastebin.com Password"),
                new InputArgument('pb_api_key', InputArgument::OPTIONAL, "User's pastebin.com Api Key"),
            ))
            ->setHelp(<<<EOT
The <info>user:create</info> command creates a user:

  <info>php app/console user:create matthieu</info>

This interactive shell will ask you for a password.

You can alternatively specify the password as the second argument:

  <info>php app/console user:create matthieu mypassword</info>

You can create an admin user via the admin flag:

  <info>php app/console user:create admin --admin</info>

You can add the user's pastebin credentials via the pastebin flag:

  <info>php app/console user:create user --pastebin</info>

You'll have to give Pastebin.com's username,password and api key

EOT
            );
    }

    /**
     * @see Command
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $username    = $input->getArgument('username');
        $password    = $input->getArgument('password');
        $this->username = $username;
        $this->password = $password;
        $pb_username = $input->getArgument('pb_username');
        $pb_password = $input->getArgument('pb_password');
        $pb_api_key  = $input->getArgument('pb_api_key');
        $admin       = $input->getOption('admin');
        $pbuser      = $input->getOption('pastebin');

        $encoded = $this->app['security.encoder.digest']->encodePassword($password, '');

        $db = $this->app['db'];
        $db->insert('users', array(
            'username' => $username,
            'password' => $encoded,
            'roles' => $admin ? 'ROLE_ADMIN' :  'ROLE_USER'
        ));
        $id = null;
        $stmt = $db->executeQuery('SELECT id FROM users WHERE username = ?',array(
            $username
        ));
        if (!$user = $stmt->fetch()) {
            throw new UsernameNotFoundException(sprintf('Username "%s" does not exist.', $username));
        }
        $id = $user['id'];
        if ($id and $pb_username and $pb_password and $pb_api_key) {
            $this->app['db']->insert('pastebin', array(
                'user_id'  => $id,
                'username' => $pb_username,
                'password' => $pb_password,
                'api_key'  => $pb_api_key
            ));
        }

        $output->writeln(sprintf('Created user <comment>%s</comment>', $username));
    }

    /**
     * @see Command
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        if (!$input->getArgument('username')) {
            $username = $this->getHelper('dialog')->askAndValidate(
                $output,
                'Please choose a username:',
                function($username) {
                    if (empty($username)) {
                        throw new \Exception('Username can not be empty');
                    }

                    return $username;
                }
            );
            $input->setArgument('username', $username);
        }

        if (!$input->getArgument('password')) {
            $password = $this->getHelper('dialog')->askAndValidate(
                $output,
                'Please choose a password:',
                function($password) {
                    if (empty($password)) {
                        throw new \Exception('Password can not be empty');
                    }

                    return $password;
                }
            );
            $input->setArgument('password', $password);
        }

        if ($input->getOption('pastebin')) {
            if (!$input->getArgument('pb_username')) {
                $def_username = $input->getArgument('username');
                $pb_username = $this->getHelper('dialog')->askAndValidate(
                    $output,
                    'Please set your pastebin username (' . $def_username . '): ',
                    function($pb_username) {
                        return $pb_username;
                    },
                    false,
                    $def_username
                );
                $input->setArgument('pb_username', $pb_username);
            }
            if (!$input->getArgument('pb_password')) {
                $def_password = $input->getArgument('password');
                $pb_password = $this->getHelper('dialog')->askAndValidate(
                    $output,
                    'Please set your pastebin password (' . $def_password . '): ',
                    function($pb_password) {
                        return $pb_password;
                    },
                    false,
                    $def_password
                );
                $input->setArgument('pb_password', $pb_password);
            }
            if (!$input->getArgument('pb_api_key')) {
                $api_key = $this->getHelper('dialog')->askAndValidate(
                    $output,
                    'Please set your pastebin api key: ',
                    function($api_key) {
                        return $api_key;
                    }
                );
                $input->setArgument('pb_api_key', $api_key);
            }
        }
    }
}
