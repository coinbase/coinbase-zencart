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
  
  /**
   * JS validation which does error-checking of data-entry if this module is selected for use
    */
  function javascript_validation() {
    return false;
  }
  /**
   * Evaluate the Credit Card Type for acceptance and the validity of the Credit Card Number & Expiration Date
   */
  function pre_confirmation_check() {
    return false;
  }
  /**
   * Display Credit Card Information on the Checkout Confirmation Page
    */
  function confirmation() {
    return false;
  }
  
  function selection() {
    return array('id' => $this->code,
                 'module' => $this->title
                 );
  }
  
  function check() {
    global $db;
    if (!isset($this->_check)) {
      $check_query = $db->Execute("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_COINBASE_STATUS'");
      $this->_check = $check_query->RecordCount();
    }
    return $this->_check;
  }
  
  function install() {
    global $db, $messageStack;
    if (defined('MODULE_PAYMENT_COINBASE_STATUS')) {
      $messageStack->add_session('Coinbase module already installed.', 'error');
      zen_redirect(zen_href_link(FILENAME_MODULES, 'set=payment&module=coinbase', 'NONSSL'));
      return 'failed';
    }
  
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable Coinbase Module', 'MODULE_PAYMENT_COINBASE_STATUS', 'True', 'Enable the Coinbase bitcoin plugin?', '6', '0', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
  }
  
  function remove() {
    global $db;
    $db->Execute("delete from " . TABLE_CONFIGURATION . " where configuration_key LIKE 'MODULE\_PAYMENT\_COINBASE\_%'");
  }
  
  function keys() {
    return array(
      'MODULE_PAYMENT_COINBASE_STATUS'
    );
  }

}