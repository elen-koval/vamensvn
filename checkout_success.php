<?php
/* -----------------------------------------------------------------------------------------
   $Id: checkout_success.php 896 2007-02-06 19:20:03 VaM $

   VaM Shop - open source ecommerce solution
   http://vamshop.ru
   http://vamshop.com

   Copyright (c) 2007 VaM Shop
   -----------------------------------------------------------------------------------------
   based on: 
   (c) 2000-2001 The Exchange Project  (earlier name of osCommerce)
   (c) 2002-2003 osCommerce(checkout_success.php,v 1.48 2003/02/17); www.oscommerce.com 
   (c) 2003	 nextcommerce (checkout_success.php,v 1.14 2003/08/17); www.nextcommerce.org
   (c) 2004	 xt:Commerce (checkout_success.php,v 1.14 2003/08/17); xt-commerce.com

   Released under the GNU General Public License
   -----------------------------------------------------------------------------------------
   Third Party contribution:

   Credit Class/Gift Vouchers/Discount Coupons (Version 5.10)
   http://www.oscommerce.com/community/contributions,282
   Copyright (c) Strider | Strider@oscworks.com
   Copyright (c  Nick Stanko of UkiDev.com, nick@ukidev.com
   Copyright (c) Andre ambidex@gmx.net
   Copyright (c) 2001,2002 Ian C Wilson http://www.phesis.org


   Released under the GNU General Public License
   ---------------------------------------------------------------------------------------*/

include ('includes/application_top.php');
// create template elements
$vamTemplate = new vamTemplate;
// include boxes
require (DIR_FS_CATALOG.'templates/'.CURRENT_TEMPLATE.'/source/boxes.php');

// if the customer is not logged on, redirect them to the default page
if (!isset ($_SESSION['customer_id'])) {
	vam_redirect(vam_href_link(FILENAME_DEFAULT));
}

// SMART CHECKOUT BOF
if (SMART_CHECKOUT == 'true') {
  // if the customer is not logged on, redirect them to the default page
  if ((!vam_session_is_registered('customer_id')) && (!vam_session_is_registered('noaccount')) && (!isset($_POST['action']))) {
  	vam_redirect(vam_href_link(FILENAME_DEFAULT));
  }	

} else {
  // if the customer is not logged on, redirect them to the default page
  if ((!vam_session_is_registered('customer_id')) && (!isset($_POST['action']))) {
     vam_redirect(vam_href_link(FILENAME_DEFAULT));
  }
}
//SMART CHECKOUT EOF

if (isset ($_GET['action']) && ($_GET['action'] == 'update')) {

	if ($_SESSION['account_type'] != 1) {
		vam_redirect(vam_href_link(FILENAME_DEFAULT));
	} else {
		vam_redirect(vam_href_link(FILENAME_LOGOFF));
	}
}
$breadcrumb->add(NAVBAR_TITLE_1_CHECKOUT_SUCCESS);
$breadcrumb->add(NAVBAR_TITLE_2_CHECKOUT_SUCCESS);

require (DIR_WS_INCLUDES.'header.php');

$orders_query = vam_db_query("select orders_id, orders_status from ".TABLE_ORDERS." where customers_id = '".$_SESSION['customer_id']."' order by orders_id desc limit 1");
$orders = vam_db_fetch_array($orders_query);
$last_order = $orders['orders_id'];
$order_status = $orders['orders_status'];

$vamTemplate->assign('FORM_ACTION', vam_draw_form('order', vam_href_link(FILENAME_CHECKOUT_SUCCESS, 'action=update', 'SSL')));
$vamTemplate->assign('BUTTON_CONTINUE', '<a class="button" href="'.vam_href_link(FILENAME_DEFAULT, '', 'SSL').'">'.vam_image_button('submit.png', IMAGE_BUTTON_CONTINUE).'</a>');
$vamTemplate->assign('BUTTON_PRINT', '<a class="button" target="_blank" href="'.vam_href_link(FILENAME_PRINT_ORDER, 'oID='.$orders['orders_id']).'">'.vam_image_button('print.png', IMAGE_BUTTON_PRINT).'</a>');
$vamTemplate->assign('FORM_END', '</form>');
// GV Code Start
$gv_query = vam_db_query("select amount from ".TABLE_COUPON_GV_CUSTOMER." where customer_id='".$_SESSION['customer_id']."'");
if ($gv_result = vam_db_fetch_array($gv_query)) {
	if ($gv_result['amount'] > 0) {
		$vamTemplate->assign('GV_SEND_LINK', vam_href_link(FILENAME_GV_SEND));
	}
}
// GV Code End

	include (DIR_WS_CLASSES.'order.php');
	$order = new order($orders['orders_id']);

if ($order->info['payment_method'] == 'schet') {
$vamTemplate->assign('BUTTON_SCHET_PRINT', '<a class="button" target="_blank" href="'.vam_href_link(FILENAME_PRINT_SCHET, 'oID='.$orders['orders_id']).'">'.vam_image_button('print.png', BUTTON_PRINT_SCHET).'</a>');
}

if ($order->info['payment_method'] == 'schet') {
$vamTemplate->assign('BUTTON_PACKINGSLIP_PRINT', '<a class="button" target="_blank" href="'.vam_href_link(FILENAME_PRINT_PACKINGSLIP, 'oID='.$orders['orders_id']).'">'.vam_image_button('print.png', BUTTON_PRINT_PACKINGSLIP).'</a>');
}

