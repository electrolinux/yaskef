<?php
/*
 * Interpreter.php : les interpréteurs de code ^_^
 *
 */

namespace Oclane;

use Doctrine\DBAL\DBALException;

class Interpreter
{
    private $app;

    public function __construct($app)
    {
        $this->app = $app;
    }

    public function evalPhp($code,&$error)
    {
        $result='';
        $err_mode = error_reporting();
        error_reporting(E_ALL);
        if (strpos($code,'?>') !== false or strpos($code, '<?php ') !== false) {
            $msg = $app['translator']->trans('Opening or closing php tags found. Syntax check is disabled for mixed content');
            $this->app['session']->setFlash('warning',$msg);
            $errors = false;
        } else {
            $errors = $this->phpSyntaxError($code);
        }
        if ($errors == false) {
            ob_start();
            $res = eval($this->codeWithReturn($code));
            //$res = eval($code);
            $result = ob_get_contents();
            if ($res === false) {
                $error = $app['translator']->trans('Error executing your code');
            }
            ob_end_clean();
        } else {
            //$s = print_r($errors,true);
            $error = 'Syntax error: ' .
                $errors[0] . ', line ' . $errors[1];
        }
        error_reporting($err_mode);
        return ($result);
    }

    /**
     * Check the syntax of some PHP code.
     * Note: only works for pure PHP (not mixed content)
     * @param string $code PHP code to check.
     * @return boolean|array If false, then check was successful, otherwise an array(message,line) of errors is returned.
     *
     * (http://stackoverflow.com/questions/3223899/php-eval-and-capturing-errors-as-much-as-possible)
     */
    protected function phpSyntaxError($code)
    {
        $braces=0;
        $inString=0;
        foreach (token_get_all('<?php ' . $code) as $token) {
            if (is_array($token)) {
                switch ($token[0]) {
                    case T_CURLY_OPEN:
                    case T_DOLLAR_OPEN_CURLY_BRACES:
                    case T_START_HEREDOC: ++$inString; break;
                    case T_END_HEREDOC:   --$inString; break;
                }
            } else if ($inString & 1) {
                switch ($token) {
                    case '`': case '\'':
                    case '"': --$inString; break;
                }
            } else {
                switch ($token) {
                    case '`': case '\'':
                    case '"': ++$inString; break;
                    case '{': ++$braces; break;
                    case '}':
                        if ($inString) {
                            --$inString;
                        } else {
                            --$braces;
                            if ($braces < 0) break 2;
                        }
                        break;
                }
            }
        }
        $inString = @ini_set('log_errors', false);
        $token = @ini_set('display_errors', true);
        ob_start();
        $braces || $code = "if(0){{$code}\n}";
        if (eval($code) === false) {
            if ($braces) {
                $braces = PHP_INT_MAX;
            } else {
                false !== strpos($code,CR) && $code = strtr(str_replace(CRLF,LF,$code),CR,LF);
                $braces = substr_count($code,LF);
            }
            $code = ob_get_clean();
            $code = strip_tags($code);
            if (preg_match("'syntax error, (.+) in .+ on line (\d+)$'s", $code, $code)) {
                $code[2] = (int) $code[2];
                $code = $code[2] <= $braces
                    ? array($code[1], $code[2])
                    : array('unexpected $end' . substr($code[1], 14), $braces);
            } else $code = array('syntax error', 0);
        } else {
            ob_end_clean();
            $code = false;
        }
        @ini_set('display_errors', $token);
        @ini_set('log_errors', $inString);
        return $code;
    }

    private function codeWithReturn($code)
    {
        $nbclose = count(explode('?>',$code));
        $nbopen = count(explode('<?php',$code));
        $append = $nbclose > $nbopen ? '<?php return true;' : ' ;return true;';
        return "$code $append";
    }

    public function evalJs($code,$html='')
    {
        // version minimale
        return $html . '<script type="text/javascript">' . $code . '</script>';
    }

    public function evalSql($code)
    {
        $db = $this->app['db'];

        $decl = preg_split('/;/',$code);
        $resultat = '';
        foreach ($decl as $sql) {
            $sql=trim($sql);
            if(empty($sql)) continue;

            $resultat .= '<code>' . $sql . '</code><br />' . "\n";
            if ($this->isExec($sql)) {
                try {
                    $res = $db->executeQuery($sql);
                    if ($res === false) {
                        $errinfo = $db->errorInfo();
                        $resultat .= '<b>Ooops (1)...: ['.$errinfo[0].'] ('.
                            $errinfo[1].') '.$errinfo[2].'</b>';

                        return $resultat;
                    } else {
                        $resultat .= '<p class="ok">' . $res->fetch() . '</p>';
                    }
                } catch (DBALException $e) {
                    $resultat .= '<p class="error">Ooops...(2) : ' . $e->getMessage() . '</p>';

                    return $resultat;
                }
            } else {
                try {
                    $res = $db->fetchAll($sql);
                    if ($res === false) {
                        $errinfo = $db->errorInfo();
                        $resultat .= '<b>Oops...(3) : ['.$errinfo[0].'] ('.
                            $errinfo[1].') '.$errinfo[2].'</b>';

                        return $resultat;
                    }
                    $resultat .= $this->asTable($res);
                } catch (DBALException $e) {
                    $resultat .= '<p class="error">Ooops...(4) : ' . $e->getMessage() . '</p>';

                    return $resultat;
                }
            }
        }

        return $resultat;
    }

    /*function __word_iregex($arg)
    {
        return '/\b' . $arg . '\b/i';
    }*/

    protected function isExec($sql)
    {
        //$_exec = array_map('__word_iregex',array('drop','create','insert','update','delete','alter'));
        $words = array('drop','create','insert','update','delete','alter');

        foreach ($words as $word) {
            if(preg_match('/\b'.$word.'\b/i',$sql))

                return true;
        }
        /*if(preg_match('/\bselect\b/i',$sql) and !preg_match('/\bfrom\b/i',$sql))

            return true;*/
        return false;
    }

    protected function asTable($result)
    {
        $head = '';
        $body = '';
        $classes = array('paire','impaire');
        $x = 0;
        foreach ($result as $row) {
            if (empty($head)) {
                $head = '<tr><th>' .
                    implode('</th><th>',array_keys($row)) . '</th></tr>' . "\n";
            }
            $css=$classes[++$x % 2];
            $body .= '<tr class="'.$css.'"><td>' .
                implode('</td><td>',array_values($row)) . '</td></tr>' . "\n";
        }

        return '<table cellpadding="1" cellspacing="1" border="0">' . "\n" .
            '<thead>' . $head . '</thead>' . "\n" .
            '<tbody>' . $body . '</tbody>' . "\n" . '</table>' . "\n";
    }
}
