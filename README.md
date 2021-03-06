yaskef - PHP, SQL, Javascript live interpreter
===============================================

Based on a fork of the [Silex-Kitchen-Edition](http://lyrixx.github.com/Silex-Kitchen-Edition),
yaskef (Yet Another Silex-Kitchen-Edition Fork) is a developper
utility to quickly test PHP, SQL and Javascript snippets, saving them
in db for later use, sending them on pastebin.com, etc.

For more information, see the [**dedicated page**](http://electrolinux.github.com/yaskef).

Installation
------------

* with composer

```bash
php composer.phar create-project -s dev electrolinux/yaskef PATH/TO/YOUR/APP
```

* with git

```bash
git clone https://github.com/electrolinux/yaskef PATH/TO/YOUR/APP
cd PATH/TO/YOUR/APP
curl -s http://getcomposer.org/installer | php
php composer.phar install
```

Quick Start Guide
-----------------

Edit 'resources/config/prod.php' if you want a different db, then

```bash
php console doctrine:database:create
php console doctrine:schema:load
php console user:create
php console snippet:load --force
```

If running on linux with sqlite3 db, ensure that the web user has write access both to
the database file and his folder. For example :

```
chgrp www-data resources/db resources/db/prod.db
chmod g+w resources/db resources/db/prod.db
```
Don't forget to do it again each time you re-create your database

Configure your web server, pointing the DocumentRoot to PATH/TO/YOUR/APP/web


For more informations about the Silex-Kitchen-Edition, see the
[**dedicated page**](http://lyrixx.github.com/Silex-Kitchen-Edition).


