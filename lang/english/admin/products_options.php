<?php
/* --------------------------------------------------------------
   $Id: products_options.php 1101 2007-02-07 17:36:57 VaM $

   VaM Shop - open source ecommerce solution
   http://vamshop.ru
   http://vamshop.com

   Copyright (c) 2007 VaM Shop
   --------------------------------------------------------------
   based on: 
   (c) 2000-2001 The Exchange Project  (earlier name of osCommerce)
   (c) 2002-2003 osCommerce(products_attributes.php,v 1.9 2002/03/30); www.oscommerce.com 
   (c) 2003	 nextcommerce (products_attributes.php,v 1.4 2003/08/1); www.nextcommerce.org
   (c) 2004	 xt:Commerce (products_attributes.php,v 1.4 2003/08/1); xt-commerce.com

   Released under the GNU General Public License 
   --------------------------------------------------------------*/

define('HEADING_TITLE_OPT', 'Product Options');
define('HEADING_TITLE_VAL', 'Option Names');
define('HEADING_TITLE_ATRIB', 'Products Attributes');

define('TABLE_HEADING_ID', 'ID');
define('TABLE_HEADING_PRODUCT', 'Product Name');
define('TABLE_HEADING_OPT_NAME', 'Option Name');
define('TABLE_HEADING_OPT_VALUE', 'Option Value');
define('TABLE_HEADING_OPT_PRICE', 'Value Price');
define('TABLE_HEADING_OPT_PRICE_PREFIX', 'Prefix (+/-)');
define('TABLE_HEADING_ACTION', 'Action');
define('TABLE_HEADING_DOWNLOAD', 'Downloadable products:');
define('TABLE_TEXT_FILENAME', 'Filename:');
define('TABLE_TEXT_MAX_DAYS', 'Expiry days:');
define('TABLE_TEXT_MAX_COUNT', 'Maximum download count:');

define('MAX_ROW_LISTS_OPTIONS', 10);

define('TEXT_WARNING_OF_DELETE', 'This option has products and values linked to it - it is not safe to delete it.');
define('TEXT_OK_TO_DELETE', 'This option has no products and values linked to it - it is safe to delete it.');
define('TEXT_SEARCH','Search: ');
define('TEXT_OPTION_ID', 'Option ID');
define('TEXT_OPTION_NAME', 'Option Name');

// VaM

define('TABLE_HEADING_OPT_IMAGE','Image');
define('TABLE_HEADING_OPT_DESC','Description');
define('TABLE_TEXT_DELETE','Delete image');
define('TEXT_OPTIONS_IMAGE','Option image');
define('TEXT_NOTE','*NOTE: Rows / Size / Length only for TEXT attributes');
define('TEXT_ROWS','Rows');
define('TEXT_SIZE','Size');
define('TEXT_MAX_LENGTH','Max Length');
define('TABLE_HEADING_OPT_TYPE','Option Type');

define('TEXT_TYPE_SELECT','-- Please Select --');
define('TEXT_TYPE_DROPDOWN','Dropdown');
define('TEXT_TYPE_TEXT','Text');
define('TEXT_TYPE_TEXTAREA','Textarea');
define('TEXT_TYPE_RADIO','Radio');
define('TEXT_TYPE_CHECKBOX','Checkbox');
define('TEXT_TYPE_READ_ONLY','Read Only');

?>