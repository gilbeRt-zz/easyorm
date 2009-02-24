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
error_reporting(E_ALL);
include("../easyorm/easyorm.php");

class Author extends EasyORM {
    function data() {
        $this->name     = DB::String(50);
        $this->surname  = DB::String(50);
        $this->book     = DB::Relation("Books",DB::MANY);
    }
}

class Books extends EasyORM {
    function data() {
        $this->author   = DB::Relation("Author",DB::ONE);
        $this->title    = DB::String(50);
        $this->pages    = DB::Integer();
        $this->tags     = DB::Relation("tags",DB::MANY);
    }
}

class Tags extends EasyORM {
    function data() {
        $this->tag  = DB::String(20);
        $this->book = DB::Relation("Books",DB::MANY);
    }
}

/* Set connection parameter */
EasyORM::SetDB("mysql://root@localhost/easyorm");

EasyORM::SetupAll();

/* insert */
$author = new Author;
$author->name = "Foobar";
$author->surname = "Author";
$author->save();

/* add two books */
$author->addBook(array("pages"=>30,"title"=>"somebook"),array("pages"=>20,"title"=>"another one"));

/*  adding a book  (another way") */
$book = new Book;
$book->author = $author;
$book->pages = 30;
$book->title = "Foobar text";
$book->save();

/* adding another book (another way)  */
new Book(array("author"=>$author,"pages"=>20,"title"=>"foobar"));

/* select and update test */
$books = new Book;
foreach($books->ByAuthor($author) as $book) {
    $book->pages = 20; 
    $book->save();
}

/* select (passing a value instead of a object) and update test */
$books = new Book;
foreach($books->ByAuthorId($author->id) as $book) {
    $book->pages = 20; 
    $book->save();
}

/* another way to get author's book */
foreach($author->getBook() as $book) {
    $book->pages=30;
    $book->save();
}
?>
