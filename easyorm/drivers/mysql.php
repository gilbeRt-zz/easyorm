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
        return "desc $table";
    }

    public function ProcessTableDetails($table) {
         
    }
}

class MysqlDBM implements DBMBase {
    private $dbm;

    public function connect($host,$db,$user,$password) {
        $this->dbm = & $dbm;
        $dbm = mysql_connect($host,$user,$passwor);
        if ($dbm===false) return false;
        return mysql_select_db($db,$dbm);
    }
}

EasyORM::registerDriver("mysql","MysqlDBM","MysqlSQL");

?>
