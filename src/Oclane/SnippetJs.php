<?php
/*
 * snippet.php : manage code snippet in db
 */

namespace Oclane;

class SnippetJs extends Snippet
{
    protected $interp = 'js';
    protected $name = 'Javascript';

    public function getOptionsList()
    {
        $snippets=array();
        $options=array(''=>$this->name . ' Snippet...');
        foreach ($this->getAll() as $row) {
            $_val = $row['name'];
            $_text = $row['code'];
            $rows = preg_split("/(\n|\r)+/",$_text);
            $safe_rows = array_map('addslashes',$rows);
            $_name = $safe_rows[0];
            if (strlen($_name) > 20) {
                $parts = explode("\n",wordwrap($_name, 20, "\n", 1));
                $_name = $parts[0];
                if (true) {
                    echo '<!-- $parts: ' ."\n";
                    var_dump($parts);
                    echo '$_name : ' . "$_name -->\n";
                }
            }
            $options[$_val] = "$_val : $_name";
            $code = implode('\n',$safe_rows);
            $code = str_replace('<script','<scr"+"ipt',$code);
            $code = str_replace('</script','</scr"+"ipt',$code);
            $snippets[$_val]=$code;
        }

        return array($options,$snippets);
    }

}
