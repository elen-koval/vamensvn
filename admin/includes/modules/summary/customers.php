<?php
/* -----------------------------------------------------------------------------------------
   $Id: customers.php 950 2007-02-08 12:51:57 VaM $   

   VaM Shop - open source ecommerce solution
   http://vamshop.ru
   http://vamshop.com

   Copyright (c) 2007 VaM Shop
   -----------------------------------------------------------------------------------------
   based on: 
   (c) 2006	 osCommerce (customers.php,v 1.25 2003/08/19); oscommerce.com

   Released under the GNU General Public License 
   ---------------------------------------------------------------------------------------*/

defined('_VALID_VAM') or die('Direct Access to this location is not allowed.');

?>
          <tr>
            <td valign="top"><table border="0" width="100%" cellspacing="0" cellpadding="2">
				  <tr> 
				    <td colspan="3" class="pageHeading" width="100%">

    <h1 class="contentBoxHeading"><?php echo TABLE_HEADING_CUSTOMERS; ?></h1>
				    
				    </td>
				  </tr>
              <tr class="dataTableHeadingRow">
                <td width="35%" class="dataTableHeadingContent"><?php echo TABLE_HEADING_LASTNAME; ?></td>
                <td width="35%" class="dataTableHeadingContent"><?php echo TABLE_HEADING_FIRSTNAME; ?></td>
                <td width="30%" class="dataTableHeadingContent"><?php echo TABLE_HEADING_DATE; ?></td>
              </tr>

<?php
	$customers_query_raw = "select
	                                c.customers_id,
	                                c.customers_lastname,
	                                c.customers_firstname,
	                                c.customers_date_added
	                                from
	                                ".TABLE_CUSTOMERS." c order by c.customers_date_added desc limit 20";

	$customers_query = vam_db_query($customers_query_raw);
	while ($customers = vam_db_fetch_array($customers_query)) {


?>
              <tr>
                <td class="dataTableContent"><a href="<?php echo vam_href_link(FILENAME_CUSTOMERS, vam_get_all_get_params(array ('cID')).'cID='.$customers['customers_id'].'&action=edit'); ?>"><?php echo $customers['customers_lastname']; ?></a></td>
                <td class="dataTableContent"><a href="<?php echo vam_href_link(FILENAME_CUSTOMERS, vam_get_all_get_params(array ('cID')).'cID='.$customers['customers_id'].'&action=edit'); ?>"><?php echo $customers['customers_firstname']; ?></a></td>
                <td class="dataTableContent"><?php echo $customers['customers_date_added']; ?></td>
              </tr>
<?php

	}
?>

                </table></td>
              </tr>