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

class DevelORM extends EasyORM {
    function data() {}
}

function easyorm_get_model_relationship($model,$relation) {
    if (!$model InstanceOf EasyORM) return false;
    foreach(get_object_vars($model) as $col=>$value) {
        if (!$value InstanceOf DB) continue;
        if ($value->type=='relation' && strtolower($value->extra)==strtolower($relation)) {
            return $col;
        }
    }
    return false;
}

function easyorm_check_model($model) {
    if (!is_subclass_of($model,"easyorm")) {
        throw new Exception("$model is not a EasyORM subclass");
    }
    $dbm = new $model;
    $dbm->scheme();
    /**
      *  Check relationship with other classes
      */
    foreach(get_object_vars($dbm) as $col=>$val) {
        if (!$val InstanceOf DB) continue;
        if ($val->type=='relation') {
            if (!is_subclass_of($val->extra,"easyorm")) {
                throw new Exception("Error, $mode::$col reference to a class doesn't exists {$val->extra}");
            }
            $rel = new $val->extra;
            $rel->scheme();
            $col = easyorm_get_model_relationship($rel,$model);
            if ($col===false) {
                throw new Exception("There is not a column that represent the relationship to $model into {$val->extra}");
            }
            if ($rel->$col->rel == DB::MANY && DB::MANY == $val->rel) {
                /**
                 *  Many <-> Many
                 *  Create a auxiliar table to save the relation ship.
                 */
                $table = new DevelORM;
                $table->table = strcmp($model,$val->extra) ? "${model}_{$val->extra}" : "{$val->extra}_$model";
                $nmodel = $val->extra;
                $table->$nmodel  = DB::Integer(array("not_null"=>true));
                $table->$model   = DB::Integer(array("not_null"=>true));
                /* create reference (many::many) table */
                easyorm_create_table($table,false);
            } else if ($val->rel == DB::MANY) {
                /**
                 *  Many -> One relation ship, create an
                 *  index.
                 */
                easyorm_create_index($dbm->table,$col);
            }
        }
    }
    /* now compare the model against the DB table */
    easyorm_create_table($dbm,$dbm->getTableStructure());
}

function easyorm_create_table($model,$table) {
    if ($table===false) {
        /* create the table */
        $model->create_table(get_object_vars($model));
    } else {
        /* compare our model against the table (add new column) */
        foreach(get_object_vars($model) as $col=>$def) {
            if (!$def InstanceOf DB) continue;
            if ($def->type == 'relation') continue;
            if (!isset($table[$col])) {
                $model->add_column($col,$def);
            }
        }
        /* compare table against model (delete column) */
        foreach($table as $id=>$column) {
            if (!isset($model->$id)) {
                $model->del_column($id,$column);
            }
        }
    }
}

function easyorm_create_index() {
}

foreach(get_declared_classes() as $class) {
    if (!is_subclass_of($class,"easyorm") || strtolower($class)=='develorm') continue;
    easyorm_check_model($class);
}

?>
