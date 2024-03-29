<?php
/* -----------------------------------------------------------------------------------------
   $Id: yandex_merchant_ac.php 998 2007/02/07 13:24:46 VaM $

   VaM Shop - open source ecommerce solution
   http://vamshop.ru
   http://vamshop.com

   Copyright (c) 2007 VaM Shop
   -----------------------------------------------------------------------------------------
   based on: 
   (c) 2000-2001 The Exchange Project  (earlier name of osCommerce)
   (c) 2002-2003 osCommerce(moneyorder.php,v 1.8 2003/02/16); www.oscommerce.com 
   (c) 2003	 nextcommerce (moneyorder.php,v 1.4 2003/08/13); www.nextcommerce.org
   (c) 2004	 xt:Commerce (webmoney.php,v 1.4 2003/08/13); xt-commerce.com

   Released under the GNU General Public License 
   ---------------------------------------------------------------------------------------*/

  class yandex_merchant_ac {
    var $code, $title, $description, $enabled;

// class constructor
    function __construct() {
      global $order;

      $this->code = 'yandex_merchant_ac';
      $this->title = MODULE_PAYMENT_YANDEX_MERCHANT_AC_TEXT_TITLE;
      $this->public_title = MODULE_PAYMENT_YANDEX_MERCHANT_AC_TEXT_PUBLIC_TITLE;
      $this->icon = DIR_WS_ICONS . 'yandex.png';
      $this->description = MODULE_PAYMENT_YANDEX_MERCHANT_AC_TEXT_ADMIN_DESCRIPTION;
      $this->sort_order = MODULE_PAYMENT_YANDEX_MERCHANT_AC_SORT_ORDER;
      $this->enabled = ((MODULE_PAYMENT_YANDEX_MERCHANT_AC_STATUS == 'True') ? true : false);

      ((MODULE_PAYMENT_YANDEX_MERCHANT_AC_TEST == 'test') ? $this->form_action_url = 'https://demomoney.yandex.ru/eshop.xml' : $this->form_action_url = 'https://money.yandex.ru/eshop.xml');
      
    }

// class methods
    function update_status() {
      global $order;

      if ( ($this->enabled == true) && ((int)MODULE_PAYMENT_YANDEX_MERCHANT_AC_ZONE > 0) ) {
        $check_flag = false;
        $check_query = vam_db_query("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . MODULE_PAYMENT_YANDEX_MERCHANT_AC_ZONE . "' and zone_country_id = '" . $order->billing['country']['id'] . "' order by zone_id");
        while ($check = vam_db_fetch_array($check_query)) {
          if ($check['zone_id'] < 1) {
            $check_flag = true;
            break;
          } elseif ($check['zone_id'] == $order->billing['zone_id']) {
            $check_flag = true;
            break;
          }
        }

        if ($check_flag == false) {
          $this->enabled = false;
        }
      }
    }

    function javascript_validation() {
      return false;
    }

    function selection() {

      if (isset($_SESSION['cart_yandex_id'])) {
        $order_id = substr($_SESSION['cart_yandex_id'], strpos($_SESSION['cart_yandex_id'], '-')+1);

        $check_query = vam_db_query('select orders_id from ' . TABLE_ORDERS_STATUS_HISTORY . ' where orders_id = "' . (int)$order_id . '" limit 1');

        if (vam_db_num_rows($check_query) < 1) {
          vam_db_query('delete from ' . TABLE_ORDERS . ' where orders_id = "' . (int)$order_id . '"');
          vam_db_query('delete from ' . TABLE_ORDERS_TOTAL . ' where orders_id = "' . (int)$order_id . '"');
          vam_db_query('delete from ' . TABLE_ORDERS_STATUS_HISTORY . ' where orders_id = "' . (int)$order_id . '"');
          vam_db_query('delete from ' . TABLE_ORDERS_PRODUCTS . ' where orders_id = "' . (int)$order_id . '"');
          vam_db_query('delete from ' . TABLE_ORDERS_PRODUCTS_ATTRIBUTES . ' where orders_id = "' . (int)$order_id . '"');
          vam_db_query('delete from ' . TABLE_ORDERS_PRODUCTS_DOWNLOAD . ' where orders_id = "' . (int)$order_id . '"');

          unset($_SESSION['cart_yandex_id']);
        }
      }

      if (vam_not_null($this->icon)) $icon = vam_image($this->icon, $this->title);

      return array('id' => $this->code,
      				'icon' => $icon,
                   'module' => $this->public_title);

    }

    function pre_confirmation_check() {
      global $cartID, $cart;

      if (empty($_SESSION['cart']->cartID)) {
        $_SESSION['cartID'] = $_SESSION['cart']->cartID = $_SESSION['cart']->generate_cart_id();
      }

      if (!isset($_SESSION['cartID'])) {
        $_SESSION['cartID'] = $_SESSION['cart']->generate_cart_id();
      }
    }

    function confirmation() {
      global $cartID, $customer_id, $languages_id, $order, $order_total_modules;

      if (isset($_SESSION['cartID'])) {
        $insert_order = false;

        if (isset($_SESSION['cart_yandex_id'])) {
          $order_id = substr($_SESSION['cart_yandex_id'], strpos($_SESSION['cart_yandex_id'], '-')+1);
          $curr_check = vam_db_query("select currency from " . TABLE_ORDERS . " where orders_id = '" . (int)$order_id . "'");
          $curr = vam_db_fetch_array($curr_check);

          if ( ($curr['currency'] != $order->info['currency']) || ($cartID != substr($_SESSION['cart_yandex_id'], 0, strlen($cartID))) ) {
            $check_query = vam_db_query('select orders_id from ' . TABLE_ORDERS_STATUS_HISTORY . ' where orders_id = "' . (int)$order_id . '" limit 1');

            if (vam_db_num_rows($check_query) < 1) {
              vam_db_query('delete from ' . TABLE_ORDERS . ' where orders_id = "' . (int)$order_id . '"');
              vam_db_query('delete from ' . TABLE_ORDERS_TOTAL . ' where orders_id = "' . (int)$order_id . '"');
              vam_db_query('delete from ' . TABLE_ORDERS_STATUS_HISTORY . ' where orders_id = "' . (int)$order_id . '"');
              vam_db_query('delete from ' . TABLE_ORDERS_PRODUCTS . ' where orders_id = "' . (int)$order_id . '"');
              vam_db_query('delete from ' . TABLE_ORDERS_PRODUCTS_ATTRIBUTES . ' where orders_id = "' . (int)$order_id . '"');
              vam_db_query('delete from ' . TABLE_ORDERS_PRODUCTS_DOWNLOAD . ' where orders_id = "' . (int)$order_id . '"');
            }

            $insert_order = true;
          }
        } else {
          $insert_order = true;
        }

        if ($insert_order == true) {
          $order_totals = array();
          if (is_array($order_total_modules->modules)) {
            reset($order_total_modules->modules);
            while (list(, $value) = each($order_total_modules->modules)) {
              $class = substr($value, 0, strrpos($value, '.'));
              if ($GLOBALS[$class]->enabled) {
                for ($i=0, $n=sizeof($GLOBALS[$class]->output); $i<$n; $i++) {
                  if (vam_not_null($GLOBALS[$class]->output[$i]['title']) && vam_not_null($GLOBALS[$class]->output[$i]['text'])) {
                    $order_totals[] = array('code' => $GLOBALS[$class]->code,
                                            'title' => $GLOBALS[$class]->output[$i]['title'],
                                            'text' => $GLOBALS[$class]->output[$i]['text'],
                                            'value' => $GLOBALS[$class]->output[$i]['value'],
                                            'sort_order' => $GLOBALS[$class]->sort_order);
                  }
                }
              }
            }
          }

if ($_SESSION['customers_status']['customers_status_ot_discount_flag'] == 1) {
	$discount = $_SESSION['customers_status']['customers_status_ot_discount'];
} else {
	$discount = '0.00';
}

if ($_SERVER["HTTP_X_FORWARDED_FOR"]) {
	$customers_ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
} else {
	$customers_ip = $_SERVER["REMOTE_ADDR"];
}

          $sql_data_array = array('customers_id' => $_SESSION['customer_id'],
                                  'customers_name' => $order->customer['firstname'] . ' ' . $order->customer['lastname'],
                                  'customers_cid' => $order->customer['csID'],
                                  'customers_vat_id' => $_SESSION['customer_vat_id'],
                                  'customers_company' => $order->customer['company'],
                                  'customers_status' => $_SESSION['customers_status']['customers_status_id'],
                                  'customers_status_name' => $_SESSION['customers_status']['customers_status_name'],
                                  'customers_status_image' => $_SESSION['customers_status']['customers_status_image'],
                                  'customers_status_discount' => $discount,
                                  'customers_street_address' => $order->customer['street_address'],
                                  'customers_suburb' => $order->customer['suburb'],
                                  'customers_city' => $order->customer['city'],
                                  'customers_postcode' => $order->customer['postcode'],
                                  'customers_state' => $order->customer['state'],
                                  'customers_country' => $order->customer['country']['title'],
                                  'customers_telephone' => $order->customer['telephone'],
                                  'customers_email_address' => $order->customer['email_address'],
                                  'customers_address_format_id' => $order->customer['format_id'],
                                  'delivery_name' => $order->delivery['firstname'] . ' ' . $order->delivery['lastname'],
                                  'delivery_company' => $order->delivery['company'],
                                  'delivery_street_address' => $order->delivery['street_address'],
                                  'delivery_suburb' => $order->delivery['suburb'],
                                  'delivery_city' => $order->delivery['city'],
                                  'delivery_postcode' => $order->delivery['postcode'],
                                  'delivery_state' => $order->delivery['state'],
                                  'delivery_country' => $order->delivery['country']['title'],
                                  'delivery_address_format_id' => $order->delivery['format_id'],
                                  'billing_name' => $order->billing['firstname'] . ' ' . $order->billing['lastname'],
                                  'billing_company' => $order->billing['company'],
                                  'billing_street_address' => $order->billing['street_address'],
                                  'billing_suburb' => $order->billing['suburb'],
                                  'billing_city' => $order->billing['city'],
                                  'billing_postcode' => $order->billing['postcode'],
                                  'billing_state' => $order->billing['state'],
                                  'billing_country' => $order->billing['country']['title'],
                                  'billing_address_format_id' => $order->billing['format_id'],
                                  'payment_method' => $order->info['payment_method'],
                                  'payment_class' => $order->info['payment_class'],
                                  'shipping_method' => $order->info['shipping_method'],
                                  'shipping_class' => $order->info['shipping_class'],
                                  'language' => $_SESSION['language'],
                                  'comments' => $order->info['comments'],
                                  'customers_ip' => $customers_ip,
                                  'orig_reference' => $order->customer['orig_reference'],
                                  'login_reference' => $order->customer['login_reference'],
                                  'cc_type' => $order->info['cc_type'],
                                  'cc_owner' => $order->info['cc_owner'],
                                  'cc_number' => $order->info['cc_number'],
                                  'cc_expires' => $order->info['cc_expires'],
                                  'date_purchased' => 'now()',
                                  'orders_status' => $order->info['order_status'],
                                  'currency' => $order->info['currency'],
                                  'currency_value' => $order->info['currency_value']);

          vam_db_perform(TABLE_ORDERS, $sql_data_array);

          $insert_id = vam_db_insert_id();

			$customer_notification = (SEND_EMAILS == 'true') ? '1' : '0';
			$sql_data_array = array ('orders_id' => $insert_id, 'orders_status_id' => $order->info['order_status'], 'date_added' => 'now()', 'customer_notified' => $customer_notification, 'comments' => $order->info['comments']);
			vam_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);

          for ($i=0, $n=sizeof($order_totals); $i<$n; $i++) {
            $sql_data_array = array('orders_id' => $insert_id,
                                    'title' => $order_totals[$i]['title'],
                                    'text' => $order_totals[$i]['text'],
                                    'value' => $order_totals[$i]['value'],
                                    'class' => $order_totals[$i]['code'],
                                    'sort_order' => $order_totals[$i]['sort_order']);

            vam_db_perform(TABLE_ORDERS_TOTAL, $sql_data_array);
          }

          for ($i=0, $n=sizeof($order->products); $i<$n; $i++) {
            $sql_data_array = array('orders_id' => $insert_id,
                                    'products_id' => vam_get_prid($order->products[$i]['id']),
                                    'products_model' => $order->products[$i]['model'],
                                    'products_name' => $order->products[$i]['name'],
                                    'products_price' => $order->products[$i]['price'],
                                    'final_price' => $order->products[$i]['final_price'],
                                    'products_tax' => $order->products[$i]['tax'],
                                    'products_quantity' => $order->products[$i]['qty']);

            vam_db_perform(TABLE_ORDERS_PRODUCTS, $sql_data_array);

            $order_products_id = vam_db_insert_id();

            $attributes_exist = '0';
            if (isset($order->products[$i]['attributes'])) {
              $attributes_exist = '1';
              for ($j=0, $n2=sizeof($order->products[$i]['attributes']); $j<$n2; $j++) {
                if (DOWNLOAD_ENABLED == 'true') {
                  $attributes_query = "select popt.products_options_name, poval.products_options_values_name, pa.options_values_price, pa.price_prefix, pad.products_attributes_maxdays, pad.products_attributes_maxcount , pad.products_attributes_filename, pad.products_attributes_is_pin
                                       from " . TABLE_PRODUCTS_OPTIONS . " popt, " . TABLE_PRODUCTS_OPTIONS_VALUES . " poval, " . TABLE_PRODUCTS_ATTRIBUTES . " pa
                                       left join " . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD . " pad
                                       on pa.products_attributes_id=pad.products_attributes_id
                                       where pa.products_id = '" . $order->products[$i]['id'] . "'
                                       and pa.options_id = '" . $order->products[$i]['attributes'][$j]['option_id'] . "'
                                       and pa.options_id = popt.products_options_id
                                       and pa.options_values_id = '" . $order->products[$i]['attributes'][$j]['value_id'] . "'
                                       and pa.options_values_id = poval.products_options_values_id
                                       and popt.language_id = '" . $_SESSION['languages_id'] . "'
                                       and poval.language_id = '" . $_SESSION['languages_id'] . "'";
                  $attributes = vam_db_query($attributes_query);
                } else {
                  $attributes = vam_db_query("select popt.products_options_name, poval.products_options_values_name, pa.options_values_price, pa.price_prefix from " . TABLE_PRODUCTS_OPTIONS . " popt, " . TABLE_PRODUCTS_OPTIONS_VALUES . " poval, " . TABLE_PRODUCTS_ATTRIBUTES . " pa where pa.products_id = '" . $order->products[$i]['id'] . "' and pa.options_id = '" . $order->products[$i]['attributes'][$j]['option_id'] . "' and pa.options_id = popt.products_options_id and pa.options_values_id = '" . $order->products[$i]['attributes'][$j]['value_id'] . "' and pa.options_values_id = poval.products_options_values_id and popt.language_id = '" . $_SESSION['languages_id'] . "' and poval.language_id = '" . $_SESSION['languages_id'] . "'");
                }

			// update attribute stock
			vam_db_query("UPDATE ".TABLE_PRODUCTS_ATTRIBUTES." set
						                               attributes_stock=attributes_stock - '".$order->products[$i]['qty']."'
						                               where
						                               products_id='".$order->products[$i]['id']."'
						                               and options_values_id='".$order->products[$i]['attributes'][$j]['value_id']."'
						                               and options_id='".$order->products[$i]['attributes'][$j]['option_id']."'
						                               ");

                $attributes_values = vam_db_fetch_array($attributes);

                $sql_data_array = array('orders_id' => $insert_id,
                                        'orders_products_id' => $order_products_id,
                                        'products_options' => $attributes_values['products_options_name'],
                                        'products_options_values' => $attributes_values['products_options_values_name'],
                                        'options_values_price' => $attributes_values['options_values_price'],
                                        'price_prefix' => $attributes_values['price_prefix']);

                vam_db_perform(TABLE_ORDERS_PRODUCTS_ATTRIBUTES, $sql_data_array);

                if ((DOWNLOAD_ENABLED == 'true') && ((isset($attributes_values['products_attributes_filename']) && vam_not_null($attributes_values['products_attributes_filename'])) or $attributes_values['products_attributes_is_pin'])) {
				
        		//PIN add
		for($pincycle=0;$pincycle<$order->products[$i]['qty'];$pincycle++) {
          if($attributes_values['products_attributes_is_pin']) {
          	$pin_query=vam_db_query("SELECT products_pin_id, products_pin_code FROM ".TABLE_PRODUCTS_PINS." WHERE products_id = '".$order->products[$i]['id']."' AND products_pin_used='0' LIMIT 1");

          	if(vam_db_num_rows($pin_query)=='0') { // We have no PIN for this product
          		// insert some error notifying here
          		$pin=PIN_NOT_AVAILABLE;
          	} else {
          		$pin_res=vam_db_fetch_array($pin_query);
          		$pin=$pin_res['products_pin_code'];
          		vam_db_query("UPDATE ".TABLE_PRODUCTS_PINS." SET products_pin_used='".$insert_id."' WHERE products_pin_id = '".$pin_res['products_pin_id']."'");
          	}
          }
//PIN				
                  $sql_data_array = array('orders_id' => $insert_id,
                                          'orders_products_id' => $order_products_id,
                                          'orders_products_filename' => $attributes_values['products_attributes_filename'],
                                          'download_maxdays' => $attributes_values['products_attributes_maxdays'],
                                          'download_count' => $attributes_values['products_attributes_maxcount'],
                                          'download_is_pin' => $attributes_values['products_attributes_is_pin'],
                                          'download_pin_code' => $pin
                                         );

                  vam_db_perform(TABLE_ORDERS_PRODUCTS_DOWNLOAD, $sql_data_array);
                  }
                }
              }
            }
          }

          $_SESSION['cart_yandex_id'] = $cartID . '-' . $insert_id;
        }
      }

      return array('title' => MODULE_PAYMENT_YANDEX_MERCHANT_AC_TEXT_DESCRIPTION);
    }

    function process_button() {
      global $customer_id, $order, $sendto, $vamPrice, $currencies, $shipping;

      $process_button_string = '';

	    $order_sum = $order->info['total'];

    			$street_address = (!isset($order->delivery["street_address"])) ? null : $order->delivery["street_address"];
    			$city = (!isset($order->delivery["city"])) ? null : $order->delivery["city"] . ', ';
    			$postcode = (!isset($order->delivery["postcode"])) ? null : $order->delivery["postcode"] . ', ';
    			$state = (!isset($order->delivery["state"])) ? null : $order->delivery["state"] . ', ';
    			$country = (!isset($order->delivery["country"])) ? null : $order->delivery["country"] . ', ';
    			$ship_address = $postcode . $city . $street_address;
    			
      $process_button_string = vam_draw_hidden_field('shopId', MODULE_PAYMENT_YANDEX_MERCHANT_AC_SHOP_ID) .
                               vam_draw_hidden_field('scid', MODULE_PAYMENT_YANDEX_MERCHANT_AC_SCID) .
                               vam_draw_hidden_field('sum', $order_sum) . 
                               vam_draw_hidden_field('customerNumber', $order->customer['id']) .
                               vam_draw_hidden_field('orderNumber', substr($_SESSION['cart_yandex_id'], strpos($_SESSION['cart_yandex_id'], '-')+1)) .
                               vam_draw_hidden_field('shopSuccessURL', vam_href_link(FILENAME_CHECKOUT_PROCESS, '', 'SSL')) .
                               vam_draw_hidden_field('shopFailURL', vam_href_link(FILENAME_CHECKOUT_SHIPPING, '', 'SSL')) .
                               vam_draw_hidden_field('cps_email', $order->customer['email_address']) .
                               vam_draw_hidden_field('cps_phone', $order->customer['telephone']) . 
                               vam_draw_hidden_field('cms_name', 'vamshop') . 
                               vam_draw_hidden_field('paymentType', MODULE_PAYMENT_YANDEX_MERCHANT_AC_PAYMENT_TYPE);

      return $process_button_string;
    }

    function before_process() {
      global $customer_id, $order, $vamPrice, $order_totals, $sendto, $billto, $languages_id, $payment, $currencies, $cart;
      global $$payment;

      $order_id = substr($_SESSION['cart_yandex_id'], strpos($_SESSION['cart_yandex_id'], '-')+1);

// initialized for the email confirmation
      $products_ordered = '';
      $subtotal = 0;
      $total_tax = 0;

      for ($i=0, $n=sizeof($order->products); $i<$n; $i++) {
// Stock Update - Joao Correia
        if (STOCK_LIMITED == 'true') {
          if (DOWNLOAD_ENABLED == 'true') {
            $stock_query_raw = "SELECT products_quantity, pad.products_attributes_filename, pad.products_attributes_is_pin 
                                FROM " . TABLE_PRODUCTS . " p
                                LEFT JOIN " . TABLE_PRODUCTS_ATTRIBUTES . " pa
                                ON p.products_id=pa.products_id
                                LEFT JOIN " . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD . " pad
                                ON pa.products_attributes_id=pad.products_attributes_id
                                WHERE p.products_id = '" . vam_get_prid($order->products[$i]['id']) . "'";
// Will work with only one option for downloadable products
// otherwise, we have to build the query dynamically with a loop
            $products_attributes = $order->products[$i]['attributes'];
            if (is_array($products_attributes)) {
              $stock_query_raw .= " AND pa.options_id = '" . $products_attributes[0]['option_id'] . "' AND pa.options_values_id = '" . $products_attributes[0]['value_id'] . "'";
            }
            $stock_query = vam_db_query($stock_query_raw);
          } else {
            $stock_query = vam_db_query("select products_quantity from " . TABLE_PRODUCTS . " where products_id = '" . vam_get_prid($order->products[$i]['id']) . "'");
          }
          if (vam_db_num_rows($stock_query) > 0) {
            $stock_values = vam_db_fetch_array($stock_query);
// do not decrement quantities if products_attributes_filename exists
            if ((DOWNLOAD_ENABLED != 'true') || (!$stock_values['products_attributes_filename']) || ($stock_values['products_attributes_is_pin']==1) ) {
              $stock_left = $stock_values['products_quantity'] - $order->products[$i]['qty'];
            } else {
              $stock_left = $stock_values['products_quantity'];
            }
            vam_db_query("update " . TABLE_PRODUCTS . " set products_quantity = '" . $stock_left . "' where products_id = '" . vam_get_prid($order->products[$i]['id']) . "'");
            if ( ($stock_left < 1) && (STOCK_ALLOW_CHECKOUT == 'false') ) {
              vam_db_query("update " . TABLE_PRODUCTS . " set products_status = '0' where products_id = '" . vam_get_prid($order->products[$i]['id']) . "'");
            }
          }
        }

// Update products_ordered (for bestsellers list)
        vam_db_query("update " . TABLE_PRODUCTS . " set products_ordered = products_ordered + " . sprintf('%d', $order->products[$i]['qty']) . " where products_id = '" . vam_get_prid($order->products[$i]['id']) . "'");

//------insert customer choosen option to order--------
        $attributes_exist = '0';
        $products_ordered_attributes = '';
        if (isset($order->products[$i]['attributes'])) {
          $attributes_exist = '1';
          for ($j=0, $n2=sizeof($order->products[$i]['attributes']); $j<$n2; $j++) {
            if (DOWNLOAD_ENABLED == 'true') {
              $attributes_query = "select popt.products_options_name, poval.products_options_values_name, pa.options_values_price, pa.price_prefix, pad.products_attributes_maxdays, pad.products_attributes_maxcount , pad.products_attributes_filename, pad.products_attributes_is_pin
                                   from " . TABLE_PRODUCTS_OPTIONS . " popt, " . TABLE_PRODUCTS_OPTIONS_VALUES . " poval, " . TABLE_PRODUCTS_ATTRIBUTES . " pa
                                   left join " . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD . " pad
                                   on pa.products_attributes_id=pad.products_attributes_id
                                   where pa.products_id = '" . $order->products[$i]['id'] . "'
                                   and pa.options_id = '" . $order->products[$i]['attributes'][$j]['option_id'] . "'
                                   and pa.options_id = popt.products_options_id
                                   and pa.options_values_id = '" . $order->products[$i]['attributes'][$j]['value_id'] . "'
                                   and pa.options_values_id = poval.products_options_values_id
                                   and popt.language_id = '" . $_SESSION['languages_id'] . "'
                                   and poval.language_id = '" . $_SESSION['languages_id'] . "'";
              $attributes = vam_db_query($attributes_query);
            } else {
              $attributes = vam_db_query("select popt.products_options_name, poval.products_options_values_name, pa.options_values_price, pa.price_prefix from " . TABLE_PRODUCTS_OPTIONS . " popt, " . TABLE_PRODUCTS_OPTIONS_VALUES . " poval, " . TABLE_PRODUCTS_ATTRIBUTES . " pa where pa.products_id = '" . $order->products[$i]['id'] . "' and pa.options_id = '" . $order->products[$i]['attributes'][$j]['option_id'] . "' and pa.options_id = popt.products_options_id and pa.options_values_id = '" . $order->products[$i]['attributes'][$j]['value_id'] . "' and pa.options_values_id = poval.products_options_values_id and popt.language_id = '" . $_SESSION['languages_id'] . "' and poval.language_id = '" . $_SESSION['languages_id'] . "'");
            }
            $attributes_values = vam_db_fetch_array($attributes);

            $products_ordered_attributes .= "\n\t" . $attributes_values['products_options_name'] . ' ' . $attributes_values['products_options_values_name'];
          }
        }
//------insert customer choosen option eof ----
        $total_weight += ($order->products[$i]['qty'] * $order->products[$i]['weight']);
        $total_tax += vam_calculate_tax($total_products_price, $products_tax) * $order->products[$i]['qty'];
        $total_cost += $total_products_price;

        $products_ordered .= $order->products[$i]['qty'] . ' x ' . $order->products[$i]['name'] . ' (' . $order->products[$i]['model'] . ') = ' . $vamPrice->Format($order->products[$i]['final_price'], true) . $products_ordered_attributes . "\n";
      }

// initialize templates
$vamTemplate = new vamTemplate;

	$vamTemplate->assign('address_label_customer', vam_address_format($order->customer['format_id'], $order->customer, 1, '', '<br />'));
	$vamTemplate->assign('address_label_shipping', vam_address_format($order->delivery['format_id'], $order->delivery, 1, '', '<br />'));
	if ($_SESSION['credit_covers'] != '1') {
		$vamTemplate->assign('address_label_payment', vam_address_format($order->billing['format_id'], $order->billing, 1, '', '<br />'));
	}
	$vamTemplate->assign('csID', $order->customer['csID']);

  $it=0;
	$semextrfields = vamDBquery("select * from " . TABLE_EXTRA_FIELDS . " where fields_required_email = '1'");
	while($dataexfes = vam_db_fetch_array($semextrfields,true)) {
	$cusextrfields = vamDBquery("select * from " . TABLE_CUSTOMERS_TO_EXTRA_FIELDS . " where customers_id = '" . (int)$_SESSION['customer_id'] . "' and fields_id = '" . $dataexfes['fields_id'] . "'");
	$rescusextrfields = vam_db_fetch_array($cusextrfields,true);

	$extrfieldsinf = vamDBquery("select fields_name from " . TABLE_EXTRA_FIELDS_INFO . " where fields_id = '" . $dataexfes['fields_id'] . "' and languages_id = '" . $_SESSION['languages_id'] . "'");

	$extrfieldsres = vam_db_fetch_array($extrfieldsinf,true);
	$extra_fields .= $extrfieldsres['fields_name'] . ' : ' .
	$rescusextrfields['value'] . "\n";
	$vamTemplate->assign('customer_extra_fields', $extra_fields);
  }
	
	$order_total = $order->getTotalData($order_id);
		$vamTemplate->assign('order_data', $order->getOrderData($order_id));
		$vamTemplate->assign('order_total', $order_total['data']);

	// assign language to template for caching
	$vamTemplate->assign('language', $_SESSION['language']);
	$vamTemplate->assign('tpl_path', 'templates/'.CURRENT_TEMPLATE.'/');
	$vamTemplate->assign('logo_path', HTTP_SERVER.DIR_WS_CATALOG.'templates/'.CURRENT_TEMPLATE.'/img/');
	$vamTemplate->assign('oID', $order_id);
	if ($order->info['payment_method'] != '' && $order->info['payment_method'] != 'no_payment') {
		include (DIR_WS_LANGUAGES.$_SESSION['language'].'/modules/payment/'.$order->info['payment_method'].'.php');
		$payment_method = constant(strtoupper('MODULE_PAYMENT_'.$order->info['payment_method'].'_TEXT_TITLE'));
	}
	$vamTemplate->assign('PAYMENT_METHOD', $payment_method);
	if ($order->info['shipping_method'] != '') {
		$shipping_method = $order->info['shipping_method'];
	}
	$vamTemplate->assign('SHIPPING_METHOD', $shipping_method);
	$vamTemplate->assign('DATE', vam_date_long($order->info['date_purchased']));

	$vamTemplate->assign('NAME', $order->customer['firstname'] . ' ' . $order->customer['lastname']);
	$vamTemplate->assign('COMMENTS', $order->info['comments']);
	$vamTemplate->assign('EMAIL', $order->customer['email_address']);
	$vamTemplate->assign('PHONE',$order->customer['telephone']);

	// dont allow cache
	$vamTemplate->caching = false;

	$html_mail = $vamTemplate->fetch(CURRENT_TEMPLATE.'/mail/'.$_SESSION['language'].'/order_mail.html');
	$txt_mail = $vamTemplate->fetch(CURRENT_TEMPLATE.'/mail/'.$_SESSION['language'].'/order_mail.txt');

	// create subject
	$order_subject = str_replace('{$nr}', $order_id, EMAIL_BILLING_SUBJECT_ORDER);
	$order_subject = str_replace('{$date}', strftime(DATE_FORMAT_LONG), $order_subject);
	$order_subject = str_replace('{$lastname}', $order->customer['lastname'], $order_subject);
	$order_subject = str_replace('{$firstname}', $order->customer['firstname'], $order_subject);

	// send mail to admin
	vam_php_mail(EMAIL_BILLING_ADDRESS, EMAIL_BILLING_NAME, EMAIL_BILLING_ADDRESS, STORE_NAME, EMAIL_BILLING_FORWARDING_STRING, $order->customer['email_address'], $order->customer['firstname'], '', '', $order_subject, $html_mail, $txt_mail);

	// send mail to customer
	vam_php_mail(EMAIL_BILLING_ADDRESS, EMAIL_BILLING_NAME, $order->customer['email_address'], $order->customer['firstname'].' '.$order->customer['lastname'], '', EMAIL_BILLING_REPLY_ADDRESS, EMAIL_BILLING_REPLY_ADDRESS_NAME, '', '', $order_subject, $html_mail, $txt_mail);

// load the after_process function from the payment modules
      $this->after_process();

		require_once(DIR_WS_INCLUDES . 'affiliate_checkout_process.php');

      $_SESSION['cart']->reset(true);

// unregister session variables used during checkout
      unset($_SESSION['sendto']);
      unset($_SESSION['billto']);
      unset($_SESSION['shipping']);
      unset($_SESSION['payment']);
      unset($_SESSION['comments']);

      unset($_SESSION['cart_yandex_id']);

      vam_redirect(vam_href_link(FILENAME_CHECKOUT_SUCCESS, '', 'SSL'));
    }

    function after_process() {
      return false;
    }

    function output_error() {
      return false;
    }

    function check() {
      if (!isset($this->_check)) {
        $check_query = vam_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_YANDEX_MERCHANT_AC_STATUS'");
        $this->_check = vam_db_num_rows($check_query);
      }
      return $this->_check;
    }

    function install() {

      vam_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_key, configuration_value, configuration_group_id, sort_order, set_function, date_added) values ('MODULE_PAYMENT_YANDEX_MERCHANT_AC_STATUS', 'True', '6', '1', 'vam_cfg_select_option(array(\'True\', \'False\'), ', now())");
      vam_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_key, configuration_value, configuration_group_id, sort_order, date_added) values ('MODULE_PAYMENT_YANDEX_MERCHANT_AC_ALLOWED', '', '6', '2', now())");
      vam_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_key, configuration_value, configuration_group_id, sort_order, date_added) values ('MODULE_PAYMENT_YANDEX_MERCHANT_AC_SHOP_ID', '', '6', '3', now())");
      vam_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_key, configuration_value, configuration_group_id, sort_order, date_added) values ('MODULE_PAYMENT_YANDEX_MERCHANT_AC_SCID', '', '6', '4', now())");
      vam_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_key, configuration_value, configuration_group_id, sort_order, date_added) values ('MODULE_PAYMENT_YANDEX_MERCHANT_AC_SECRET_KEY', '', '6', '5', now())");
      vam_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_key, configuration_value, configuration_group_id, sort_order, date_added) values ('MODULE_PAYMENT_YANDEX_MERCHANT_AC_SORT_ORDER', '0', '8', '6', now())");
      vam_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_key, configuration_value, configuration_group_id, sort_order, use_function, set_function, date_added) values ('MODULE_PAYMENT_YANDEX_MERCHANT_AC_ZONE', '0', '6', '7', 'vam_get_zone_class_title', 'vam_cfg_pull_down_zone_classes(', now())");
      vam_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_key, configuration_value, configuration_group_id, sort_order, set_function, use_function, date_added) values ('MODULE_PAYMENT_YANDEX_MERCHANT_AC_ORDER_STATUS_ID', '0', '6', '8', 'vam_cfg_pull_down_order_statuses(', 'vam_get_order_status_name', now())");
      vam_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_key, configuration_value, configuration_group_id, sort_order, set_function, date_added) values ('MODULE_PAYMENT_YANDEX_MERCHANT_AC_TEST', 'test', '6', '9', 'vam_cfg_select_option(array(\'test\', \'production\'), ', now())");
      vam_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_key, configuration_value, configuration_group_id, sort_order, set_function, date_added) values ('MODULE_PAYMENT_YANDEX_MERCHANT_AC_PAYMENT_TYPE', 'AC', '6', '10', 'vam_cfg_select_option(array(\'PC\', \'AC\', \'MC\', \'GP\', \'WM\', \'SB\'), ', now())");
    }

    function remove() {
      vam_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }

    function keys() {
      return array('MODULE_PAYMENT_YANDEX_MERCHANT_AC_STATUS', 'MODULE_PAYMENT_YANDEX_MERCHANT_AC_ALLOWED', 'MODULE_PAYMENT_YANDEX_MERCHANT_AC_SHOP_ID', 'MODULE_PAYMENT_YANDEX_MERCHANT_AC_SCID', 'MODULE_PAYMENT_YANDEX_MERCHANT_AC_SECRET_KEY', 'MODULE_PAYMENT_YANDEX_MERCHANT_AC_SORT_ORDER', 'MODULE_PAYMENT_YANDEX_MERCHANT_AC_ZONE', 'MODULE_PAYMENT_YANDEX_MERCHANT_AC_ORDER_STATUS_ID', 'MODULE_PAYMENT_YANDEX_MERCHANT_AC_TEST', 'MODULE_PAYMENT_YANDEX_MERCHANT_AC_PAYMENT_TYPE');
    }

  }
?>