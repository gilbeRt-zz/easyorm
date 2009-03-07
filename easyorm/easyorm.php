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

    /**
     *
     */
    final public function rewind() {
        $this->actual = 0;
    }

    /**
     *
     */
    final public function & current() {
        foreach(get_object_vars($this->records[$this->actual]) as $key => $value) {
            $this->$key = $value;
        }
        return $this;
    }

    /**
     *
     */
    final public function key() {
        return $this->actual;
    }

    /**
     *
     */
    final public function next() {
        $this->actual++;
    }

    /**
     *
     */
    final public function valid() {
        return $this->actual < count($this->records);
    }
} 

/**
 *
 */
abstract class EasyORM  extends ORecord {
    /**
     *
     */
    private static $drivers;
    /**
     *
     */
    private static $dbm;
    /**
     *
     */
    private static $sql;
    /**
     *
     */
    private static $hooks=array();
    /**
     *
     */
    private $_schema=null;
    /**
     *
     */
    private $_data  =array();
    /**
     *
     */
    private $table=false;

    /**
     *
     */
    final public function __construct() {
        if ($this->table === false) {
            $this->table = strtolower(get_class($this));
        }
        $params = func_get_args();
        if (count($params) > 0) {
            foreach($params as $param) {
                foreach($param as $col => $value) {
                    $this->$col = $value;
                }
                $this->save();
            }
        } 
    }

    /**
     *
     */
    final public function schema() {
        if ($this->_schema===null) {
            $id = DB::Integer(array("auto_increment"=>true));
            $this->id = $id;
            $this->data();
            $this->id = $id;
        }
        return $this->_schema;
    }

    /**
     *
     */
    final public function Hook($action,$function) {
        $hook = & self::$hooks;
        if (!is_callable($function))
            throw new DBException(DBException::NOTFUNC,$function);
        if (!isset($hook[$action]))
            $hook[$action] = array();
        $hook[$action][] = $function;
    }

