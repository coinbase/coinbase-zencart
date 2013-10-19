<?php

// Coinbase payment plugin
class coinbase {
  
  var $code;
  var $title;
  var $description;
  var $enabled;
  
  // Constructor
  function coinbase() {
    
    $this->code = 'coinbase';
    $this->title = MODULE_PAYMENT_COINBASE_TEXT_TITLE;
    $this->description = MODULE_PAYMENT_COINBASE_TEXT_DESCRPITION;
    $this->enabled = true;
  }
  
  function selection() {
    return array('id' => $this->code,
                 'module' => $this->title
                 );
  }
  
  function check() {
    return true;
  }
  
  function install() {
  
  }
  
  function remove() {
  
  }
  
  function keys() {
    return array(
    
    );
  }

}