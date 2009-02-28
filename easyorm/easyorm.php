<?php
/*
 * Copyright (c) 2009, EasyORM
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *     * Redistributions of source code must retain the above copyright
 *       notice, this list of conditions and the following disclaimer.
 *     * Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *     * Neither the name of the Cesar Rodas nor the
 *       names of its contributors may be used to endorse or promote products
 *       derived from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY CESAR RODAS ''AS IS'' AND ANY
 * EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL CESAR RODAS BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

define("EORM_DRIVER_DIR","drivers/");


/**
 *  ORecord
 *
 *  This class define the Iterable interface, 
 *
 */
abstract class ORecord implements Iterator {
    protected $records;
    protected $actual=0;

    final public function rewind() {
        $this->actual = 0;
    }

    final public function & current() {
        foreach(get_object_vars($this->records[$this->actual]) as $key => $value) {
            $this->$key = $value;
        }
        return $this;
    }

    final public function key() {
        return $this->actual;
    }

    final public function next() {
        $this->actual++;
    }

    final public function valid() {
        return $this->actual < count($this->records);
    }
} 

abstract class EasyORM  extends ORecord {
    private static $drivers;
    private static $dbm;
    private static $sql;
    private $table=false;

    final public function __construct() {
        if ($this->table === false) {
            $this->table = strtolower(get_class($this));
        }
        $params = func_get_args();
        if (count($params) > 0) {
            /* insert */
            //var_dump($this,$params);
        } 
    }

    /**
     *
     *
     */
    final public function scheme() {
        $id = DB::Integer(array("auto_increment"=>true));
        $this->id = $id;
        $this->data();
        $this->id = $id;
    }

    /**
     *  Set DB 
     *
     *  This function set-up the connection DB connection 
     *  URI.
     *
     *  @param string $param 
     */
    public final static function SetDB($param) {
        $host=$user=$password="";
        extract(parse_url($param));
        $db = substr($path,1);
        if (!isset($scheme)) {
            throw new Exception("$param is an invalid connection URI");
        }
        if (!EasyORM::import(EORM_DRIVER_DIR."/$scheme.php")) {
            throw new Exception("There is not a driver for {$scheme}");
        }
        if (!EasyORM::isDriver($scheme)) {
            throw new Exception("The driver $scheme is not working well");
        }
        self::$dbm = new self::$drivers[$scheme]["dbm"]($host,$user,$password,$db);
        self::$sql = new self::$drivers[$scheme]["sql"];
    }

    public final static function registerDriver($driver,$dbm,$sql) {
        if (!is_subclass_of($sql,"StdSQL")) {
            throw new Exception("$sql is not a subclass of StdSQL");
        }
        if (array_search("DBMBase",class_implements($dbm))===false) {
            throw new Exception("$dbm do not implements DBMBase interface");
        }
        self::$drivers[$driver] = array("dbm"=> $dbm,"sql"=> $sql);
    }

    public final static function isDriver($driver) {
        return isset(self::$drivers[$driver]);
    }

    public final static function import($file){
        static $loaded=array();
        $file=dirname(__FILE__)."/$file";
        if (is_file($file) && !isSet($loaded[$file])) {
            include($file);
            $loaded[$file] = true;
        }
        return isset($loaded[$file]);
    }

    public final static function SetupAll() {
        self::import("devel.php");
    }

    private final static function doConnect() {
        $oDbm = & self::$dbm;
        if (!$oDbm->isConnected()) {
            if (!$oDbm->doConnect()) {
                throw new Exception("Error while connecting to the DB");
            }
        }
    }

    /** 
     *  
     *
     *
     */
    public final static function Execute($sql) {
        echo "+ $sql\n";
        self::doConnect();
        return self::$dbm->Execute($sql);
    }

    protected final static function Query($sql) {
        $oDbm = & self::$dbm;
        self::doConnect();
        return $oDbm->BufferedQuery($sql);
    }


    final function getTableStructure() {
        $oSql = & self::$sql;
        $sql  = $oSql->getTableDetails($this->table);
        $result = self::query($sql);
        if (!$result) {
            return false;
        }
        return $oSql->ProcessTableDetails($result);
        }
    
    final public function get_relation($relation) {
        foreach(get_object_vars($this) as $col=>$value) {
            if (!$value InstanceOf DB) continue;
            if ($value->type=='relation' && strtolower($value->extra)==strtolower($relation)) {
                return $col;
            }
        }
        return false;
    }

    final public function create_table() {
        $sql = & self::$sql;
        $this->scheme();
        $table = $this->getTableStructure();
        $model = get_object_vars($this);
        if ($table==false) {
            /* there isn't a table yet, so create one */
            $csql = $sql->create_table($this->table,$model);
            self::Execute($csql);
        } else {
            /* there is a table yet, so change it until it looks    */
            /* as our model                                         */

            /* compare our model against the table (add new column) */
            foreach($model as $col=>$def) {
                if (!$def InstanceOf DB) continue;
                if ($def->type == 'relation') continue;
                if (!isset($table[$col])) {
                    $this->add_column($col,$def);
                }
            }
            /* compare table against model (delete column) */
            foreach($table as $id=>$column) {
                if (!isset($this->$id)) {
                    $this->del_column($id,$column);
                }
            }
        }
        $this->check_relations();
    }

