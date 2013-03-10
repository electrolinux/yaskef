<?php

namespace SKE\Composer;

class Script
{
    public static function install()
    {
        chmod('resources/cache', 0777);
        chmod('resources/log', 0777);
        chmod('web/assets', 0777);
        chmod('console', 0500);
		if (PHP_OS == 'WINNT') {
			// no symlink !!
			unlink('resources/assets/js/bootstrap.min.js');
			copy('vendor/twitter/bootstrap/docs/assets/js/bootstrap.min.js','resources/assets/js/bootstrap.min.js');
		}
        exec('php console assetic:dump');
    }
}
