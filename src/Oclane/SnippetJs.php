<?php
/*
 * snippet.php : manage code snippet in db
 */

namespace Oclane;

class SnippetJs extends Snippet
{
    protected $lang = 'js';
    protected $name = 'Javascript';

    public function getOptionsList($app)
    {
        $snippets=array();
        $options=array(''=>$app['translator']->trans('%lang% Snippet...',array(
            '%lang%' => $this->name
        )));
        foreach ($this->getAll() as $row) {
            $_val = $row['name'];
            $nrows = $row['rows'];
            $safe_rows = $this->getSafeRows($row['code']);
            $_name = $app['translator']->trans('%name% (%nrows% rows)', array(
                '%name%' => $_val, '%nrows%' => $nrows));
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
