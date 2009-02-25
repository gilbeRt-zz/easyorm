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

EasyORM::import("sql.php");

abstract class EasyORM {
    private static $drivers;
    private static $dbm;
    private static $sql;
    public $table=false;

    final function __construct() {
        if ($this->table === false) {
            $this->table = get_class($this);
        }
        $this->data();
    }

    public static function SetDB($param) {
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

    public static function registerDriver($driver,$dbm,$sql) {
        if (!is_subclass_of($sql,"StdSQL")) {
            throw new Exception("$sql is not a subclass of StdSQL");
        }
        if (array_search("DBMBase",class_implements($dbm))===false) {
            throw new Exception("$dbm do not implements DBMBase interface");
        }
        self::$drivers[$driver] = array("dbm"=> $dbm,"sql"=> $sql);
    }

    public static function isDriver($driver) {
        return isset(self::$drivers[$driver]);
    }

    public static function import($file){
        static $loaded=array();
        $file=dirname(__FILE__)."/$file";
        if (is_file($file) && !isSet($loaded[$file])) {
            include($file);
            $loaded[$file] = true;
        }
        return isset($loaded[$file]);
    }

    public static function SetupAll() {
        self::import("devel.php");
    }

    private static function doConnect() {
        $oDbm = & self::$dbm;
        if (!$oDbm->isConnected()) {
            if (!$oDbm->doConnect()) {
                throw new Exception("Error while connecting to the DB");
            }
        }
    }

    public static function Execute($sql) {
        return self::$dbm->Execute($sql);
    }

    private static function Query($sql) {
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

    final function __call($name,$params) {
        $action = substr($name,0,3);
        var_dump($name,$params);
        switch (strtolower($action)) {
            case "add":
                break;
            default:
                break;
        }
        die();
    }

    final public function save() {
    }

    abstract function data();
}

class DB {
    const ONE='one';
    const MANY='many';
    public $type;
    public $size;
    public $rel;
    public $extra;

    function __construct($type,$size=0,$rel=null,$extra=null) {
        $this->type=$type;
        $this->size=$size;
        $this->rel =$rel;
        $this->extra=$extra;
    }

    public static function String($length) {
        return new DB("string",$length);
    }

    public static function Integer($length=11) {
        return new DB("integer",$length);
    }

    public static function Relation($class,$rel=DB::ONE) {
        if (!is_subclass_of($class,"EasyORM")) {
            throw new Exception("$class is not an EasyORM subclass");
        }
        return new DB("relation",0,$rel,$class);
    }
}


?>
