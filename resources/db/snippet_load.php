<?php

/**
 * @author didier Belot <electrolinux@gmail.com>
 */
namespace Oclane;

use Symfony\Component\Finder\Finder;

use Doctrine\DBAL\Schema\Table;

$schema = $app['db']->getSchemaManager();
if (!$schema->tablesExist('snippets')) {
    throw new \Exception("table snippets don't exists !!");
}
$db = $app['db'];
$db->executeQuery('DELETE FROM snippets');

$finder = new Finder();
$finder->files()
    ->ignoreVCS(true)
    ->name('*.txt')
    ->notName('*~')
    ->in(__DIR__.'/../snippets')
;
$php = new Snippet($db);
$sql = new SnippetSql($db);
$js = new SnippetJs($db);

foreach ($finder as $file) {
    $name = str_replace('_',' ',str_replace('.txt','',basename($file)));
    $lang = basename(dirname($file));
    $code = file_get_contents($file);
    $html='';
    $comment = '';
    if (preg_match('/^(.*)BEGIN_HTML(.*)END_HTML(.*)$/is',$code,$matches)) {
        $html = $matches[2];
        $code = $matches[1] . $matches[3];
    }
    if (preg_match('/^(.*)BEGIN_COMMENT(.*)END_COMMENT(.*)$/is',$code,$matches)) {
        $comment = $matches[2];
        $code = $matches[1] . $matches[3];
    }
    echo "$lang: $name\n";
    if ($lang == 'php') {
        $php->add($name,$code,$comment,$html);
    } elseif ($lang == 'sql') {
        $sql->add($name,$code,$comment,$html);
    } elseif ($lang = 'js') {
        $js->add($name,$code,$comment,$html);
    }
}
