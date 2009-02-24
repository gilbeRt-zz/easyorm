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


abstract class StdSQL {
    /**
     *  Select
     *
     *  This function generated, based on the arguments, the SQL ANSI 92 SELECT
     *  statement, usually this method is final (it is an standar).
     *
     *  @param string $table Table name
     *  @param string|array $rows Array with columns to select, or the string "*"
     *  @param array  $where 
     *  @param array  $order
     *  @return string The SQL statement.
     *  @todo Very weak, it must be improved.
     */
    public function select($table,$rows='*',$where=array(),$order=array()) {
        $sql = "SELECT ";
        if (is_array($rows)) {
            foreach($rows as $row) {
                $sql .= $this->SkipFieldName($row).",";
            }
            $sql[strlen($sql)-1] = ' ';
        } elseif ($rows=='*') {
            $sql .= '* ';
        } else {
            return false;
        }
        $sql .= "FROM $table";
        if (is_array($where) && count($where) > 0) {
            $sql .= " WHERE ";
            $wheresql = array();
            foreach($where as $col => $value) {
                $wheresql[] = $this->SkipFieldName($col)."=".$this->SkipValue($value);
            }
            $sql .= implode(" and ",$wheresql); 
        }
        if (is_array($order) && count($order) > 0) {
            $sql .= " ORDER BY ";
        }
        return $sql;
    }

    public function insert($table,$rows) {
        if (!is_array($rows) || count($rows) == 0) {
            return false;
        }
        $cols = implode(",",array_map(array(&$this,"SkipFieldName"),array_keys($rows)));
        $vals = implode(',',array_map(array(&$this,"SkipValue"),$rows));
        $sql = "INSERT INTO ".$this->SkipFieldName($table)."($cols) VALUES($vals)";
        return $sql;
    }

    abstract public function SkipValue($name);
    abstract public function SkipFieldName($name);
    abstract public function GetTableDetails($table);
    abstract public function ProcessTableDetails($table);
}

interface DBMBase {
    public function __construct($host,$user,$password,$db);
    public function doconnect();
    public function isconnected();
}

?>
