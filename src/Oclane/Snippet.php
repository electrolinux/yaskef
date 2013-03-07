<?php
/*
 * snippet.php : manage code snippet in db
 */

namespace Oclane;

use Doctrine\DBAL\DBALException;

class Snippet
{
    protected $db;
    protected $lang = 'php';
    protected $qlang = null;
    protected $name = 'PHP';

    protected $_error;

    public function __construct($db)
    {
        $this->db = $db;
        $this->qlang = $db->quote($this->lang);
    }

    public function getAll()
    {
        $db = $this->db;
        $qlang = $this->qlang;
        try {
            $res = $db->fetchAll("SELECT id, name, code, rows, comment, html
                FROM snippet WHERE lang=$qlang ORDER BY name");
            if ($res) {
                return $res;
            }

            return array();

        } catch (DBALException $e) {
            $this->_error = $e->getMessage();

            return array();
        }
    }

    public function add($name,$code,$comment='',$html='')
    {
        $db = $this->db;
        $qname = $db->quote($name);
        $qlang = $this->qlang;
        $qcode = $db->quote($code);
        $qcomment = empty($comment) ? 'NULL' : $db->quote($comment);
        $qhtml = empty($html) ? 'NULL' : $db->quote($html);
        $lignes = preg_split("/(\n|\r)+/",$code);
        $rows=count($lignes);
        try {
            $res = $db->executeQuery("INSERT INTO snippet
                (name,lang,code,rows,comment,html)
                VALUES ($qname,$qlang,$qcode,$rows,$qcomment,$qhtml)");
            if ($res) {
                return true;
            } elseif ($res === false) {
                return $this->modif($name,$code,$comment,$html);
            }
        } catch (DBALException $e) {
            //$this->_error = $e->getMessage();
            //return FALSE;
            return $this->modif($name,$code,$comment,$html);
        }
    }

    public function modif($name,$code,$comment='',$html='')
    {
        $db = $this->db;
        try {
            $qname = $db->quote($name);
            $qlang = $this->qlang;
            $qcode = $db->quote($code);
            $qcomment = empty($comment) ? null : $db->quote($comment);
            $qhtml = empty($html) ? null : $db->quote($html);
            $_rows = preg_split("/(\n|\r)+/",$code);
            $rows=count($_rows);
            $res = $db->executeUpdate("UPDATE snippet
                SET code = $qcode, rows = $rows,
                comment = $qcomment,
                html = $qhtml
                WHERE name = $qname AND lang = $qlang");
            if($res)

                return True;
            else {
                $errinfo = $db->errorInfo();
                $this->_error = '['.$errinfo[0].'] ('.$errinfo[1].') '.$errinfo[2].'.';

                return FALSE;
            }
        } catch (DBALException $e) {
            $this->_error = $e->getMessage();

            return FALSE;
        }
    }

    protected function getError()
    {
        return $this->_error;
    }

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
            $snippets[$_val]=array();
            $snippets[$_val]['code']=implode('\n',$safe_rows);
            $snippets[$_val]['html']=implode('\n',$this->getSafeRows($row['html']));
            $snippets[$_val]['comment']=implode('\n',$this->getSafeRows($row['comment']));
        }

        return array($options,$snippets);
    }

    protected function getSafeRows($text=null)
    {
        if (empty($text)) {
            return array();
        }
        $rows = preg_split("/(\n|\r)+/",$text);
        return array_map('addslashes',$rows);
    }

    public function deleteSnippet($name,$lang)
    {
        if ($lang != $this->lang) {
            throw new \Exception('Incorrect language option: ' . $lang);
        }
        return $this->db->delete('snippet',array('lang' => $lang,'name'=>$name));
    }
}
