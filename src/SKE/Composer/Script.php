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
        touch('resources/config/db_config.php');
        chmod('resources/config/db_config.php',0666);
        exec('php console assetic:dump');
    }
}
