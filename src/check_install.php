<?php
/*
 * check_install.php
 *
 * help to install if not yet ready...
 *
 * obviously inspired from bolt lowlevelchecks.php
 *
 */

class Check
{

    public static function run()
    {
        // 1. checks that needs big changes (php version, safe mode off, composer not run)

        // requires PHP 5.3.2 or higher.
        if (!version_compare(phpversion(), "5.3.2") > -1) {
            Check::exitError("Yaskef requires PHP <u>5.3.2</u> or higher. You have PHP <u>". phpversion().
            "</u>, so Yaskef will not run on your current setup.");
        }

        if (ini_get('safe_mode')) {
            Check::exitError("Yaskef requires Safe mode to be <b>off</b>. Please send your hoster to " .
                "<a href='http://php.net/manual/en/features.safe-mode.php'>this page</a>, and point out the ".
                "<span style='color: #F00;'>BIG RED BANNER</span> that states that safe_mode is <u>DEPRECATED</u>. Seriously.");
        }

        // Check if the vendor folder is present. If not, this is most likely because
        // the user checked out the repo from Git, without running composer.
        if (!file_exists(__DIR__.'/../vendor/autoload.php')) {
            Check::exitError("The file <code>vendor/autoload.php</code> doesn't exist. Make sure " .
                "you've installed the Silex/Yaskef components with Composer. Please read the docs.");
        }

        // 2. checks that don't need a server restart, just reloading the page is enough
        // so run them all, and make a list of any problems to fix

        $errors = array();

        $cleanPath = function($path) {
            if (realpath($path) != '') {
                return realpath($path);
            }
            $end = basename($path);
            $path = dirname($path);
            while (realpath($path)=='') {
                $end  = basename($path) . "/$end";
                $path = dirname($path);
            }
            return realpath($path) . "/$end";
        };

        // Check folders needing write access
        foreach(array(
            __DIR__.'/../resources/cache',
            __DIR__.'/../resources/log',
            __DIR__.'/../resources/config',
            __DIR__.'/../web/assets/css',
            __DIR__.'/../web/assets/img',
            __DIR__.'/../web/assets/js',
            ) as $folder) {
            $folder = $cleanPath($folder);
            if (!file_exists($folder)) {
                if (!@mkdir($folder,0775,true)) {
                    $errors[] = "The folder <code>$folder</code> doesn't exist and can't
                        be created. Make sure it's present and writable by the
                        webserver's account.";
                }
            } elseif (!is_writable($folder)) {
                //$folder = realpath($folder);
                $errors[]="The folder <code>$folder</code> isn't writable. Make sure it's " .
                    "present and writable by the webserver's account.";
            }
        }

        if (count($errors)) {
            Check::exitError($errors);
        }

        return true;

    }


    /**
     * Print a 'low level' error page, and quit. The user has to fix something.
     *
     * @param string $message
     */
    private static function exitError($errors)
    {

        $html = <<< EOM
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Yaskef - Error</title>
    <link rel="stylesheet" type="text/css" href="assets/css/styles.css" />
</head>
<body style="padding: 20px;">

    <div style="max-width: 530px; margin: auto;">

    <h1>Yaskef - Fatal error.</h1>

    <ul>%error%</ul>

    <p>This is a fatal error. Please fix the error, and refresh the page.
    Yaskef can not run, until this error has been corrected. <br>
    </p>

    <ul>
        <li><a href="http://electrolinux.github.com/yaskef/">Yaskef documentation</a></li>
        <li><a href="https://github.com/electrolinux/yaskef">Yaskef Project on Github</a></li>
    </ul>

    </div>
    <hr>

</body>
</html>
EOM;

        if (!is_array($errors)) {
            $errors = array($errors);
        }
        $message = '<li><strong>' . implode('</strong></li><li><strong>',$errors) . '</strong></li>';
        $html = str_replace("%error%", $message, $html);

        echo $html;

        die();

    }

}


return Check::run();

