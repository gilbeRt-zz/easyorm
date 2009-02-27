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

    final function create_table($param) {
        $dbm = & self::$dbm;
        $sql = & self::$sql;
        $csql = $sql->create_table($this->table,$param);
        return self::Execute($csql);
    }

    final function add_index($type,$columns) {
        $dbm = & self::$dbm;
        $sql = & self::$sql;
        switch($type) {
            case DB::UNIQUE:
            case DB::INDEX:
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
     *  
     */
    final function add_column($column,DB $def) {
        $dbm = & self::$dbm;
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
    final function del_column($column) {
        return self::Execute(self::$sql->del_column($this->table,$column));
    }

    final function & __call($name,$params) {
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

EasyORM::import("sql.php");

?>