    final private function check_relations() {
        foreach(get_object_vars($this) as $xcol => $def) {
            if (!$def InstanceOf DB) continue;
            if ($def->type == 'relation') {
                if (!is_subclass_of($def->extra,"easyorm")) {
                    throw new Exception("Error, {$this->table}::$col reference to a class doesn't exists {$def->extra}");
                }
                $rel = new $def->extra;
                $rel->scheme();
                $col =  $rel->get_relation(get_class($this));
                if ($col===false) {
                    throw new Exception("There is not a column that represent the relationship to {$this->table} into {$def->extra}");
                }
                if ($rel->$col->rel == DB::MANY && DB::MANY == $def->rel) {
                    /**
                     *  Many <-> Many
                     *  Create a auxiliar table to save the relation ship.
                     */
                    $nmodel = strtolower($def->extra);
                    $model  = strtolower($this->table);
                    $tmp = new DevelORM;
                    $tmp->table = strcmp($model,$nmodel)<1 ? "${model}_{$nmodel}" : "{$nmodel}_$model";
                    $tmp->scheme();
                    $tmp->$nmodel  = DB::Integer(array("required"=>true));
                    $tmp->$model   = DB::Integer(array("required"=>true));
                    /* create reference (many::many) table */
                    $tmp->create_table(); 
                    /* create unique index */
                    $tmp->add_index(DB::UNIQUE,array($model,$nmodel));
                    $tmp = null;/* release memory */
                } else if ($def->rel == DB::ONE and $rel->$col->rel == DB::MANY) {
                    /**
                     *  Many -> One relation ship, create an
                     *  index.
                     */
                    $table = $this->getTableStructure();
                    if (!isset($table[$xcol])) {
                        $this->add_column($xcol,DB::Integer(11));
                    }
                    $this->add_index(DB::INDEX,array($xcol));
                }
            }
        }
    }
    
    final function get_index() {
        $sql = & self::$sql;
        $index = $this->Query($sql->GetIndexs($this->table));
        return $sql->ProcessIndexs($index);
    }

    final function add_index($type,$columns) {
        $sql = & self::$sql;
        switch($type) {
            case DB::UNIQUE:
            case DB::INDEX:
                $index = $this->get_index();
                if (isset($index[$type])) {
                    /* let's see if there is already an index as we need */
                    foreach($index[$type] as $index) {
                        if (array_diff($index,$columns) === array()) {
                            return true;
                        }
                    }
                }
                $name = $this->table."_".implode("_",$columns);
                $name = strtolower($name);
                $oSql = $sql->create_index($this->table,$name,$type,$columns);
                $this->Execute($oSql);
                break;
            default:
                throw new Exception('Unkown $type');
                break;
        }
    }

    /** 
     *  Add Column
     *  
     */
    final private function add_column($column,DB $def) {
        $sql = & self::$sql;
        return self::Execute($sql->add_column($this->table,$column,$def));
    }

    /**
     *  Delete column
     *  
     *  Delete the $column from the actual objects' table.
     *
     *  @param string $column The Column name to delete
     *  @return bool True if success.
     */
    final private function del_column($column)  {
        return self::Execute(self::$sql->del_column($this->table,$column));
    }

    final public function & __call($name,$params) {
        $action = substr($name,0,3);
        //var_dump($name,$params);
        switch (strtolower($action)) {
            case "add":
                break;
            default:
                break;
        }
        return $this;
    }

    final public function save() {
        var_dump($this->id);
        die();
    }

    final public function __set($var,$value) {
        switch($var) {
            case "table":
                $this->$var = strtolower($value);
                break;
            default:
                if ($value instanceof DB)
                    $this->$var = $value;
                break;
        } 
    }

    final public function __get($var) {
        return isset($this->$var) ? $this->$var : false;
    }


    abstract function data();
}

class DB {
    const ONE='one';
    const MANY='many';
    const UNIQUE='unique';
    const INDEX='index';
    public $type;
    public $size;
    public $rel;
    public $extra;

    function __construct($type,$extras) {
        $this->type=$type;
        if (count($extras)==1 && is_numeric($extras[0]))  {
            $this->size=$extras[0];
        } else { 
            foreach ($extras[0] as $k=>$v) {
                $this->$k=$v;
            }
        }
        if (isset($this->auto_increment)&&$this->auto_increment) {
            $this->primary_key = true;
        }
    }

    public static function String() {
        $param = func_get_args();
        if (count($param)==0) {
            $param[] = 255;
        }
        return new DB("string",$param);
    }

    public static function Integer() {
        $param = func_get_args();
        if (count($param)==0) {
            $param[] = 11;
        }
        return new DB("integer",$param);
    }

    public static function Relation($class,$rel=DB::ONE) {
        if (!is_subclass_of($class,"EasyORM")) {
            throw new Exception("$class is not an EasyORM subclass");
        }
        return new DB("relation",array(array("rel"=>$rel,"extra"=>$class)));
    }
}


final class DevelORM extends EasyORM {
    function data() {}
}

EasyORM::import("sql.php");
?>
