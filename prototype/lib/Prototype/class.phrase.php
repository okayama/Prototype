<?php
class phrase extends PADOBaseModel {

    function save () {
        $app = Prototype::get_instance();
        $lang = $this->lang;
        $cache_key = 'phrase' . DS . "locale_{$lang}__c";
        $app->clear_cache( $cache_key );
        return parent::save();
    }

    function remove () {
        $app = Prototype::get_instance();
        $lang = $this->lang;
        $cache_key = 'phrase' . DS . "locale_{$lang}__c";
        $app->clear_cache( $cache_key );
        return parent::remove();
    }

}