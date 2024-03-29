<?php
/* -----------------------------------------------------------------------------------------
   $Id: yandex_fizlico.php 998 2009/02/07 13:24:46 VaM $

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

define('MODULE_PAYMENT_YANDEX_FIZLICO_TEXT_TITLE', 'Яндекс.Касса (Яндекс.Деньги, карточки Visa, MasterCard)');
define('MODULE_PAYMENT_YANDEX_FIZLICO_TEXT_PUBLIC_TITLE', 'Яндекс.Касса (Яндекс.Деньги, карточки Visa, MasterCard)');
define('MODULE_PAYMENT_YANDEX_FIZLICO_TEXT_DESCRIPTION', '');
define('MODULE_PAYMENT_YANDEX_FIZLICO_TEXT_ADMIN_DESCRIPTION', 'Модуль приёма оплаты Яндекс.Деньги для физ. лиц.');
  
define('MODULE_PAYMENT_YANDEX_FIZLICO_STATUS_TITLE' , 'Разрешить модуль Яндекс-деньги');
define('MODULE_PAYMENT_YANDEX_FIZLICO_STATUS_DESC' , 'Вы хотите разрешить использование модуля при оформлении заказов?');
define('MODULE_PAYMENT_YANDEX_FIZLICO_ALLOWED_TITLE' , 'Разрешённые страны');
define('MODULE_PAYMENT_YANDEX_FIZLICO_ALLOWED_DESC' , 'Укажите коды стран, для которых будет доступен данный модуль (например RU,DE (оставьте поле пустым, если хотите что б модуль был доступен покупателям из любых стран))');
define('MODULE_PAYMENT_YANDEX_FIZLICO_SHOP_ID_TITLE' , 'Номер кошелька Яндекс.Деньги');
define('MODULE_PAYMENT_YANDEX_FIZLICO_SHOP_ID_DESC' , 'Укажите Ваш кошелёк в яндекс.деньгах.');
define('MODULE_PAYMENT_YANDEX_FIZLICO_SECRET_KEY_TITLE' , 'Секретное слово');
define('MODULE_PAYMENT_YANDEX_FIZLICO_SECRET_KEY_DESC' , 'В данной опции укажите Секретное слово из настроек на сайте яндекс.деньги, необходимо для уведомлений и автоматический смены статуса заказа.');
define('MODULE_PAYMENT_YANDEX_FIZLICO_SORT_ORDER_TITLE' , 'Порядок сортировки');
define('MODULE_PAYMENT_YANDEX_FIZLICO_SORT_ORDER_DESC' , 'Порядок сортировки модуля.');
define('MODULE_PAYMENT_YANDEX_FIZLICO_ZONE_TITLE' , 'Зона');
define('MODULE_PAYMENT_YANDEX_FIZLICO_ZONE_DESC' , 'Если выбрана зона, то данный модуль оплаты будет виден только покупателям из выбранной зоны.');
define('MODULE_PAYMENT_YANDEX_FIZLICO_ORDER_STATUS_ID_TITLE' , 'Укажите оплаченный статус заказа');
define('MODULE_PAYMENT_YANDEX_FIZLICO_ORDER_STATUS_ID_DESC' , 'Укажите оплаченный статус заказа.');
  
?>