    /**
     *
     */
    final private function ExecHook($action) {
        $params = func_get_args();
        $hook   = & self::$hooks;
        if (!isset($hook[$action]))
            return; /* no action */
        foreach ($hook[$action] as $fnc) {
            call_user_func_array($fnc,$params);
        } 
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
            throw new DBException(DBException::URI,$param);
        }
        if (!EasyORM::import(EORM_DRIVER_DIR."/$scheme.php")) {
            throw new DBException(DBException::MISSDRIVER,$scheme);
        }
        if (!EasyORM::isDriver($scheme)) {
            throw new DBException(DBException::DRIVER,$scheme);
        }
        self::$dbm = new self::$drivers[$scheme]["dbm"]($host,$user,$password,$db);
        self::$sql = new self::$drivers[$scheme]["sql"];
    }

    /**
     *
     */
    public final static function registerDriver($driver,$dbm,$sql) {
        if (!is_subclass_of($sql,"StdSQL")) {
            throw new DBException(DBException::SUBCLASS,$sql,"StdSQL");
        }
        if (array_search("DBMBase",class_implements($dbm))===false) {
            throw new DBException(DBException::DBMBASE,$dbm);
        }
        self::$drivers[$driver] = array("dbm"=> $dbm,"sql"=> $sql);
    }

    /**
     *
     */
    public final static function isDriver($driver) {
        return isset(self::$drivers[$driver]);
    }

    /**
     *
     */
    public final static function import($file){
        static $loaded=array();
        $file=dirname(__FILE__)."/$file";
        if (is_file($file) && !isSet($loaded[$file])) {
            include($file);
            $loaded[$file] = true;
        }
        return isset($loaded[$file]);
    }

    /**
     *
     */
    public final static function SetupAll() {
        self::import("devel.php");
    }

    /**
     *
     */
    private final static function doConnect() {
        $oDbm = & self::$dbm;
        if (!$oDbm->isConnected()) {
            if (!$oDbm->doConnect()) {
                throw new DBException(DBException::DBCONN);
            }
        }
    }

    /** 
     *  
     */
    public final static function Execute($sql) {
        self::ExecHook("on_exec",$sql);
        self::doConnect();
        return self::$dbm->Execute($sql);
    }

    /** 
     *  
     */
    protected final function Query($sql) {
        $oDbm = & self::$dbm;
        self::ExecHook("on_query",$sql);
        self::doConnect();
        return $oDbm->BufferedQuery($sql);
    }


    /** 
     *  
     */
    final function getTableStructure() {
        $oSql = & self::$sql;
        $sql  = $oSql->getTableDetails($this->table);
        $result = self::query($sql);
        if (!$result) {
            return false;
        }
        return $oSql->ProcessTableDetails($result);
    }
    
    /** 
     *  
     */
    final private function get_relation($relation) {
        foreach($this->schema() as $col=>$value) {
            if (!$value InstanceOf DB) continue;
            $value->extra = strtolower($value->extra);
            $relation     = strtolower($relation);
            if ($value->type=='relation' && $value->extra==$relation) {
                return $col;
            }
        }
        return false;
    }

    /** 
     *  
     */
    final public function create_table($add_id=true) {
        $sql = & self::$sql;
        $model=$this->schema();
        if (!$add_id) {
            unset($model['id']);
        }
        $table = $this->getTableStructure();
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
                if (!isset($model[$id])) {
                    $this->del_column($id,$column);
                }
            }
        }
        $this->check_relations();
    }
    
    /**
     *
     */
    final private function check_relations() {
        foreach($this->schema() as $xcol => $def) {
            if (!$def InstanceOf DB) continue;
            if ($def->type == 'relation') {
                if (!is_subclass_of($def->extra,"easyorm")) {
                    throw new DBException(DBException::RELCLASS,$this->table,$col,$def->extra);
                }
                $rel = new $def->extra;
                $rschema=$rel->schema();
                $col =  $rel->get_relation(get_class($this));
                if ($col===false) {
                    throw new DBException(DBException::RELCOL,$this->table,$def->extra);
                }
                if ($rschema[$col]->rel == DB::MANY && DB::MANY == $def->rel) {
                    /**
                     *  Many <-> Many
                     *  Create a auxiliar table to save the relation ship.
                     */
                    $nmodel = strtolower($def->extra);
                    $model  = strtolower($this->table);
                    $tmp = new DevelORM;
                    $tmp->table = strcmp($model,$nmodel)<1 ? "${model}_{$nmodel}" : "{$nmodel}_$model";
                    $tmp->schema();
                    $tmp->$nmodel  = DB::Integer(array("required"=>true));
                    $tmp->$model   = DB::Integer(array("required"=>true));
                    /* create reference (many::many) table */
                    $tmp->create_table(false); 
                    /* create unique index */
                    $tmp->add_index(DB::UNIQUE,array($model,$nmodel));
                    $tmp = null;/* release memory */
                } else if ($def->rel == DB::ONE and $rschema[$col]->rel == DB::MANY) {
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
    
    /**
     *
     */
    final function get_index() {
        $sql = & self::$sql;
        $index = $this->Query($sql->GetIndexs($this->table));
        return $sql->ProcessIndexs($index);
    }

    /**
     *
     */
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
                throw new DBException(DBException::TYPE,$type);
                break;
        }
    }

    /** 
     *  Add Column
     *
     *  This function add a new column to the table.
     *
     *  @param string $column Column name, 
     *  @param DB $def Table definition
     *  @return bool True if success
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
        switch (strtolower($action)) {
            case "add":
                break;
            default:
                break;
        }
        return $this;
    }

    final private function get_row_data() {
        $_data = & $this->_data;
        $params = array();
        foreach($this->schema() as $col=>$def) {
            if (!isset($_data[$col]))
                continue;
            $params[$col] = $_data[$col];
        }
        return $params;
    }

    /**
     *
     *
     *
     */
    final public function save() {
        $sql   = & self::$sql;
        if (!isset($this->_data['id'])) {
            $params = $this->get_row_data();
            $iSql   = $sql->Insert($this->table,$params);
            $this->Execute($iSql);
            $this->id = self::$dbm->Get_Insert_Id(); 
        } else {
            $params = $this->get_row_data();
            $iSql   = $sql->Update($this->table,$params,array("id"=>$params['id']));
            $this->Execute($iSql);
        }
    }

    final public function __set($var,$value) {
        switch($var) {
            case "table":
                $this->$var = strtolower($value);
                break;
            default:
                if ($value instanceof DB) {
                    $this->_schema[$var] = $value;
                    return; 
                }
                $this->_data[$var] = $value;
                break;
        } 
    }

    final public function __get($var) {
        return isset($this->$var) ? $this->$var : false;
    }

    abstract function data();
}


final class DevelORM extends EasyORM {
    function data() {}
}

EasyORM::import("exception.php");
EasyORM::import("type.php");
EasyORM::import("sql.php");
?>
