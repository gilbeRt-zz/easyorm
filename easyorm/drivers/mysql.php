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


class MysqlSQL extends StdSQL {
    public function SkipValue($name) {
        return '"'.addslashes($name).'"';
    }

    public function SkipFieldName($name) {
        return "`$name`";
    }

    public function GetTableDetails($table) {
        return "DESC $table";
    }


    public function ProcessTableDetails($table) {
        if (!is_array($table) || count($table)==0) return false;
        $columns=array();
        foreach($table as $column) {
            list($type,$size) = $this->get_col_type($column->Type);
            $columns[$column->Field] = DB::$type($size);
        }
        return $columns;
    }

    public function GetIndexs($table) {
        return "SHOW INDEX FROM ".$this->SkipFieldName($table);
    }

    public function ProcessIndexs($indexs) {
        if(!is_array($indexs)) {
            return false;
        }
        $result = array();
        foreach($indexs as $index) {
            if (!is_object($index)) continue;
            $type = $index->Non_unique ? "index" : "unique";
            $result[$type][$index->Key_name][] = $index->Column_name;
        } 
        return $result;
    }

}

class MysqlDBM implements DBMBase {
    private $dbm=false;
    private $host;
    private $user;
    private $password;
    private $db;

    function __construct($host,$user,$password,$db) {
        $this->host = $host;
        $this->user = $user;
        $this->password = $password;
        $this->password = $password;
        $this->db = $db;
    }

    function doConnect() {
        $this->dbm = & $dbm;
        $dbm = mysql_connect($this->host,$this->user,$this->password);
        if ($dbm===false) return false;
        return mysql_select_db($this->db,$dbm);
    }

    function isConnected() {
        return $this->dbm !== false;
    }

    function BufferedQuery($sql) {
        $query = mysql_query($sql,$this->dbm);
        if ($query===false) {
            return false;
        }
        if (mysql_num_rows($query)==0) return;
        $r = array();
        while ($row = mysql_fetch_object($query)) 
            $r[] = $row;
        mysql_free_result($query);
        return $r;
    }

    function Execute($sql) {
        return mysql_unbuffered_query($sql,$this->dbm)!==false;
    }

    function Get_Insert_Id() {
        return mysql_insert_id($this->dbm);
    }
}

EasyORM::registerDriver("mysql","MysqlDBM","MysqlSQL");

?>
