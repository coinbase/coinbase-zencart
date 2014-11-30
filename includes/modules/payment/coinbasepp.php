<?php

// Coinbase payment plugin
class coinbasepp {
  
  var $code;
  var $title;
  var $description;
  var $enabled;
  
  // Constructor
  function coinbasepp() {
    
    $this->code = 'coinbasepp';
    $this->title = MODULE_PAYMENT_COINBASE_TEXT_TITLE;
    $this->description = MODULE_PAYMENT_COINBASE_TEXT_DESCRIPTION;
    $this->enabled = ((MODULE_PAYMENT_COINBASE_STATUS == 'True') ? true : false);
    $this->sort_order = MODULE_PAYMENT_COINBASE_SORT_ORDER;
    $this->order_status = MODULE_PAYMENT_COINBASE_PROCESSING_STATUS_ID;
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
                 'module' => MODULE_PAYMENT_COINBASE_TEXT_CHECKOUT
                 );
  }
  
  function process_button() {
    return false;
  }
  
  function before_process() {
    return false;
  }
  
  function after_process() {
    global $insert_id, $db, $order;
    
    $info = $order->info;
    
    $name = "Order #" . $insert_id;
    $custom = $insert_id;
    $currencyCode = $info['currency'];
    $total = $info['total'];
    $callback = zen_href_link('coinbasepp_callback.php', $parameters='', $connection='NONSSL', $add_session_id=true, $search_engine_safe=true, $static=true );
    $params = array (
      'description' => $name,
      'callback_url' => $callback . "?type=" . MODULE_PAYMENT_COINBASE_CALLBACK_SECRET,
      'success_url' => $callback . "?type=success",
      'cancel_url' => $callback . "?type=cancel",
      'info_url' => zen_href_link('index')
    );
    
    require_once(dirname(__FILE__) . "/coinbase/Coinbase.php");
    
    $oauth = new Coinbase_Oauth(MODULE_PAYMENT_COINBASE_OAUTH_CLIENTID, MODULE_PAYMENT_COINBASE_OAUTH_CLIENTSECRET, null);
    $tokens = unserialize(MODULE_PAYMENT_COINBASE_OAUTH);
    $coinbase = new Coinbase($oauth, $tokens);
    
    try {
      $code = $coinbase->createButton($name, $total, $currencyCode, $custom, $params)->button->code;
    } catch (Coinbase_TokensExpiredException $e) {
      try {
        $tokens = $oauth->refreshTokens($tokens);
      } catch (Exception $f) {
        $this->tokenFail($f->getMessage());
      }
      $coinbase = new Coinbase($oauth, $tokens);
      $db->Execute("update ". TABLE_CONFIGURATION. " set configuration_value = '" . $db->prepare_input(serialize($tokens)) . "' where configuration_key = 'MODULE_PAYMENT_COINBASE_OAUTH'");

      $code = $coinbase->createButton($name, $total, $currencyCode, $custom, $params)->button->code;
    }
    
    $_SESSION['cart']->reset(true);
    $_SESSION['coinbasepp_order_id'] = $insert_id;
    zen_redirect("https://coinbase.com/checkouts/$code");
    
    return false;
  }
  
  function tokenFail($msg) {
    
    global $db;
    $db->Execute("update ". TABLE_CONFIGURATION. " set configuration_value = '' where configuration_key = 'MODULE_PAYMENT_COINBASE_OAUTH'");
    $db->Execute("update ". TABLE_CONFIGURATION. " set configuration_value = '' where configuration_key = 'MODULE_PAYMENT_COINBASE_OAUTH_CLIENTID'");
    $db->Execute("update ". TABLE_CONFIGURATION. " set configuration_value = '' where configuration_key = 'MODULE_PAYMENT_COINBASE_OAUTH_CLIENTSECRET'");
    throw new Exception("No account is connected, or the current account is not working. You need to connect a merchant account in ZenCart Admin. $msg");
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
    
    $callbackSecret = md5('zencart_' . mt_rand());
  
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable Coinbase Module', 'MODULE_PAYMENT_COINBASE_STATUS', 'True', 'Enable the Coinbase bitcoin plugin?', '6', '0', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort order of display.', 'MODULE_PAYMENT_COINBASE_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '8', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Pending Notification Status', 'MODULE_PAYMENT_COINBASE_PROCESSING_STATUS_ID', '" . DEFAULT_ORDERS_STATUS_ID .  "', 'Set the status of orders made with this payment module that are not yet completed to this value<br />(\'Pending\' recommended)', '6', '5', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Order Status', 'MODULE_PAYMENT_COINBASE_COMPLETE_STATUS_ID', '2', 'Set the status of orders made with this payment module that have completed payment to this value<br />(\'Processing\' recommended)', '6', '6', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Merchant Account', 'MODULE_PAYMENT_COINBASE_OAUTH', '', '', '6', '6', 'coinbasepp_oauth_set(', 'coinbasepp_oauth_use', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added, use_function) values ('Client ID', 'MODULE_PAYMENT_COINBASE_OAUTH_CLIENTID', '', '', '6', '6', now(), 'coinbasepp_censor_use')");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added, use_function) values ('Client Secret', 'MODULE_PAYMENT_COINBASE_OAUTH_CLIENTSECRET', '', '', '6', '6', now(), 'coinbasepp_censor_use')");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added, use_function) values ('Callback Secret Key (do not edit)', 'MODULE_PAYMENT_COINBASE_CALLBACK_SECRET', '$callbackSecret', '', '6', '6', now(), 'coinbasepp_censor_use')");
  }
  
  function remove() {
    global $db;
    $db->Execute("delete from " . TABLE_CONFIGURATION . " where configuration_key LIKE 'MODULE\_PAYMENT\_COINBASE\_%'");
  }
  
  function keys() {
    return array(
      'MODULE_PAYMENT_COINBASE_STATUS',
      'MODULE_PAYMENT_COINBASE_SORT_ORDER',
      'MODULE_PAYMENT_COINBASE_PROCESSING_STATUS_ID',
      'MODULE_PAYMENT_COINBASE_COMPLETE_STATUS_ID',
      'MODULE_PAYMENT_COINBASE_OAUTH',
      'MODULE_PAYMENT_COINBASE_OAUTH_CLIENTID',
      'MODULE_PAYMENT_COINBASE_OAUTH_CLIENTSECRET',
      'MODULE_PAYMENT_COINBASE_CALLBACK_SECRET',
    );
  }

}

function coinbasepp_censor_use($value) {
  return "(hidden for security reasons)";
}

// Used in OAuth settings
function coinbasepp_oauth_use($value) {
  $redirectUrl = dirname(dirname(zen_href_link('modules.php'))) . "/coinbasepp_callback.php?type=oauth&after=" . urlencode($_SERVER['REQUEST_URI']);
  if($value == "") {
    if(MODULE_PAYMENT_COINBASE_OAUTH_CLIENTID) {
      require_once(dirname(__FILE__) . "/coinbase/Coinbase.php");
      $oauth = new Coinbase_Oauth(MODULE_PAYMENT_COINBASE_OAUTH_CLIENTID, MODULE_PAYMENT_COINBASE_OAUTH_CLIENTSECRET, $redirectUrl);
      $authorize = $oauth->createAuthorizeUrl('merchant');
      return "No merchant account connected: <a href='$authorize'>click here to connect account</a>";
    } else {
      return "No merchant account connected; not ready to accept payments (click Edit to set up an account)";
    }
  } else {
    return "Account connected; Coinbase plugin ready to accept payments";
  }
}

// Used in OAuth settings
function coinbasepp_oauth_set($value, $key) {
  $redirectUrlNoParams = dirname(dirname(zen_href_link('modules.php'))) . "/coinbasepp_callback.php";
  $redirectUrl = $redirectUrlNoParams . "?type=oauth&after=" . urlencode($_SERVER['REQUEST_URI']);
  
  if(MODULE_PAYMENT_COINBASE_OAUTH_CLIENTID != "" && MODULE_PAYMENT_COINBASE_OAUTH == "") {

    require_once(dirname(__FILE__) . "/coinbase/Coinbase.php");
    $oauth = new Coinbase_Oauth(MODULE_PAYMENT_COINBASE_OAUTH_CLIENTID, MODULE_PAYMENT_COINBASE_OAUTH_CLIENTSECRET, $redirectUrl);
    $authorize = $oauth->createAuthorizeUrl('merchant');
  
    return "No merchant account is connected.<br><br>
    <a href='$authorize'>Click here to connect a merchant account.</a>";
  } else if(MODULE_PAYMENT_COINBASE_OAUTH == "") {
    return "No merchant account is connected.<br><br>
    To connect an account and start accepting bitcoin:<br>
    1. If you don't have a Coinbase account, sign up for one at <a href='https://coinbase.com'>https://coinbase.com</a>.<br><br>
    2. <a href='https://coinbase.com/oauth/applications/new'>Click here to add a new OAuth2 application</a> and enter the following information:<br>
    <b>Name:</b> a name for this Zencart installation.<br>
    <b>Redirect URL:</b> <input type='text' value='$redirectUrlNoParams' readonly><br><br>
    3. Click 'Submit', and copy and paste the generated Client ID and Client Secret below. Keep these values secret. After saving these settings, return to this page to finish setting up the plugin.";
  } else {
    $tokens = MODULE_PAYMENT_COINBASE_OAUTH;
    $onclick = "document.getElementsByName('configuration[MODULE_PAYMENT_COINBASE_OAUTH]')[0].value='';
                document.getElementsByName('configuration[MODULE_PAYMENT_COINBASE_OAUTH_CLIENTID]')[0].value='';
                document.getElementsByName('configuration[MODULE_PAYMENT_COINBASE_OAUTH_CLIENTSECRET]')[0].value='';
                document.getElementById('coinbasepp-tokens').form.submit();";
    return "<script type='text/javascript'>function coinbasepp_onclick() { $onclick }</script>Merchant account connected. <a href='javascript:;' onclick='coinbasepp_onclick();'>Disconnect account</a><input type='hidden' value='$tokens' name='configuration[MODULE_PAYMENT_COINBASE_OAUTH]' id='coinbasepp-tokens' />";
  }
}
