<?php
class HelloWorld extends Prototype {

    function hello ( $app ) {
        echo 'Hello World!';
    }

    function post_save_entry ( &$cb, $app, $obj ) {
    }
}