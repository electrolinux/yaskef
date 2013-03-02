<?php
/*
 * PasteBin.php : pastebin.com api
 */

namespace Oclane;

use Doctrine\DBAL\DBALException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;


class PasteBin
{
    const URL='http://pastebin.com/api/api_post.php';
    const LOGIN_URL='http://pastebin.com/api/api_login.php';

    const HEAD="Yaskef Snippet (electrolinux.github.com/yaskef/)\r\n";

    protected $db;
    protected $app;
    protected $users_username;
    protected $user_id;
    protected $pb_username;
    protected $pb_password;
    protected $pb_api_key;
    protected $pb_exposure = 0;
    protected $pb_expiration = '10M';

    protected $pb_api_user_key;

    public $key_from_session = false;

    protected $_error;

    public function __construct($app)
    {
        $this->app = $app;
        $this->db = $app['db'];
        $token = $app['security']->getToken();
        if (null !== $token) {
            $username = $token->getUsername();
            $this->setUser($username);
            if ($app['session']->get('pb_api_user_key')) {
                $this->pb_api_user_key = $app['session']->get('pb_api_user_key');
                $this->key_from_session = true;
            } else {
                $this->pb_api_user_key = $this->genApiUserKey();
                $app['session']->set('pb_api_user_key',$this->pb_api_user_key);
            }
        } else {
            throw new \Exception('PasteBin without user...');
        }
    }

    public function getUsername()
    {
        return $this->pb_username;
    }

    public function getPassword()
    {
        return $this->pb_password;
    }

    public function getApiKey()
    {
        return $this->pb_api_key;
    }

    public function getExposure()
    {
        return $this->pb_exposure;
    }

    public function getExpiration()
    {
        return $this->pb_expiration;
    }

    public function getApiUserKey()
    {
        return $this->pb_api_user_key;
    }

    private function setUser($username)
    {
        $this->users_username = $username;
        try {
            $stmt = $this->db->executeQuery(
                'SELECT p.* FROM users u LEFT JOIN pastebin p ON p.user_id = u.id WHERE u.username = ?',
                array(strtolower($username))
            );
            if ($pb = $stmt->fetch()) {
                $this->pb_username   =  $pb['username'];
                $this->pb_password   =  $pb['password'];
                $this->pb_api_key    =  $pb['api_key'];
                $this->pb_exposure   =  $pb['exposure'];
                $this->pb_expiration =  $pb['expiration'];
            } else {
                $stmt = $this->db->executeQuery(
                    'SELECT id,username FROM users WHERE username = ?',
                    array(strtolower($username))
                );
                if ($user = $stmt->fetch()) {
                    $this->user_id = $user['id'];
                }
            }
        } catch (\Exception $e) {
            $this->app['session']->getFlashBag()->add('error',$e->getMessage());
        }
        return $this;
    }

    public function genApiUserKey()
    {
        $api_dev_key = $this->pb_api_key;
        $api_user_name = urlencode($this->pb_username);
        $api_user_password = urlencode($this->pb_password);
        $url = PasteBin::LOGIN_URL;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'api_dev_key='.$api_dev_key.'&api_user_name='.$api_user_name.'&api_user_password='.$api_user_password.'');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_VERBOSE, 1);
        curl_setopt($ch, CURLOPT_NOBODY, 0);
        $response = curl_exec($ch);
        if (preg_match('/^Bad API request/',$response)) {
            return false;
        }
        return $response;
    }

    protected function prepareCode($lang,$code,$html='')
    {
        if($lang == 'php') {
            $s = "<?php\r\n// " . PasteBin::HEAD . "\r\n$code";
        } elseif ($lang == 'js') {
            if (!empty($html)) {
                $s = "/*\r\n * " . PasteBin::HEAD . "\r\n" .
                " * Html markup for this snippet:\r\n" .
                "--8<--------\r\n$html\r\n--8<--------\r\n" .
                " */\r\n\r\n$code";
            } else {
                $s = "/*\r\n * " . PasteBin::HEAD ." */\r\n\r\n$code";
            }
        } elseif ($lang == 'sql') {
            $s = '-- ' . PasteBin::HEAD . "\r\n$code";
        } else {
            $s='# ' . PasteBin::HEAD . "\r\n$code";
        }
        return urlencode($s);
    }

    public function postCode($lang,$code,$title='',$html='')
    {
        $api_dev_key = $this->pb_api_key;
        $api_paste_code = $this->prepareCode($lang,$code,$html);
        $api_paste_private = $this->pb_exposure;
        if (empty($title)) {
            $title='New Pastebin test';
        }
        $api_paste_name = $title;
        $api_paste_expire_date = $this->pb_expiration;
        $api_paste_format = $lang == 'js' ? 'javascript' : $lang;
        $api_user_key = $this->pb_api_user_key; // if an invalid api_user_key or no key is used, the paste will be create as a guest
        $api_paste_name = urlencode($api_paste_name);
        $url = PasteBin::URL;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'api_option=paste&api_user_key='.$api_user_key.'&api_paste_private='.$api_paste_private.'&api_paste_name='.$api_paste_name.'&api_paste_expire_date='.$api_paste_expire_date.'&api_paste_format='.$api_paste_format.'&api_dev_key='.$api_dev_key.'&api_paste_code='.$api_paste_code.'');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_VERBOSE, 1);
        curl_setopt($ch, CURLOPT_NOBODY, 0);
        $response = curl_exec($ch);
        return $response;
    }

    public function updateUser($username,$password,$api_key,$exposure,$expiration)
    {
        try {
            if (!$this->pb_username) {
                // chance there is no entry in pastebin yet
                if (!$this->user_id) {
                    $msg = $this->app['translator']->trans('No user associated with this PasteBin');
                    $this->app['session']->getFlashBag()->add('error',$msg);
                    return false;
                }
                $this->db->insert('pastebin', array(
                    'user_id' => $this->user_id,
                    'username' => $username,
                    'password' => $password,
                    'api_key' => $api_key,
                    'exposure' => $exposure,
                    'expiration' => $expiration
                    ));
            } else {
                $this->db->update('pastebin',array(
                    'password' => $password,
                    'api_key' => $api_key,
                    'exposure' => $exposure,
                    'expiration' => $expiration
                    ), array('username' => $username)
                );
            }
            $this->app['session']->remove('pb_api_user_key');
        } catch(\Exception $e) {
            $this->app['session']->getFlashBag()->add('error',$e->getMessage());
        }
    }
}

