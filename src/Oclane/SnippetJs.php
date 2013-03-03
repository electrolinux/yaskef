<?php
/*
 * snippet.php : manage code snippet in db
 */

namespace Oclane;

class SnippetJs extends Snippet
{
    protected $lang = 'js';
    protected $name = 'Javascript';

    public function getOptionsList()
    {
        $snippets=array();
        $options=array(''=>$this->name . ' Snippet...');
        foreach ($this->getAll() as $row) {
            $_val = $row['name'];
            $safe_rows = $this->getSafeRows($row['code']);
            $_name = "$_val: " . $safe_rows[0];
            if (strlen($_name) > 50) {
                $parts = explode("\n",wordwrap($_name, 50, "\n", 1));
                $_name = $parts[0];
            }
            $options[$_val] = $_name;
            $snippets[$_val] = array();
            $code = implode('\n',$safe_rows);
            $code = str_replace('<script','<scr"+"ipt',$code);
            $code = str_replace('</script','</scr"+"ipt',$code);
            $snippets[$_val]['code']=$code;
            $snippets[$_val]['html']=implode('\n',$this->getSafeRows($row['html']));
            $snippets[$_val]['comment']=implode('\n',$this->getSafeRows($row['comment']));
        }

        return array($options,$snippets);
    }

}