if ($order->info['payment_method'] == 'kvitancia') {
$vamTemplate->assign('BUTTON_KVITANCIA_PRINT', '<a class="button" target="_blank" href="'.vam_href_link(FILENAME_PRINT_KVITANCIA, 'oID='.$orders['orders_id']).'">'.vam_image_button('print.png', BUTTON_PRINT_KVITANCIA).'</a>');
}

if ($order->info['payment_method'] == 'kupivkredit') {

require_once (DIR_FS_INC.'vam_kupi_v_kredit.inc.php');	
	
      $kupi_v_kredit = '';

                               $server = ((MODULE_PAYMENT_KUPIVKREDIT_TEST == 'test') ? $server = 'https://kupivkredit-test-fe.tcsbank.ru/widget/vkredit.js' : $server = 'https://www.kupivkredit.ru/widget/vkredit.js');
                               $order_sum = (int)$order->info['total'];

		$items = array();

			foreach ($order->products as $product) {
	 
				$items[] = array(
					'title' => $product['name'],
					'category' => get_category_name(get_category_id($product['id'])),
					'qty' => $product['qty'],
					'price' => round($product['price'])
				); 
			}

			$details =   array (
				'firstname' => $order->customer['firstname'],
				'lastname' => $order->customer['lastname'],
				'middlename' => $order->customer['secondname'],
				'email' => $order->customer['email_address'],
				'cellphone' => $order->customer['telephone'],
			);
			
		$order = array (
		  'items' => $items,
		  'details' => $details,
		  'partnerId' => MODULE_PAYMENT_KUPIVKREDIT_ID,
		  'partnerName' => STORE_NAME,
		  'partnerOrderId' => $orders['orders_id'],
		  'deliveryType' => ''
		);

    $base64 = base64_encode(json_encode($order));
    $sig = signMessage($base64, MODULE_PAYMENT_KUPIVKREDIT_SECRET_KEY);

		
      $kupi_v_kredit = '
<script type="text/javascript">
$.getScript("' . $server . '", function() {
  vkredit = new VkreditWidget(1, ' . $order_sum . ',  {
			order: "' . $base64 . '",
			sig: "' . $sig . '"
	});
	vkredit.openWidget();
});
	

</script>
';	
	
$vamTemplate->assign('KUPIVKREDIT_CODE', $kupi_v_kredit);
}


// Google Conversion tracking
if (GOOGLE_CONVERSION == 'true') {

include(DIR_WS_MODULES . 'analytics/analytics.php');

$tracking_code .= '

<script>
  (function(i,s,o,g,r,a,m){i[\'GoogleAnalyticsObject\']=r;i[r]=i[r]||function(){
  (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
  m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
  })(window,document,\'script\',\'//www.google-analytics.com/analytics.js\',\'ga\');

  ga(\'create\', \'' . GOOGLE_CONVERSION_ID . '\', \'auto\');
  ga(\'send\', \'pageview\');
  ga(\'require\', \'displayfeatures\');  
  ga(\'require\', \'ecommerce\', \'ecommerce.js\');

  ga(\'ecommerce:addTransaction\', {
' . $transaction_string . '
});

' . $item_string . '

  ga(\'ecommerce:send\');

</script>

		    ';

	$vamTemplate->assign('google_tracking', 'true');
	$vamTemplate->assign('tracking_code', $tracking_code);

}

if (YANDEX_METRIKA == 'true') {

include(DIR_WS_MODULES . 'analytics/metrika.php');

$tracking_code .= '
<script type="text/javascript">
var yaParams = {
	
'.$transaction_string.'	
  goods: 
     [
'.$item_string.'	
      ]
	};
</script>
<div style="display:none;"><script type="text/javascript">
(function(w, c) {
    (w[c] = w[c] || []).push(function() {
        try {
            w.yaCounter' . YANDEX_METRIKA_ID . ' = new Ya.Metrika({id:' . YANDEX_METRIKA_ID . ', enableAll: true,webvisor:true,ut:"noindex",params:window.yaParams||{ }});
        }
        catch(e) { }
    });
})(window, \'yandex_metrika_callbacks\');
</script></div>
<script src="//mc.yandex.ru/metrika/watch.js" type="text/javascript" defer="defer"></script>
<noscript><div><img src="//mc.yandex.ru/watch/' . YANDEX_METRIKA_ID . '" style="position:absolute; left:-9999px;" alt="" /></div></noscript>
		    ';

	$vamTemplate->assign('google_tracking', 'true');
	$vamTemplate->assign('tracking_code', $tracking_code);

}

if (DOWNLOAD_ENABLED == 'true')
	include (DIR_WS_MODULES.'downloads.php');
$vamTemplate->assign('language', $_SESSION['language']);
$vamTemplate->assign('PAYMENT_BLOCK', $payment_block);
$vamTemplate->caching = 0;
$main_content = $vamTemplate->fetch(CURRENT_TEMPLATE.'/module/checkout_success.html');

$vamTemplate->assign('language', $_SESSION['language']);
$vamTemplate->assign('main_content', $main_content);
$vamTemplate->caching = 0;
if (!defined(RM)) $vamTemplate->loadFilter('output', 'note');
$template = (file_exists('templates/'.CURRENT_TEMPLATE.'/'.FILENAME_CHECKOUT_SUCCESS.'.html') ? CURRENT_TEMPLATE.'/'.FILENAME_CHECKOUT_SUCCESS.'.html' : CURRENT_TEMPLATE.'/index.html');
$vamTemplate->display($template);
include ('includes/application_bottom.php');
?>