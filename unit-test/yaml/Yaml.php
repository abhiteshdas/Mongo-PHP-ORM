<?php

/*
  YAML subset parser

  limitations:
  value can be numbers and strings and json expressions ([...] and {...}) 


    */

namespace hb\yaml;

class Yaml {

    public static function parse($file, $want_json = false) {
        $p = new static($file);
        $json = $p->doit();
        if (! $json)
            $json = "{}";
        if ($want_json)
            return $json;
        $r = json_decode($json, 1);
        if (! $r) {
            trigger_error("Parse Error: $file");
            die;
        }
        return $r;
    }

    // --------------------------------------------------------------------------------
    // PRIVATE / INTERNAL

    protected $lines;           // line buffer
    protected $line = 0;        // line number
    protected $lines_total = 0; // lines in file

    protected function __construct($file) {
        $this->lines = file($file, FILE_IGNORE_NEW_LINES);
        $this->lines_total = count($this->lines);
    }


    function doit($ident=0) {
        $r = [];
        $p = 0; // same-ident position 
        while($ikv=$this->next()) {
            list($id, $k, $v)=$ikv; // ident, key, value

            // echo "doit($ident) ".json_encode($ikv)."\n";

            // node: x1
            //   node: y
            // node: x2 << this case
            if ($id<$ident) {
                $this->redo = $ikv;
                break;
            }

            // bad identation
            // node: v
            //  node: v
            if ($id>$ident && $v!=='') {
                trigger_error("Bad ident($id) expected: $ident. Line $this->line '$k:$v'");
                die;
            }

            // node: << opening node
            if ($v==='') {
                $next = $this->next();
                if (! $next) {
                    trigger_error("child node expected. Line $this->line '$k:$v'");
                    die;
                }
                $next_id=$next[0]; // identation
                if ($next[1]===null) // LIST
                    $v = $this->doList($next_id);
                else {
                    $this->redo = $next;
                    $v=$this->doit($next_id);
                }
            }
            
            $r[]=$k.":".$v;
            $p++;
        }
        return "{".join(",", $r)."}";
    }


    // process "- item"
    function doList($ident) {
        $r = [];
        while($ikv=$this->next()) {
            // echo "doList($ident) ".json_encode($ikv)."\n";
            list($id, $k, $v)=$ikv; // ident, key, value
            if ($ident>$id) {
                trigger_error("format error. only list of scalars are supported. Line $this->line '$k:$v'");
                die;
            }
            if ($k) {
                trigger_error("format error. list item expected. Line $this->line '$k:$v'");
                die;
            }
            if ($ident<$id) {
                $this->redo=$ikv;
                break;
            }
            $r[]=$this->v($v);
        }
        return "[".join(",", $r)."]";
    }

    private $redo;

    // get next useful line
    // skip empty lines and comments
    function next() {           // [ident, key, value]  | false
        if ($this->redo) {
            $v = $this->redo;
            $this->redo = null;
            return $v;
        }
        if ($this->lines_total == $this->line)
            return false; // EOF

        $l = $this->lines[$this->line];
        $this->line++;

        $l = rtrim($l);
        $l0 = strlen($l);
        $l = ltrim($l);
        if (!$l || $l[0]=='#')  // comments and empty lines
            return $this->next();
        $ident = $l0 - strlen($l);

        // cut same-line '#' comments
        if ($o=strrpos($l, "#")) {
            // do we have quotes in value
            if (strpos(substr($l, 0, $o-1), '"')!==false) {
                if (substr_count($l, '"', 0, $o-1) & 1)
                    $o=0; // uneven number of quotes, this is not a comment
            }
            if ($o)
                $l = rtrim(substr($l, $o-1));
        }

        // LISTS / ARRAYS - "- " prefix
        if ($l[0]=='-' && $l[1]==' ') {
            $l=ltrim(substr($l,1));
            return [$ident+2, null, $this->v($l)];
        }

        // if (! preg_match('/^[\w ]+:/', $l))
        //    return [$ident, '', $l];  // no KEY

        list($k, $v) = explode(":", $l, 2);
        return [$ident, '"'.trim($k).'"', $this->v($v)];  // $k & $v - escaped
    }

    // escape value
    function v($v) {
        $v = trim($v);
        // escape strings
        if ($v && ! is_numeric($v)) {
            $_ = $v[0];
            if ($_!='"' && $_!='[' && $_!='{')
                $v = '"'.$v.'"';
        }
        return $v;
    }

} // class
